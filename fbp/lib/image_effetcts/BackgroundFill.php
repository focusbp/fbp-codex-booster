<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/ImageEffects.php';
require_once __DIR__ . '/ImageAiRouting.php';

/** AI supplies a candidate; only pixels in the explicit binary edit mask are copied. */
final class BackgroundFill
{
    private const MAX_PIXELS = 24000000;

    /** Paths in the job are relative to baseDir. White mask pixels mean EDIT. */
    public static function prepare(array $job, string $baseDir, string $outputDir, string $context = 'php', bool $sourceIsSrgb = false): array
    {
        $provider = ImageAiRouting::provider($context);
        $extra = array_diff(array_keys($job), ['input', 'mask', 'rectangles', 'prompt', 'feather']);
        if ($extra) throw new InvalidArgumentException('Unknown background-fill fields: ' . implode(', ', $extra));
        $feather = $job['feather'] ?? 0;
        self::checkFeather($feather);
        $prompt = $job['prompt'] ?? null;
        if (!is_string($prompt) || trim($prompt) === '' || strlen($prompt) > 16000) throw new InvalidArgumentException('A nonempty prompt up to 16000 bytes is required.');
        if (isset($job['mask']) === isset($job['rectangles'])) throw new InvalidArgumentException('Supply exactly one of mask or rectangles.');
        $inputPath = self::path($baseDir, $job['input'] ?? null);
        // Restrict to PNG/JPEG. HEIC staging and implicit ICC conversion are intentionally absent.
        self::checkImage($inputPath);
        $src = ImageEffects::prepare($inputPath, '', $sourceIsSrgb);
        $w = imagesx($src); $h = imagesy($src);
        $mask = imagecreatetruecolor($w, $h);
        imagefill($mask, 0, 0, 0);
        if (isset($job['mask'])) {
            $maskInput = self::load(self::path($baseDir, $job['mask']));
            if (imagesx($maskInput) !== $w || imagesy($maskInput) !== $h) throw new InvalidArgumentException('Mask must match the orientation-corrected input dimensions.');
            for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
                $v = imagecolorat($maskInput, $x, $y);
                if (($v & 0x7f000000) || !in_array($v & 0xffffff, [0, 0xffffff], true)) throw new InvalidArgumentException('Mask must be opaque binary black/white; white means edit.');
                imagesetpixel($mask, $x, $y, $v & 0xffffff);
            }
        } else {
            $rects = $job['rectangles'];
            if (!is_array($rects) || !array_is_list($rects) || count($rects) < 1 || count($rects) > 100) throw new InvalidArgumentException('rectangles must contain 1..100 [x,y,width,height] entries.');
            foreach ($rects as $rect) {
                if (!is_array($rect) || !array_is_list($rect) || count($rect) !== 4 || count(array_filter($rect, 'is_int')) !== 4) throw new InvalidArgumentException('Rectangle coordinates must be four integers.');
                [$x, $y, $rw, $rh] = $rect;
                if ($x < 0 || $y < 0 || $rw < 1 || $rh < 1 || $rw > $w - $x || $rh > $h - $y) throw new InvalidArgumentException('Rectangle extends outside the input.');
                imagefilledrectangle($mask, $x, $y, $x + $rw - 1, $y + $rh - 1, 0xffffff);
            }
        }
        $apiMask = imagecreatetruecolor($w, $h);
        imagealphablending($apiMask, false); imagesavealpha($apiMask, true);
        $preview = imagecreatetruecolor($w, $h);
        imagecopy($preview, $src, 0, 0, 0, 0, $w, $h);
        $selected = 0;
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $edit = (imagecolorat($mask, $x, $y) & 0xffffff) === 0xffffff;
            imagesetpixel($apiMask, $x, $y, $edit ? 0x7fffffff : 0xffffff);
            if ($edit) {
                $selected++;
                $p = imagecolorat($src, $x, $y);
                imagesetpixel($preview, $x, $y, (min(255, (($p >> 16) & 255) + 90) << 16) | (((($p >> 8) & 255) >> 1) << 8) | (($p & 255) >> 1));
            }
        }
        if ($selected === 0) throw new InvalidArgumentException('Edit mask is empty.');
        self::newDirectory($outputDir);
        try {
            ImageEffects::savePng($src, "$outputDir/input.png");
            ImageEffects::savePng($mask, "$outputDir/mask.png");
            ImageEffects::savePng($apiMask, "$outputDir/api-mask.png");
            ImageEffects::savePng($preview, "$outputDir/preview.png");
            $instructions = "Remove the unwanted text/objects only inside the WHITE regions of mask.png. "
                . "Reconstruct plausible background matching the surrounding photograph. No replacement text. "
                . "Image 1 input.png is the edit target; image 2 mask.png identifies edit regions. "
                . "Keep the same composition, framing, aspect ratio and dimensions ({$w}x{$h}). "
                . "Do not move or redraw elements outside the mask. Additional request: " . trim($prompt);
            $request = [
                'schema' => 'fbp-background-fill-v1', 'status' => 'awaiting_candidate',
                'provider' => $provider, 'width' => $w, 'height' => $h, 'edit_pixels' => $selected,
                'feather' => $feather,
                'files' => [], 'prompt' => $instructions,
                'next_action' => $provider === 'codex'
                    ? 'View input.png and preview.png, then use the built-in image editing tool with input.png and mask.png. If unavailable, stop; do not call the API. Apply its saved candidate with background_fill_apply; inspect result visually.'
                    : 'Use OpenAI Images edits with input.png and api-mask.png, then apply the returned candidate.',
            ];
            foreach (['input.png', 'mask.png', 'api-mask.png', 'preview.png'] as $file) $request['files'][$file] = hash_file('sha256', "$outputDir/$file");
            self::writeJson("$outputDir/request.json", $request);
            return $request;
        } catch (\Throwable $e) {
            foreach (['input.png', 'mask.png', 'api-mask.png', 'preview.png', 'request.json'] as $file) if (is_file("$outputDir/$file")) unlink("$outputDir/$file");
            rmdir($outputDir);
            throw $e;
        }
    }

    /** candidate is a full image with the SAME framing. Resizing requires explicit opt-in. */
    public static function apply(string $requestFile, string $candidatePath, string $outputDir, bool $resizeCandidate = false, ?int $feather = null): array
    {
        $request = self::readRequest($requestFile);
        $feather = $feather ?? ($request['feather'] ?? 0);
        self::checkFeather($feather);
        $dir = dirname($requestFile);
        $src = self::load("$dir/input.png");
        $mask = self::load("$dir/mask.png");
        $candidate = self::load($candidatePath);
        $w = imagesx($src); $h = imagesy($src);
        $cw = imagesx($candidate); $ch = imagesy($candidate);
        if ($w !== $request['width'] || $h !== $request['height'] || imagesx($mask) !== $w || imagesy($mask) !== $h) throw new InvalidArgumentException('Prepared image dimensions changed.');
        $resized = $cw !== $w || $ch !== $h;
        if ($resized) {
            if (!$resizeCandidate || abs(($cw / $ch) / ($w / $h) - 1) > .01) throw new InvalidArgumentException('Candidate dimensions differ; explicit resize requires aspect ratio within 1%.');
            $scaled = imagescale($candidate, $w, $h, IMG_BICUBIC_FIXED);
            if (!$scaled) throw new RuntimeException('Cannot resize candidate.');
            $candidate = $scaled;
        }
        // Copy the original raw RGBA values, never alpha-blend outside the mask.
        $result = imagecreatetruecolor($w, $h);
        imagealphablending($result, false); imagesavealpha($result, true);
        $distances = $feather > 0 ? self::distances($mask, $feather + 1) : null;
        $changed = 0; $edited = 0;
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $original = imagecolorat($src, $x, $y);
            $value = $original;
            if ((imagecolorat($mask, $x, $y) & 0xffffff) === 0xffffff) {
                $value = imagecolorat($candidate, $x, $y);
                if (($value >> 24) & 127) throw new InvalidArgumentException('Candidate must be opaque inside the edit mask.');
                if ($distances !== null) {
                    $weight = min(1, (imagecolorat($distances, $x, $y) - 1) / $feather);
                    $blended = 0;
                    foreach ([0, 8, 16, 24] as $shift) {
                        $channel = (int) round((($value >> $shift) & 255) * $weight + (($original >> $shift) & 255) * (1 - $weight));
                        $blended |= $channel << $shift;
                    }
                    $value = $blended;
                }
                $edited++;
                if ($value !== $original) $changed++;
            }
            imagesetpixel($result, $x, $y, $value);
        }
        if ($edited !== $request['edit_pixels']) throw new InvalidArgumentException('Prepared mask pixel count changed.');
        if ($changed === 0) throw new InvalidArgumentException('Candidate does not change the selected region.');
        self::newDirectory($outputDir);
        try {
            ImageEffects::savePng($result, "$outputDir/result.png");
            $outsideChanged = 0;
            $saved = self::load("$outputDir/result.png");
            for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
                if ((imagecolorat($mask, $x, $y) & 0xffffff) === 0 && imagecolorat($src, $x, $y) !== imagecolorat($saved, $x, $y)) $outsideChanged++;
            }
            if ($outsideChanged !== 0) throw new RuntimeException('Outside-mask pixel preservation failed.');
            $report = ['provider' => $request['provider'], 'width' => $w, 'height' => $h,
                'request_sha256' => hash_file('sha256', $requestFile), 'candidate_sha256' => hash_file('sha256', $candidatePath),
                'result_sha256' => hash_file('sha256', "$outputDir/result.png"), 'candidate_resized' => $resized,
                'edit_pixels' => $edited, 'changed_edit_pixels' => $changed,
                'feather' => $feather,
                'outside_mask_changed_pixels' => $outsideChanged, 'visual_review_required' => true];
            self::writeJson("$outputDir/report.json", $report);
            return $report;
        } catch (\Throwable $e) {
            foreach (['result.png', 'report.json'] as $file) if (is_file("$outputDir/$file")) unlink("$outputDir/$file");
            rmdir($outputDir);
            throw $e;
        }
    }

    /** PHP entry: caller supplies its own API key and Controller::get_temp_dir(). */
    public static function run(array $job, string $baseDir, string $outputDir, string $apiKey, string $tempDir, string $model = 'gpt-image-2', bool $sourceIsSrgb = false): array
    {
        ImageAiRouting::requireApi();
        require_once __DIR__ . '/OpenAiImageEditor.php';
        $editor = new OpenAiImageEditor($apiKey, $model);
        if (!is_dir($tempDir) || !is_writable($tempDir)) throw new InvalidArgumentException('Pass Controller::get_temp_dir() as tempDir.');
        self::prepare($job, $baseDir, $outputDir, 'php', $sourceIsSrgb);
        $request = self::readRequest("$outputDir/request.json");
        $bytes = $editor->edit("$outputDir/input.png", "$outputDir/api-mask.png", $request['prompt']);
        $candidate = tempnam($tempDir, 'background_fill_');
        if ($candidate === false) throw new RuntimeException('Cannot create candidate temporary file.');
        try {
            if (file_put_contents($candidate, $bytes) !== strlen($bytes)) throw new RuntimeException('Cannot write candidate.');
            return self::apply("$outputDir/request.json", $candidate, "$outputDir/applied", true);
        } finally {
            unlink($candidate);
        }
    }

    public static function readRequest(string $path): array
    {
        if (!is_file($path) || filesize($path) > 100000) throw new InvalidArgumentException('Readable request.json required.');
        $r = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($r) || ($r['schema'] ?? null) !== 'fbp-background-fill-v1'
            || ($r['status'] ?? null) !== 'awaiting_candidate' || !in_array($r['provider'] ?? null, ['openai', 'codex'], true)
            || !is_int($r['width'] ?? null) || !is_int($r['height'] ?? null) || !is_int($r['edit_pixels'] ?? null)
            || !is_string($r['prompt'] ?? null)) throw new InvalidArgumentException('Invalid background-fill request.');
        foreach (['input.png', 'mask.png', 'api-mask.png', 'preview.png'] as $file) {
            $p = dirname($path) . '/' . $file;
            if (!is_file($p) || ($r['files'][$file] ?? null) !== hash_file('sha256', $p)) throw new InvalidArgumentException('Prepared asset hash mismatch: ' . $file);
        }
        return $r;
    }

    private static function path(string $baseDir, $path): string
    {
        if (!is_string($path) || $path === '' || str_contains($path, '://') || str_contains($path, "\0")) throw new InvalidArgumentException('Local image path required.');
        return str_starts_with($path, '/') ? $path : $baseDir . '/' . $path;
    }

    private static function checkImage(string $path): void
    {
        if (str_contains($path, '://') || str_contains($path, "\0")) throw new InvalidArgumentException('Local image required.');
        if (!is_file($path) || !is_readable($path) || filesize($path) > 40 * 1024 * 1024) throw new InvalidArgumentException('Readable local image up to 40MB required.');
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $info[0] > self::MAX_PIXELS / $info[1]) throw new InvalidArgumentException('PNG/JPEG up to 24 million pixels required.');
    }

    private static function load(string $path): GdImage
    {
        self::checkImage($path);
        $img = @imagecreatefromstring(file_get_contents($path));
        if (!$img) throw new RuntimeException('Cannot decode image.');
        if (!imageistruecolor($img)) imagepalettetotruecolor($img);
        imagealphablending($img, false); imagesavealpha($img, true);
        return $img;
    }

    private static function newDirectory(string $path): void
    {
        if (file_exists($path) || is_link($path) || !is_dir(dirname($path)) || !mkdir($path, 0700)) throw new InvalidArgumentException('A new output directory under an existing parent is required.');
    }

    private static function checkFeather($value): void
    {
        if (!is_int($value) || $value < 0 || $value > 64) throw new InvalidArgumentException('feather must be an integer from 0 to 64.');
    }

    /** Bounded Manhattan distance from black pixels or image edges, stored in GD, not PHP arrays. */
    private static function distances(GdImage $mask, int $limit): GdImage
    {
        $w = imagesx($mask); $h = imagesy($mask);
        $d = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $v = 0;
            if ((imagecolorat($mask, $x, $y) & 0xffffff) === 0xffffff) {
                $v = min($limit, $x === 0 ? 1 : imagecolorat($d, $x - 1, $y) + 1,
                    $y === 0 ? 1 : imagecolorat($d, $x, $y - 1) + 1);
            }
            imagesetpixel($d, $x, $y, $v);
        }
        for ($y = $h - 1; $y >= 0; $y--) for ($x = $w - 1; $x >= 0; $x--) {
            $v = imagecolorat($d, $x, $y);
            if ($v > 0) imagesetpixel($d, $x, $y, min($v,
                $x === $w - 1 ? 1 : imagecolorat($d, $x + 1, $y) + 1,
                $y === $h - 1 ? 1 : imagecolorat($d, $x, $y + 1) + 1));
        }
        return $d;
    }

    private static function writeJson(string $path, array $value): void
    {
        $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($path, $bytes) !== strlen($bytes)) throw new RuntimeException('Cannot write output JSON.');
    }
}
