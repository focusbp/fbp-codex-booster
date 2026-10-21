<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/** Non-generative image effects. No background inference or AI/API calls. */
final class ImageEffects
{
    public const DEFAULTS = [
        'width' => 1080, 'height' => 1920, 'margin' => 80,
        'background_top' => 239, 'background_bottom' => 255,
        'background_gradient' => null,
        'background_lights' => null,
        'background_effects' => null,
        'reflection_opacity' => 0.2, 'reflection_depth' => 220,
        'shadow_opacity' => 0.15, 'shadow_blur' => 12,
        'light_strength' => 0.0, 'light_target' => 'background',
    ];
    private const MAX_PIXELS = 24000000;

    /** Generate a subject candidate mask using the bundled local model. Pass $ctl->get_temp_dir(). */
    public static function createMask(GdImage $image, string $tempDir, ?string $python = null, int $timeout = 60, int $tolerance = 28): array
    {
        require_once __DIR__ . '/PythonMask.php';
        return PythonMask::create($image, $tempDir, $python, $timeout, $tolerance);
    }

    private static function dimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || $width > self::MAX_PIXELS / $height) {
            throw new InvalidArgumentException('Image must contain 1..24000000 pixels.');
        }
    }

    private static function fraction(float $value): void
    {
        if (!is_finite($value) || $value < 0 || $value > 1) {
            throw new InvalidArgumentException('Strength must be finite and between 0 and 1.');
        }
    }

    private static function sameSize(GdImage ...$images): void
    {
        foreach ($images as $image) {
            if (imagesx($image) !== imagesx($images[0]) || imagesy($image) !== imagesy($images[0])) {
                throw new InvalidArgumentException('Image and mask/layer dimensions must match.');
            }
        }
    }

    private static function canvas(int $width, int $height, bool $transparent = false): GdImage
    {
        self::dimensions($width, $height);
        $image = imagecreatetruecolor($width, $height);
        if (!$image) throw new RuntimeException('Cannot allocate image.');
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $transparent ? 0x7f000000 : 0);
        return $image;
    }

    /** Local file only. Supply $ctl->get_temp_dir() for HEIC staging. Input is never modified.
     * ICC conversion is not supplied by GD. ICC-tagged sources require explicit sRGB confirmation.
     * Untagged input is assumed sRGB. */
    public static function prepare(string $path, string $tempDir, bool $sourceIsSrgb = false): GdImage
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > 40 * 1024 * 1024) {
            throw new InvalidArgumentException('Readable local image up to 40MB required.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new RuntimeException('Cannot read image.');
        $orientation = 1;
        $isHeic = substr($bytes, 4, 4) === 'ftyp'
            && preg_match('/heic|heix|hevc|hevx|mif1|heim|heis/', substr($bytes, 8, 40));
        if ($isHeic) {
            // The existing converter shells out using quoted filenames. Stage only under a safe server path.
            if (!is_dir($tempDir) || !is_writable($tempDir) || !preg_match('~^[a-zA-Z0-9_./ -]+$~D', $tempDir)) {
                throw new InvalidArgumentException('Writable safe temporary directory required for HEIC.');
            }
            require_once dirname(__DIR__, 2) . '/lib_ext/php-heic-to-jpg/src/HeicToJpg.php';
            $stage = tempnam($tempDir, 'image_effect_');
            if ($stage === false) throw new RuntimeException('Cannot create HEIC staging file.');
            try {
                if (file_put_contents($stage, $bytes) !== strlen($bytes)) throw new RuntimeException('Cannot stage HEIC.');
                $bytes = \Maestroerror\HeicToJpg::convert($stage)->get();
            } finally {
                foreach (glob($stage . '*') ?: [] as $temporary) {
                    if (is_file($temporary)) unlink($temporary);
                }
            }
        }
        $size = @getimagesizefromstring($bytes);
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new InvalidArgumentException('JPEG, PNG or HEIC required.');
        }
        self::dimensions($size[0], $size[1]);
        // Read profile markers, not arbitrary image pixel data.
        if (self::hasIccProfile($bytes, $size[2]) && !$sourceIsSrgb) {
            throw new InvalidArgumentException('ICC conversion is unavailable in GD; provide an sRGB-normalized source and sourceIsSrgb=true.');
        }
        $image = @imagecreatefromstring($bytes);
        if (!$image) throw new RuntimeException('Cannot decode image.');
        if (!imageistruecolor($image)) imagepalettetotruecolor($image);
        if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://application/octet-stream;base64,' . base64_encode($bytes));
            $orientation = (int) ($exif['Orientation'] ?? 1);
        }
        if ($orientation === 2) imageflip($image, IMG_FLIP_HORIZONTAL);
        if ($orientation === 3) $image = imagerotate($image, 180, 0);
        if ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
        if (in_array($orientation, [5, 6, 7], true)) $image = imagerotate($image, -90, 0);
        if ($orientation === 5) imageflip($image, IMG_FLIP_HORIZONTAL);
        if ($orientation === 7) imageflip($image, IMG_FLIP_VERTICAL);
        if ($orientation === 8) $image = imagerotate($image, 90, 0);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return $image;
    }

    private static function hasIccProfile(string $bytes, int $type): bool
    {
        if ($type === IMAGETYPE_PNG) {
            for ($p = 8, $n = strlen($bytes); $p + 12 <= $n;) {
                $length = unpack('N', substr($bytes, $p, 4))[1];
                if (substr($bytes, $p + 4, 4) === 'iCCP') return true;
                $p += 12 + $length;
            }
        } else {
            for ($p = 2, $n = strlen($bytes); $p + 4 <= $n;) {
                if (ord($bytes[$p]) !== 255) break;
                $marker = ord($bytes[$p + 1]);
                if ($marker === 0xda || $marker === 0xd9) break;
                $length = unpack('n', substr($bytes, $p + 2, 2))[1];
                if ($length < 2) break;
                if ($marker === 0xe2 && substr($bytes, $p + 4, 12) === "ICC_PROFILE\0") return true;
                $p += 2 + $length;
            }
        }
        return false;
    }

    /** PNG mask: white (>=128)=subject, black=background. Color masks are rejected. */
    public static function mask(GdImage $source): GdImage
    {
        $result = self::canvas(imagesx($source), imagesy($source));
        for ($y = 0; $y < imagesy($source); $y++) for ($x = 0; $x < imagesx($source); $x++) {
            $c = imagecolorsforindex($source, imagecolorat($source, $x, $y));
            if ($c['red'] !== $c['green'] || $c['red'] !== $c['blue'] || $c['alpha'] !== 0) {
                throw new InvalidArgumentException('Mask must be opaque grayscale.');
            }
            imagesetpixel($result, $x, $y, $c['red'] >= 128 ? 0xffffff : 0);
        }
        return $result;
    }

    /** All vertices are original-image pixel coordinates, origin top-left. No hidden-part reconstruction. */
    public static function removeSupports(GdImage $mask, array $polygons, bool $reviewed = false, bool $occludesSubject = false): GdImage
    {
        if (!$reviewed || $occludesSubject) throw new InvalidArgumentException('Review required; occluding supports cannot be restored.');
        $result = self::mask($mask);
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || count($polygon) < 3 || count($polygon) > 1000) throw new InvalidArgumentException('Polygon requires 3..1000 vertices.');
            $flat = [];
            foreach ($polygon as $point) {
                if (!is_array($point) || count($point) !== 2 || !isset($point[0], $point[1])) throw new InvalidArgumentException('Vertex must be [x,y].');
                foreach ($point as $value) {
                    if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) throw new InvalidArgumentException('Finite coordinates required.');
                }
                if ($point[0] < 0 || $point[0] >= imagesx($result) || $point[1] < 0 || $point[1] >= imagesy($result)) throw new InvalidArgumentException('Vertex outside image.');
                $flat[] = (int) round($point[0]); $flat[] = (int) round($point[1]);
            }
            imagefilledpolygon($result, $flat, 0);
        }
        return $result;
    }

    public static function previewMasks(GdImage $image, GdImage $mask, array $polygons = []): GdImage
    {
        self::sameSize($image, $mask);
        $mask = self::mask($mask);
        $preview = self::canvas(imagesx($image), imagesy($image));
        imagecopy($preview, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        for ($y = 0; $y < imagesy($image); $y++) for ($x = 0; $x < imagesx($image); $x++) {
            if ((imagecolorat($mask, $x, $y) & 255) === 0) continue;
            $c = imagecolorat($image, $x, $y);
            $r = (int) round((($c >> 16) & 255) * .65 + 56 * .35);
            $g = (int) round((($c >> 8) & 255) * .65 + 219 * .35);
            $b = (int) round(($c & 255) * .65 + 115 * .35);
            imagesetpixel($preview, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
        // Validate polygon dimensions using the same checks as deletion.
        if ($polygons) self::removeSupports($mask, $polygons, true);
        foreach ($polygons as $polygon) {
            $flat = [];
            foreach ($polygon as $point) { $flat[] = (int) round($point[0]); $flat[] = (int) round($point[1]); }
            imagesetthickness($preview, 2);
            imagepolygon($preview, $flat, 0xff0000);
        }
        return $preview;
    }

    public static function background(int $width = 1080, int $height = 1920, int $top = 239, int $bottom = 255): GdImage
    {
        if (min($top, $bottom) < 0 || max($top, $bottom) > 255) throw new InvalidArgumentException('Gray level must be 0..255.');
        $image = self::canvas($width, $height);
        for ($y = 0; $y < $height; $y++) {
            $gray = (int) round($top + ($bottom - $top) * $y / max(1, $height - 1));
            imageline($image, 0, $y, $width - 1, $y, ($gray << 16) | ($gray << 8) | $gray);
        }
        return $image;
    }

    /** Continuous color-stop gradient; compatible with figure_gradient.py JSON. */
    public static function gradient(int $width, int $height, array $config): GdImage
    {
        require_once __DIR__ . '/Gradient.php';
        return Gradient::render($width, $height, $config);
    }

    /** Apply ordered local lights to the background before compose(). */
    public static function backgroundLights(GdImage $background, array $config): GdImage
    {
        require_once __DIR__ . '/BackgroundLights.php';
        return BackgroundLights::apply($background, $config);
    }

    /** Background addition layer + resolved primitive JSON; presets randomize unless seeded. */
    public static function backgroundEffects(int $width, int $height, array $config): array
    {
        require_once __DIR__ . '/BackgroundEffects.php';
        return BackgroundEffects::render($width, $height, $config);
    }

    /** Returns placed reference, binary mask and coordinate transform. Does not upscale. */
    public static function layout(GdImage $image, GdImage $mask, int $width = 1080, int $height = 1920, int $margin = 80): array
    {
        self::sameSize($image, $mask);
        if ($margin < 0 || $margin * 2 >= min($width, $height)) throw new InvalidArgumentException('Invalid margin.');
        $mask = self::mask($mask);
        $scale = min(1, ($width - 2 * $margin) / imagesx($image), ($height - 2 * $margin) / imagesy($image));
        $w = max(1, (int) round(imagesx($image) * $scale)); $h = max(1, (int) round(imagesy($image) * $scale));
        $x = intdiv($width - $w, 2); $y = intdiv($height - $h, 2);
        $reference = self::canvas($width, $height); $placed = self::canvas($width, $height);
        if ($w === imagesx($image) && $h === imagesy($image)) imagecopy($reference, $image, $x, $y, 0, 0, $w, $h);
        else imagecopyresampled($reference, $image, $x, $y, 0, 0, $w, $h, imagesx($image), imagesy($image));
        imagecopyresized($placed, $mask, $x, $y, 0, 0, $w, $h, imagesx($mask), imagesy($mask));
        return ['reference' => $reference, 'mask' => $placed, 'transform' => ['scale' => $scale,
            'offset' => [$x, $y], 'source_size' => [imagesx($image), imagesy($image)],
            'resized_size' => [$w, $h], 'output_size' => [$width, $height]]];
    }

    /** contacts: [[x,y,horizontal radius],...] in output-canvas coordinates. */
    public static function shadow(int $width, int $height, array $contacts, float $opacity = .15, float $blur = 12): GdImage
    {
        self::fraction($opacity);
        if (!is_finite($blur) || $blur < 0 || $blur > 1000) throw new InvalidArgumentException('Blur must be 0..1000 pixels.');
        $layer = self::canvas($width, $height, true);
        foreach ($contacts as $point) {
            if (!is_array($point) || count($point) !== 3) throw new InvalidArgumentException('Contact must be [x,y,radius].');
            foreach ($point as $value) if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) throw new InvalidArgumentException('Finite contacts required.');
            [$cx, $cy, $radius] = $point;
            if ($cx < 0 || $cy < 0 || $cx >= $width || $cy >= $height || $radius <= 0 || $radius > $width) throw new InvalidArgumentException('Contact outside canvas.');
            $rx = max(1, $radius + $blur); $ry = max(1, 4 + $blur / 2);
            for ($y = max(0, (int) floor($cy - 3 * $ry)); $y <= min($height - 1, (int) ceil($cy + 3 * $ry)); $y++) {
                for ($x = max(0, (int) floor($cx - 3 * $rx)); $x <= min($width - 1, (int) ceil($cx + 3 * $rx)); $x++) {
                    $alpha = $opacity * exp(-2 * (($x - $cx) / $rx) ** 2 - 2 * (($y - $cy) / $ry) ** 2);
                    $old = 1 - ((imagecolorat($layer, $x, $y) >> 24) & 127) / 127;
                    imagesetpixel($layer, $x, $y, ((int) round(127 * (1 - $alpha) * (1 - $old))) << 24);
                }
            }
        }
        return $layer;
    }

    public static function reflection(GdImage $image, GdImage $mask, int $floorY, bool $fullBody, float $opacity = .2, int $depth = 220): GdImage
    {
        self::sameSize($image, $mask); self::fraction($opacity);
        if (!$fullBody || $floorY <= 0 || $floorY >= imagesy($image) - 1 || $depth < 1) throw new InvalidArgumentException('Full body and valid floor/depth required.');
        $mask = self::mask($mask);
        $layer = self::canvas(imagesx($image), imagesy($image), true);
        for ($dy = 1; $dy <= min($depth, imagesy($image) - 1 - $floorY); $dy++) {
            $sy = $floorY - $dy;
            if ($sy < 0) break;
            $alpha = (int) round(127 * (1 - $opacity * (1 - $dy / $depth) ** 2));
            for ($x = 0; $x < imagesx($image); $x++) {
                if ((imagecolorat($mask, $x, $sy) & 255) === 0) continue;
                imagesetpixel($layer, $x, $floorY + $dy, ($alpha << 24) | (imagecolorat($image, $x, $sy) & 0xffffff));
            }
        }
        return $layer;
    }

    public static function lighting(GdImage $image, float $strength = .12, array $center = [.5, .25], float $radius = .7,
        string $target = 'background', bool $allowSubjectChange = false): GdImage
    {
        self::fraction($strength);
        if (!in_array($target, ['background', 'subject'], true) || ($target === 'subject' && !$allowSubjectChange)) throw new InvalidArgumentException('Subject lighting requires explicit permission.');
        if (count($center) !== 2 || !isset($center[0], $center[1])) throw new InvalidArgumentException('Center must be [x,y].');
        foreach ($center as $value) self::fraction($value);
        if (!is_finite($radius) || $radius <= 0) throw new InvalidArgumentException('Positive finite radius required.');
        $result = self::canvas(imagesx($image), imagesy($image));
        for ($y = 0; $y < imagesy($image); $y++) for ($x = 0; $x < imagesx($image); $x++) {
            $factor = $strength * exp(-((($x / max(1, imagesx($image) - 1) - $center[0]) ** 2
                + ($y / max(1, imagesy($image) - 1) - $center[1]) ** 2) / (2 * $radius ** 2)));
            $c = imagecolorat($image, $x, $y); $color = $c & 0x7f000000;
            foreach ([16, 8, 0] as $shift) {
                $v = ($c >> $shift) & 255;
                $color |= ((int) round($v + (255 - $v) * $factor)) << $shift;
            }
            imagesetpixel($result, $x, $y, $color);
        }
        return $result;
    }

    /** Approximate floor reflection using a projected contact polyline and vertical compression. */
    public static function reflectionOnSurface(GdImage $image, GdImage $mask, array $contactLine, bool $fullBody,
        float $opacity = .4, int $depth = 480, float $verticalScale = .75, float $blur = 0): GdImage
    {
        require_once __DIR__ . '/SurfaceReflection.php';
        $layer = SurfaceReflection::create($image, $mask, $contactLine, $fullBody, $opacity, $depth, $verticalScale);
        require_once __DIR__ . '/SoftBlur.php';
        return SoftBlur::apply($layer, $blur);
    }

    /** Requires only the source and its binary mask; all two-foot geometry is mask-derived. */
    public static function reflectionFromMask(GdImage $image, GdImage $mask, float $opacity = .4, int $depth = 480): array
    {
        require_once __DIR__ . '/MaskReflection.php';
        return MaskReflection::create($image, $mask, $opacity, $depth);
    }

    /** Infer two feet from a confirmed full-body mask; returns layer/report, no shadow if ambiguous. */
    public static function footShadow(GdImage $mask, array $options = [], bool $fullBody = false): array
    {
        require_once __DIR__ . '/FootShadow.php';
        return FootShadow::create($mask, $options, $fullBody);
    }

    /** Copies subject pixels last; input objects are never mutated. */
    public static function compose(GdImage $reference, GdImage $mask, GdImage $background, array $layers = []): GdImage
    {
        self::sameSize($reference, $mask, $background, ...$layers);
        $mask = self::mask($mask);
        $result = self::canvas(imagesx($reference), imagesy($reference));
        imagecopy($result, $background, 0, 0, 0, 0, imagesx($reference), imagesy($reference));
        imagealphablending($result, true);
        foreach ($layers as $layer) imagecopy($result, $layer, 0, 0, 0, 0, imagesx($result), imagesy($result));
        imagealphablending($result, false);
        for ($y = 0; $y < imagesy($result); $y++) for ($x = 0; $x < imagesx($result); $x++) {
            if ((imagecolorat($mask, $x, $y) & 255) !== 0) imagesetpixel($result, $x, $y, imagecolorat($reference, $x, $y));
        }
        return $result;
    }

    /** Comparison baseline is oriented/sRGB-confirmed and resized reference, NOT the encoded original. */
    public static function verify(GdImage $reference, GdImage $result, GdImage $mask): array
    {
        self::sameSize($reference, $result, $mask);
        $mask = self::mask($mask); $protected = 0; $changed = 0; $maximum = 0;
        for ($y = 1; $y < imagesy($mask) - 1; $y++) for ($x = 1; $x < imagesx($mask) - 1; $x++) {
            for ($dy = -1; $dy <= 1; $dy++) for ($dx = -1; $dx <= 1; $dx++) {
                if ((imagecolorat($mask, $x + $dx, $y + $dy) & 255) === 0) continue 3;
            }
            $protected++; $a = imagecolorat($reference, $x, $y); $b = imagecolorat($result, $x, $y); $diff = 0;
            foreach ([16, 8, 0] as $shift) $diff = max($diff, abs((($a >> $shift) & 255) - (($b >> $shift) & 255)));
            $changed += (int) ($diff > 0); $maximum = max($maximum, $diff);
        }
        return ['width' => imagesx($result), 'height' => imagesy($result), 'protected_pixels' => $protected,
            'changed_pixels' => $changed, 'max_rgb_difference' => $maximum,
            'pixel_preservation_pass' => $protected > 0 && $changed === 0,
            'mask_accuracy_verified' => false, 'visual_review_required' => true,
            'comparison' => 'Oriented, sRGB-confirmed, proportionally resized reference; mask boundary excluded.'];
    }

    public static function comparison(GdImage $before, GdImage $after): GdImage
    {
        self::sameSize($before, $after);
        $result = self::canvas(imagesx($before) * 2, imagesy($before));
        imagecopy($result, $before, 0, 0, 0, 0, imagesx($before), imagesy($before));
        imagecopy($result, $after, imagesx($before), 0, 0, 0, imagesx($after), imagesy($after));
        return $result;
    }

    /** PNG bytes with explicit sRGB intent. Use only with sRGB-confirmed input. */
    public static function pngData(GdImage $image): string
    {
        ob_start();
        try {
            if (!imagepng($image, null, 6)) throw new RuntimeException('PNG encoding failed.');
            $bytes = ob_get_contents();
        } finally { ob_end_clean(); }
        $body = "sRGB\0";
        return substr($bytes, 0, 33) . pack('N', 1) . $body . pack('N', crc32($body)) . substr($bytes, 33);
    }

    public static function savePng(GdImage $image, string $path): void
    {
        self::writeNew($path, self::pngData($image));
    }

    private static function writeNew(string $path, string $data): void
    {
        $stream = @fopen($path, 'xb');
        if ($stream === false) throw new RuntimeException('Cannot create new output file.');
        try {
            $offset = 0;
            while ($offset < strlen($data)) {
                $n = fwrite($stream, substr($data, $offset));
                if ($n === false || $n === 0) throw new RuntimeException('Cannot write output.');
                $offset += $n;
            }
        } catch (\Throwable $e) { fclose($stream); unlink($path); throw $e; }
        fclose($stream);
    }

    public static function settings(array $values = []): array
    {
        if (array_diff_key($values, self::DEFAULTS)) throw new InvalidArgumentException('Unknown setting.');
        $s = array_replace(self::DEFAULTS, $values);
        if ($s['background_effects'] !== null) {
            if (!is_array($s['background_effects'])) throw new InvalidArgumentException('Background effects must be an object.');
            require_once __DIR__ . '/BackgroundEffects.php';
            $s['background_effects'] = BackgroundEffects::resolve($s['background_effects']);
        }
        if ($s['background_lights'] !== null) {
            if (!is_array($s['background_lights'])) throw new InvalidArgumentException('Lights must be an object.');
            require_once __DIR__ . '/BackgroundLights.php';
            $s['background_lights'] = BackgroundLights::validate($s['background_lights']);
        }
        if ($s['background_gradient'] !== null) {
            if (!is_array($s['background_gradient'])) throw new InvalidArgumentException('Gradient must be an object.');
            require_once __DIR__ . '/Gradient.php';
            $s['background_gradient'] = Gradient::validate($s['background_gradient']);
        }
        foreach (['width', 'height', 'margin', 'background_top', 'background_bottom', 'reflection_depth'] as $key) {
            if (!is_int($s[$key])) throw new InvalidArgumentException($key . ' must be integer.');
        }
        self::dimensions($s['width'], $s['height']);
        if ($s['margin'] < 0 || $s['margin'] * 2 >= min($s['width'], $s['height']) || $s['reflection_depth'] < 1) throw new InvalidArgumentException('Invalid margin/depth.');
        if (min($s['background_top'], $s['background_bottom']) < 0 || max($s['background_top'], $s['background_bottom']) > 255) throw new InvalidArgumentException('Invalid background gray level.');
        foreach (['reflection_opacity', 'shadow_opacity', 'light_strength'] as $key) self::fraction($s[$key]);
        if (!is_numeric($s['shadow_blur']) || !is_finite((float) $s['shadow_blur']) || $s['shadow_blur'] < 0 || $s['shadow_blur'] > 1000) throw new InvalidArgumentException('Invalid blur.');
        if (!in_array($s['light_target'], ['background', 'subject'], true)) throw new InvalidArgumentException('Invalid lighting target.');
        return $s;
    }

    public static function loadPreset(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) throw new RuntimeException('Cannot read preset.');
        $values = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($values)) throw new InvalidArgumentException('Preset must be a JSON object.');
        return self::settings($values);
    }

    public static function savePreset(array $settings, string $path): void
    {
        self::writeNew($path, json_encode(self::settings($settings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    /** Caller must review mask first. Returns GD images/report; performs no file or DB writes.
     * options: settings, reviewed, polygons, occludes_subject, full_body, reflections,
     * contacts, allow_subject_change. reflections = [['floor_y'=>int, 'mask'=>GdImage(optional)],...]. */
    public static function process(GdImage $image, GdImage $mask, array $options = []): array
    {
        $allowed = ['settings', 'reviewed', 'polygons', 'occludes_subject', 'full_body', 'reflections', 'contacts', 'allow_subject_change'];
        if (array_diff(array_keys($options), $allowed)) throw new InvalidArgumentException('Unknown process option.');
        self::sameSize($image, $mask);
        $s = self::settings($options['settings'] ?? []);
        if (($options['reviewed'] ?? false) !== true) {
            return ['state' => 'awaiting_mask_review', 'preview' => self::previewMasks($image, $mask, $options['polygons'] ?? [])];
        }
        if ($options['occludes_subject'] ?? false) throw new InvalidArgumentException('Hidden subject restoration is excluded.');
        $clean = self::removeSupports($mask, $options['polygons'] ?? [], true);
        $placed = self::layout($image, $clean, $s['width'], $s['height'], $s['margin']);
        $bg = $s['background_gradient'] !== null ? self::gradient($s['width'], $s['height'], $s['background_gradient'])
            : self::background($s['width'], $s['height'], $s['background_top'], $s['background_bottom']);
        $subject = $placed['reference']; $layers = [];
        if ($s['background_lights'] !== null) $bg = self::backgroundLights($bg, $s['background_lights']);
        if ($s['background_effects'] !== null) {
            $effect = self::backgroundEffects($s['width'], $s['height'], $s['background_effects']);
            imagealphablending($bg, true);
            imagecopy($bg, $effect['layer'], 0, 0, 0, 0, $s['width'], $s['height']);
        }
        if ($s['light_target'] === 'background') $bg = self::lighting($bg, $s['light_strength']);
        else $subject = self::lighting($subject, $s['light_strength'], [.5, .25], .7, 'subject', ($options['allow_subject_change'] ?? false) === true);
        foreach ($options['reflections'] ?? [] as $reflection) {
            if (!is_array($reflection) || !isset($reflection['floor_y']) || !is_int($reflection['floor_y'])) throw new InvalidArgumentException('Each reflection needs integer floor_y.');
            $rmask = $reflection['mask'] ?? $placed['mask'];
            // Each reflection mask is in final-canvas coordinates and may only select actual subject pixels.
            self::sameSize($rmask, $placed['mask']); $rmask = self::mask($rmask);
            for ($y = 0; $y < imagesy($rmask); $y++) for ($x = 0; $x < imagesx($rmask); $x++) {
                if ((imagecolorat($placed['mask'], $x, $y) & 255) === 0) imagesetpixel($rmask, $x, $y, 0);
            }
            $layers[] = self::reflection($subject, $rmask, $reflection['floor_y'], ($options['full_body'] ?? false) === true, $s['reflection_opacity'], $s['reflection_depth']);
        }
        if (!empty($options['contacts'])) $layers[] = self::shadow($s['width'], $s['height'], $options['contacts'], $s['shadow_opacity'], $s['shadow_blur']);
        $result = self::compose($subject, $placed['mask'], $bg, $layers);
        $report = self::verify($placed['reference'], $result, $placed['mask']);
        $report['settings'] = $s; $report['transform'] = $placed['transform'];
        return ['state' => 'rendered_requires_visual_review', 'reference' => $placed['reference'], 'mask' => $placed['mask'],
            'background' => $bg, 'layers' => $layers, 'result' => $result, 'report' => $report];
    }
}
