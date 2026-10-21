<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/ImageEffects.php';

/** Standalone CLI adapter. All rendering belongs to ImageEffects and its helpers. */
final class Cli
{
    /** Each option is [type, default, required]. New operations need no local shell changes. */
    private static function commands(): array
    {
        $input = ['input' => ['string', null, true]];
        $mask = ['mask' => ['string', null, true]];
        $config = ['config' => ['string', null, true]];
        $polygons = ['polygons' => ['string', null, false]];
        $review = ['reviewed' => ['flag', false, false]];
        $body = ['full-body' => ['flag', false, false]];
        $size = ['width' => ['int', 1080, false], 'height' => ['int', 1920, false]];
        $reflection = ['opacity' => ['float', .4, false], 'depth' => ['int', 480, false]];
        return [
            'background_fill_prepare' => $config,
            'background_fill_apply' => ['request' => ['string', null, true], 'candidate' => ['string', null, true],
                'resize-candidate' => ['flag', false, false], 'feather' => ['int', null, false]],
            'compose_batch' => $config + ['zip' => ['flag', false, false], 'templates' => ['string', null, false]],
            'contact_sheet' => $config + ['zip' => ['flag', false, false]],
            'normalize_srgb' => $input,
            'prepare' => $input,
            'segment' => $input,
            'detect_supports' => $input + $polygons,
            'preview_masks' => $input + $mask + $polygons,
            'remove_supports' => $input + $mask + ['polygons' => ['string', null, true]] + $review,
            'background' => $size + ['top' => ['int', 239, false], 'bottom' => ['int', 255, false]],
            'gradient' => $config + $size,
            'lights' => $input + $config,
            'background_effects' => [
                'config' => ['string', null, false], 'type' => ['string', null, false],
                'seed' => ['int', null, false], 'impact' => ['float', null, false],
                'input' => ['string', null, false], 'width' => ['int', null, false],
                'height' => ['int', null, false], 'resolved' => ['string', null, false],
            ],
            'layout' => $input + $mask + ['margin' => ['int', 80, false]],
            'shadow' => $size + ['contacts' => ['json', [], false], 'opacity' => ['float', .15, false], 'blur' => ['float', 12, false]],
            'foot_shadow' => $mask + $body + ['opacity' => ['float', .3, false], 'blur' => ['float', 5, false],
                'length' => ['float', 8, false], 'angle' => ['float', 200, false], 'report' => ['string', null, true]],
            'reflection' => $input + $mask + $body + ['floor-y' => ['int', null, true], 'opacity' => ['float', .2, false], 'depth' => ['int', 220, false]],
            'reflection_surface' => $input + $mask + $body + $reflection + ['contact-line' => ['json', null, true],
                'vertical-scale' => ['float', .75, false], 'blur' => ['float', 0, false]],
            'reflection_mask' => $input + $mask + $reflection,
            'lighting' => $input + ['strength' => ['float', .12, false], 'target' => ['string', 'background', false],
                'allow-subject-change' => ['flag', false, false], 'center' => ['json', [.5, .25], false], 'radius' => ['float', .7, false]],
            'export' => $input + $mask + ['background' => ['string', null, true], 'layers' => ['list', [], false]],
            'verify' => $input + $mask + ['result' => ['string', null, true]],
            'preset' => ['from-json' => ['string', null, false]],
            'process' => $input + ['mask' => ['string', null, false]] + $polygons + $review + $body + [
                'floor-y' => ['int', null, false], 'contacts' => ['json', [], false],
                'preset' => ['string', null, false], 'allow-subject-change' => ['flag', false, false],
            ],
        ];
    }

    public static function main(array $args): int
    {
        try {
            $commands = self::commands();
            $op = array_shift($args);
            if ($op === null || in_array($op, ['--help', '-h'], true)) {
                echo 'Usage: run.sh <' . implode('|', array_keys($commands)) . "> [arguments]\n";
                echo "Rendering uses the framework PHP library. Run <operation> --help for options.\n";
                return 0;
            }
            $op = preg_replace('/\.py$/', '', preg_replace('/^figure_/', '', $op));
            if (!isset($commands[$op])) throw new InvalidArgumentException('Unknown operation: ' . $op);
            $schema = $commands[$op] + [
                'output' => ['string', null, true],
                'source-is-srgb' => ['flag', false, false],
                'temp-dir' => ['string', getenv('IMAGE_EFFECTS_TEMP_DIR') ?: null, false],
            ];
            if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
                echo "Framework image effects: $op\n";
                foreach ($schema as $key => [$type, $default, $required]) {
                    echo '  --' . $key . ($type === 'flag' ? '' : ' <' . $type . '>')
                        . ($required ? ' (required)' : ($default !== null ? ' (default: ' . json_encode($default) . ')' : '')) . "\n";
                }
                echo "--output must be new (a directory for layout/process/compose_batch/contact_sheet). Existing files are never overwritten.\n";
                if (in_array($op,['compose_batch','contact_sheet'],true)) echo "--config: JSON specification. Paths relative to JSON directory. --zip adds images.zip (images only). See ImageComposition.md.\n";
                if ($op === 'normalize_srgb') echo "Convert embedded RGB ICC with Little CMS (FFI); untagged RGB is assumed sRGB. Output is PNG. CMYK/gray profiles are unsupported.\n";
                if (str_starts_with($op, 'background_fill_')) echo "BackgroundFill.md: prepare a Codex image-tool request, then apply its candidate. No CLI API fallback. --output is a new directory. White mask pixels mean edit; outside pixels are preserved.\n";
                echo "--source-is-srgb confirms ICC-tagged input is already sRGB; GD does not convert ICC colors.\n";
                echo "--temp-dir must be a writable directory for HEIC/mask staging.\n";
                return 0;
            }
            $a = self::parse($args, $schema);
            if (!extension_loaded('gd')) throw new RuntimeException('PHP GD is required.');
            return self::run($op, $a);
        } catch (\Throwable $e) {
            fwrite(STDERR, json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            return 1;
        }
    }

    private static function parse(array $args, array $schema): array
    {
        $a = array_map(static fn($s) => $s[1], $schema); $seen = [];
        while ($args) {
            $arg = array_shift($args);
            if (!str_starts_with($arg, '--')) throw new InvalidArgumentException('Expected option, got: ' . $arg);
            $parts = explode('=', substr($arg, 2), 2); $key = $parts[0];
            if (!isset($schema[$key]) || isset($seen[$key])) throw new InvalidArgumentException('Unknown or duplicate option: --' . $key);
            $seen[$key] = true; $type = $schema[$key][0];
            if ($type === 'flag') {
                if (count($parts) !== 1) throw new InvalidArgumentException('Flag takes no value: --' . $key);
                $a[$key] = true; continue;
            }
            if ($type === 'list') {
                $a[$key] = count($parts) === 2 ? [$parts[1]] : [];
                while ($args && !str_starts_with($args[0], '--')) $a[$key][] = array_shift($args);
                continue;
            }
            $value = $parts[1] ?? array_shift($args);
            if ($value === null || str_starts_with($value, '--')) throw new InvalidArgumentException('Value required: --' . $key);
            if ($type === 'int') {
                if (!preg_match('/^-?\d+$/D', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('Integer required: --' . $key);
                $value = (int) $value;
            } elseif ($type === 'float') {
                if (!is_numeric($value) || !is_finite((float) $value)) throw new InvalidArgumentException('Finite number required: --' . $key);
                $value = (float) $value;
            } elseif ($type === 'json') {
                $value = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($value)) throw new InvalidArgumentException('JSON array required: --' . $key);
            }
            $a[$key] = $value;
        }
        foreach ($schema as $key => $s) if ($s[2] && ($a[$key] === null || $a[$key] === '')) throw new InvalidArgumentException('Required option: --' . $key);
        return $a;
    }

    private static function json(string $path): array
    {
        $data = file_get_contents($path);
        if ($data === false) throw new RuntimeException('Cannot read JSON: ' . $path);
        $value = json_decode($data, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new InvalidArgumentException('JSON object/array required.');
        return $value;
    }

    private static function newPaths(array $paths): void
    {
        $seen = [];
        foreach ($paths as $path) {
            $parent = realpath(dirname($path));
            if ($parent === false || !is_dir($parent) || !is_writable($parent)) throw new RuntimeException('Writable output parent required: ' . $path);
            $key = $parent . '/' . basename($path);
            if (file_exists($path) || is_link($path) || isset($seen[$key])) throw new RuntimeException('Outputs must differ and must not exist: ' . $path);
            $seen[$key] = true;
        }
    }

    private static function writeJson(string $path, array $data): void
    {
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $stream = @fopen($path, 'xb');
        if ($stream === false) throw new RuntimeException('Cannot create new JSON output.');
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $n = fwrite($stream, substr($bytes, $offset));
                if (!$n) throw new RuntimeException('Cannot write JSON output.');
                $offset += $n;
            }
        } catch (\Throwable $e) { fclose($stream); unlink($path); throw $e; }
        fclose($stream);
    }

    private static function temp(array $a): string
    {
        $dir = $a['temp-dir'];
        if (!$dir || !is_dir($dir) || !is_writable($dir)) throw new InvalidArgumentException('Supply a writable --temp-dir for HEIC or mask generation.');
        return $dir;
    }

    /** Load a layer without color conversion or discarding alpha. */
    private static function image(string $path): GdImage
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > 40 * 1024 * 1024) throw new InvalidArgumentException('Readable image up to 40MB required.');
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $info[0] > 24000000 / $info[1]) throw new InvalidArgumentException('PNG/JPEG up to 24 million pixels required.');
        $image = @imagecreatefromstring(file_get_contents($path));
        if (!$image) throw new RuntimeException('Cannot decode image.');
        if (!imageistruecolor($image)) imagepalettetotruecolor($image);
        imagealphablending($image, false); imagesavealpha($image, true);
        return $image;
    }

    private static function prepare(array $a): GdImage
    {
        // prepare() only uses this directory when HEIC needs staging.
        $prefix = file_get_contents($a['input'], false, null, 0, 48);
        $heic = $prefix !== false && substr($prefix, 4, 4) === 'ftyp';
        $image = ImageEffects::prepare($a['input'], $heic ? self::temp($a) : ($a['temp-dir'] ?? ''), $a['source-is-srgb']);
        for ($y = 0; $y < imagesy($image); $y++) for ($x = 0; $x < imagesx($image); $x++) {
            if ((imagecolorat($image, $x, $y) >> 24) & 127) throw new InvalidArgumentException('Transparent subject/background input is unsupported; supply an opaque photograph.');
        }
        return $image;
    }

    private static function mask(string $path, GdImage $src): GdImage
    {
        $mask = self::image($path);
        if (imagesx($mask) !== imagesx($src) || imagesy($mask) !== imagesy($src)) throw new InvalidArgumentException('Mask must match input dimensions.');
        return ImageEffects::mask($mask);
    }

    private static function proposal(array $a, GdImage $src): array
    {
        if (empty($a['polygons'])) return ['regions' => [], 'polygons' => [], 'occludes_subject' => false];
        $data = self::json($a['polygons']);
        if (($data['image_size'] ?? null) !== [imagesx($src), imagesy($src)] || ($data['image_sha256'] ?? null) !== hash_file('sha256', $a['input'])
            || !isset($data['regions']) || !is_array($data['regions']) || !array_is_list($data['regions'])) throw new InvalidArgumentException('Proposal dimensions/hash/regions must match the exact input image.');
        $polygons = []; $occludes = false;
        foreach ($data['regions'] as $region) {
            if (!is_array($region)) throw new InvalidArgumentException('Invalid region.');
            $confidence = $region['confidence'] ?? null;
            if ((!is_int($confidence) && !is_float($confidence)) || !is_finite((float) $confidence) || $confidence < 0 || $confidence > 1
                || !is_bool($region['occludes_subject'] ?? null)) throw new InvalidArgumentException('Region needs confidence 0..1 and boolean occludes_subject.');
            $polygon = $region['polygon'] ?? null;
            if (!is_array($polygon) || !array_is_list($polygon) || count($polygon) < 3 || count($polygon) > 1000) throw new InvalidArgumentException('Polygon requires 3..1000 vertices.');
            foreach ($polygon as $point) {
                if (!is_array($point) || !array_is_list($point) || count($point) !== 2) throw new InvalidArgumentException('Vertex must be [x,y].');
                foreach ($point as $i => $v) if ((!is_int($v) && !is_float($v)) || !is_finite((float) $v) || $v < 0 || $v >= ($i === 0 ? imagesx($src) : imagesy($src))) throw new InvalidArgumentException('Polygon points must be inside the input image.');
            }
            $polygons[] = $polygon; $occludes = $occludes || $region['occludes_subject'];
        }
        $data['polygons'] = $polygons; $data['occludes_subject'] = $occludes;
        return $data;
    }

    private static function sidecar(string $output, string $suffix): string
    {
        $name = pathinfo($output, PATHINFO_FILENAME);
        return dirname($output) . '/' . $name . $suffix;
    }

    private static function run(string $op, array $a): int
    {
        if ($op === 'background_fill_prepare' || $op === 'background_fill_apply') {
            require_once __DIR__ . '/BackgroundFill.php';
            if ($op === 'background_fill_prepare') {
                $report = BackgroundFill::prepare(self::json($a['config']), dirname(realpath($a['config'])), $a['output'], 'auto-task', $a['source-is-srgb']);
            } else {
                $report = BackgroundFill::apply($a['request'], $a['candidate'], $a['output'], $a['resize-candidate'], $a['feather']);
            }
            echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            return 0;
        }
        if ($op === 'compose_batch' || $op === 'contact_sheet') {
            require_once __DIR__ . '/ImageComposition.php';
            $config=self::json($a['config']);
            if($op==='compose_batch' && $a['templates']!==null){
                $templates=self::json($a['templates']);$local=$config['templates']??[];
                if(!is_array($local)||array_intersect_key($templates,$local))throw new InvalidArgumentException('Template sources must be maps with unique names.');
                $config['templates']=$templates+$local;
            }
            if($op==='compose_batch')ImageComposition::batch($config, dirname(realpath($a['config'])), $a['output'], $a['zip'], $a['source-is-srgb']);
            else ImageComposition::sheet($config, dirname(realpath($a['config'])), $a['output'], $a['zip'], $a['source-is-srgb']);
            return 0;
        }
        $out = $a['output']; $paths = [$out];
        if ($op === 'background_effects') {
            $resolved = $a['resolved'] ?? self::sidecar($out, '.json'); $paths[] = $resolved;
        }
        if ($op === 'foot_shadow') $paths[] = $a['report'];
        if ($op === 'reflection_mask') { $paths[] = $maskOut = self::sidecar($out, '-mask.png'); $paths[] = $reportOut = self::sidecar($out, '-report.json'); }
        self::newPaths($paths);
        if($op==='normalize_srgb'){
            if(strtolower(pathinfo($out,PATHINFO_EXTENSION))!=='png')throw new InvalidArgumentException('normalize_srgb output must use .png.');
            require_once __DIR__.'/ColorProfiles.php';ImageEffects::savePng(ColorProfiles::normalize($a['input']),$out);return 0;
        }
        if ($op === 'preset') { ImageEffects::savePreset($a['from-json'] ? self::json($a['from-json']) : [], $out); return 0; }
        if ($op === 'background') { ImageEffects::savePng(ImageEffects::background($a['width'], $a['height'], $a['top'], $a['bottom']), $out); return 0; }
        if ($op === 'gradient') { ImageEffects::savePng(ImageEffects::gradient($a['width'], $a['height'], self::json($a['config'])), $out); return 0; }
        if ($op === 'shadow') { ImageEffects::savePng(ImageEffects::shadow($a['width'], $a['height'], $a['contacts'], $a['opacity'], $a['blur']), $out); return 0; }
        if ($op === 'background_effects') {
            if (($a['config'] === null) === ($a['type'] === null)) throw new InvalidArgumentException('Supply exactly one of --config/--type.');
            if ($a['input'] !== null && ($a['width'] !== null || $a['height'] !== null)) throw new InvalidArgumentException('Omit width/height with --input.');
            $config = $a['config'] ? self::json($a['config']) : ['effects' => [['type' => $a['type']]]];
            if ($a['type'] !== null && !in_array($a['type'], ['spotlight', 'lightning', 'cloud', 'fireworks'], true)) throw new InvalidArgumentException('Unknown preset type.');
            if ($a['seed'] !== null) $config['seed'] = $a['seed'];
            if ($a['impact'] !== null) {
                $changed = false;
                foreach ($config['effects'] ?? [] as $i => $effect) if (in_array($effect['type'] ?? null, ['spotlight', 'lightning', 'cloud', 'fireworks'], true)) {
                    $config['effects'][$i]['impact'] = $a['impact']; $changed = true;
                }
                if (!$changed) throw new InvalidArgumentException('--impact requires preset types.');
            }
            $bg = $a['input'] !== null ? self::prepare($a) : null;
            $effect = ImageEffects::backgroundEffects($bg ? imagesx($bg) : ($a['width'] ?? 1080), $bg ? imagesy($bg) : ($a['height'] ?? 1440), $config);
            if ($bg) { imagealphablending($bg, true); imagecopy($bg, $effect['layer'], 0, 0, 0, 0, imagesx($bg), imagesy($bg)); }
            ImageEffects::savePng($bg ?? $effect['layer'], $out); self::writeJson($resolved, $effect['config']); return 0;
        }
        if ($op === 'foot_shadow') {
            $result = ImageEffects::footShadow(self::image($a['mask']), array_intersect_key($a, array_flip(['opacity', 'blur', 'length', 'angle'])), $a['full-body']);
            ImageEffects::savePng($result['layer'], $out); self::writeJson($a['report'], $result['report']); return $result['report']['supported'] ? 0 : 3;
        }
        if ($op === 'process') return self::pipeline($a);
        // Mask coordinates refer to already-normalized input for these operations.
        $src = in_array($op, ['prepare', 'segment', 'lights'], true) ? self::prepare($a) : self::image($a['input']);
        if ($op === 'prepare') { ImageEffects::savePng($src, $out); return 0; }
        if ($op === 'segment') { ImageEffects::savePng(ImageEffects::createMask($src, self::temp($a))['mask'], $out); return 0; }
        if ($op === 'lights') { ImageEffects::savePng(ImageEffects::backgroundLights($src, self::json($a['config'])), $out); return 0; }
        if ($op === 'lighting') { ImageEffects::savePng(ImageEffects::lighting($src, $a['strength'], $a['center'], $a['radius'], $a['target'], $a['allow-subject-change']), $out); return 0; }
        if ($op === 'detect_supports') {
            $data = self::proposal($a, $src);
            if ($a['polygons']) { unset($data['polygons'], $data['occludes_subject']); $data['state'] = 'proposal_requires_visual_review'; }
            else $data = ['image_size' => [imagesx($src), imagesy($src)], 'image_sha256' => hash_file('sha256', $a['input']),
                'instruction' => 'Identify only photographic display bases/support rods. Return regions with label, polygon [[x,y],...], confidence 0..1 and occludes_subject boolean. Original pixel coordinates, top-left origin. Do not classify figure parts/weapons as supports. Flag ambiguity; never invent hidden detail. Empty regions if none.',
                'regions' => [], 'state' => 'awaiting_ai_proposal'];
            self::writeJson($out, $data); return 0;
        }
        $mask = self::mask($a['mask'], $src);
        if ($op === 'preview_masks' || $op === 'remove_supports') {
            $data = self::proposal($a, $src);
            $result = $op === 'preview_masks' ? ImageEffects::previewMasks($src, $mask, $data['polygons'])
                : ImageEffects::removeSupports($mask, $data['polygons'], $a['reviewed'], $data['occludes_subject']);
            ImageEffects::savePng($result, $out); return 0;
        }
        if ($op === 'layout') {
            $placed = ImageEffects::layout($src, $mask, 1080, 1920, $a['margin']); self::mkdir($out);
            ImageEffects::savePng($placed['reference'], $out . '/reference.png'); ImageEffects::savePng($placed['mask'], $out . '/mask.png');
            self::writeJson($out . '/transform.json', $placed['transform']); return 0;
        }
        if ($op === 'reflection') $result = ImageEffects::reflection($src, $mask, $a['floor-y'], $a['full-body'], $a['opacity'], $a['depth']);
        elseif ($op === 'reflection_surface') $result = ImageEffects::reflectionOnSurface($src, $mask, $a['contact-line'], $a['full-body'], $a['opacity'], $a['depth'], $a['vertical-scale'], $a['blur']);
        elseif ($op === 'reflection_mask') {
            $result = ImageEffects::reflectionFromMask($src, $mask, $a['opacity'], $a['depth']);
            ImageEffects::savePng($result['layer'], $out); ImageEffects::savePng($result['mask'], $maskOut); self::writeJson($reportOut, $result['report']); return $result['report']['supported'] ? 0 : 3;
        } elseif ($op === 'export') {
            if (imagesx($src) !== 1080 || imagesy($src) !== 1920) throw new InvalidArgumentException('Export requires 1080x1920 placed input.');
            $result = ImageEffects::compose($src, $mask, self::image($a['background']), array_map([self::class, 'image'], $a['layers']));
        } elseif ($op === 'verify') {
            $result = self::image($a['result']); $report = self::verify($src, $result, $mask);
            $bytes = file_get_contents($a['result']);
            $report['png_format_ok'] = substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n";
            $report['srgb_profile_ok'] = self::srgbPng($bytes);
            self::writeJson($out, $report); return $report['pixel_preservation_pass'] && $report['output_size_ok'] && $report['png_format_ok'] && $report['srgb_profile_ok'] ? 0 : 2;
        } else throw new RuntimeException('Unhandled operation.');
        ImageEffects::savePng($result, $out); return 0;
    }

    private static function mkdir(string $path): void
    {
        if (!mkdir($path, 0777)) throw new RuntimeException('Cannot create new output directory.');
    }

    private static function verify(GdImage $src, GdImage $result, GdImage $mask): array
    {
        return ImageEffects::verify($src, $result, $mask) + ['output_size_ok' => imagesx($result) === 1080 && imagesy($result) === 1920];
    }

    private static function srgbPng(string $bytes): bool
    {
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
        for ($p = 8, $n = strlen($bytes); $p + 12 <= $n;) {
            $length = unpack('N', substr($bytes, $p, 4))[1];
            if ($length > $n - $p - 12) return false;
            if (substr($bytes, $p + 4, 4) === 'sRGB' && $length === 1 && ord($bytes[$p + 8]) <= 3) return true;
            $p += 12 + $length;
        }
        return false;
    }

    private static function pipeline(array $a): int
    {
        $s = $a['preset'] ? ImageEffects::loadPreset($a['preset']) : ImageEffects::settings();
        if ($s['light_target'] === 'subject' && !$a['allow-subject-change']) throw new InvalidArgumentException('Subject lighting requires --allow-subject-change.');
        if ($a['floor-y'] !== null && !$a['full-body']) throw new InvalidArgumentException('--floor-y requires --full-body.');
        $src = self::prepare($a); $mask = $a['mask'] ? self::mask($a['mask'], $src) : ImageEffects::createMask($src, self::temp($a))['mask'];
        $data = self::proposal($a, $src);
        $result = ImageEffects::process($src, $mask, ['settings' => $s, 'reviewed' => $a['reviewed'],
            'polygons' => $data['polygons'], 'occludes_subject' => $data['occludes_subject'], 'full_body' => $a['full-body'],
            'reflections' => $a['floor-y'] === null ? [] : [['floor_y' => $a['floor-y']]],
            'contacts' => $a['contacts'], 'allow_subject_change' => $a['allow-subject-change']]);
        $out = $a['output']; self::mkdir($out);
        ImageEffects::savePng($src, $out . '/normalized.png'); ImageEffects::savePng($mask, $out . '/mask-original.png');
        ImageEffects::savePng($result['preview'] ?? ImageEffects::previewMasks($src, $mask, $data['polygons']), $out . '/mask-preview.png');
        if ($result['state'] === 'awaiting_mask_review') {
            self::writeJson($out . '/report.json', ['state' => $result['state'], 'visual_review_required' => true]); return 3;
        }
        foreach (['reference', 'mask', 'background', 'result'] as $name) ImageEffects::savePng($result[$name], $out . '/' . $name . '.png');
        foreach ($result['layers'] as $i => $layer) ImageEffects::savePng($layer, $out . '/layer-' . ($i + 1) . '.png');
        ImageEffects::savePng(ImageEffects::comparison($result['reference'], $result['result']), $out . '/comparison.png');
        self::writeJson($out . '/transform.json', $result['report']['transform']); self::writeJson($out . '/preset.json', $result['report']['settings']);
        $report = $result['report'] + ['output_size_ok' => imagesx($result['result']) === 1080 && imagesy($result['result']) === 1920,
            'state' => $result['state'], 'original_file_sha256' => hash_file('sha256', $a['input']), 'reflection_plane' => $a['floor-y'],
            'supports_removed' => count($data['regions']), 'subject_lighting_applied' => $s['light_target'] === 'subject' && $s['light_strength'] > 0];
        self::writeJson($out . '/report.json', $report); return $report['pixel_preservation_pass'] ? 0 : 2;
    }
}
