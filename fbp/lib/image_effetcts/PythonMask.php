<?php
namespace Fbp\ImageEffects;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/** PHP owns decoding/color/orientation; Python only selects original RGB pixels. */
final class PythonMask
{
    public static function create(GdImage $image, string $tempDir, ?string $python = null, int $timeout = 60, int $tolerance = 28): array
    {
        if (!is_dir($tempDir) || !is_writable($tempDir) || $timeout < 1 || $timeout > 300 || $tolerance < 0 || $tolerance > 100) {
            throw new InvalidArgumentException('Writable temporary directory, timeout 1..300 and tolerance 0..100 required.');
        }
        if (!function_exists('proc_open')) throw new RuntimeException('Python process execution is disabled.');
        if (imagesx($image) < 1 || imagesy($image) < 1 || imagesx($image) > 24000000 / imagesy($image)) {
            throw new InvalidArgumentException('Mask input exceeds 24 million pixels.');
        }
        // open_basedir blocks PHP stat() of /usr/bin and /opt, but not OS process execution.
        // Do not probe executable files outside the app; proc_open reports missing executables.
        $restricted = trim((string) ini_get('open_basedir')) !== '';
        $python = $python ?? (!$restricted && is_executable('/opt/fbp-image-effects/bin/python3') ? '/opt/fbp-image-effects/bin/python3' : '/usr/bin/python3');
        if (!str_starts_with($python, '/') || str_contains($python, "\0")
            || (!$restricted && (!is_file($python) || !is_executable($python)))) {
            throw new InvalidArgumentException('An absolute executable Python path is required.');
        }
        $input = tempnam($tempDir, 'ie_rgb_');
        if ($input === false) throw new RuntimeException('Cannot allocate input staging file.');
        $output = tempnam($tempDir, 'ie_mask_');
        if ($output === false) { unlink($input); throw new RuntimeException('Cannot allocate output staging file.'); }
        $process = null; $pipes = []; $started = microtime(true);
        try {
            $rgb = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagealphablending($rgb, false);
            imagecopy($rgb, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            if (!imagebmp($rgb, $input, false)) throw new RuntimeException('Cannot encode mask input.');
            unset($rgb);
            // Array-form proc_open does not use a shell. No user-provided model/script or environment.
            $process = proc_open([$python, '-B', __DIR__ . '/python/mask_worker.py', $input, $output, (string) $tolerance],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__,
                ['PATH' => '/usr/bin:/bin', 'PYTHONDONTWRITEBYTECODE' => '1', 'OMP_NUM_THREADS' => '1']);
            if (!is_resource($process)) throw new RuntimeException('Cannot start mask generator.');
            fclose($pipes[0]); unset($pipes[0]);
            stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
            $stdout = ''; $stderr = ''; $deadline = microtime(true) + $timeout;
            do {
                $stdout .= stream_get_contents($pipes[1]); $stderr .= stream_get_contents($pipes[2]);
                if (strlen($stdout) + strlen($stderr) > 262144) throw new RuntimeException('Mask generator exceeded diagnostic limit.');
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) > $deadline) throw new RuntimeException('Mask generation timed out.');
                usleep(20000);
            } while (true);
            $stdout .= stream_get_contents($pipes[1]); $stderr .= stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) fclose($pipe);
            $pipes = [];
            $closed = proc_close($process); $process = null;
            $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
            $report = json_decode($stdout, true);
            if ($exit !== 0 || !is_array($report) || isset($report['error'])) {
                $kind = is_array($report) ? ($report['kind'] ?? 'worker_failed') : 'worker_failed';
                $allowed = ['invalid_input', 'segmentation_unavailable', 'mask_uncertain', 'worker_failed'];
                if (!in_array($kind, $allowed, true)) $kind = 'worker_failed';
                throw new RuntimeException('Mask generation failed: ' . $kind);
            }
            if (($report['width'] ?? null) !== imagesx($image) || ($report['height'] ?? null) !== imagesy($image)
                || ($report['mask_sha256'] ?? '') !== hash_file('sha256', $output)) {
                throw new RuntimeException('Mask dimensions or checksum do not match.');
            }
            $info = @getimagesize($output);
            if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] !== imagesx($image) || $info[1] !== imagesy($image)) {
                throw new RuntimeException('Invalid generated mask PNG.');
            }
            $mask = @imagecreatefrompng($output);
            if (!$mask) throw new RuntimeException('Cannot decode generated mask.');
            $report['duration_seconds'] = round(microtime(true) - $started, 3);
            $report['visual_review_required'] = true;
            return ['mask' => ImageEffects::mask($mask), 'report' => $report];
        } finally {
            if (is_resource($process)) proc_terminate($process, 9);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) proc_close($process);
            foreach ([$input, $output] as $file) if (is_file($file)) unlink($file);
        }
    }
}
