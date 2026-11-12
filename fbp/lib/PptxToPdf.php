<?php

/** LibreOffice-backed PPTX conversion. No HTTP response or saved-file side effects. */
final class PptxToPdf
{
    /**
     * Return PDF bytes. Pass Controller::get_temp_dir() as $tempDir.
     * The caller owns authorization, storage and delivery of the returned PDF.
     */
    public static function convert(string $input, string $tempDir, int $timeout = 60): string
    {
        if ($timeout < 1 || $timeout > 300) {
            throw new InvalidArgumentException('PPTX conversion timeout must be 1..300 seconds.');
        }
        if (!function_exists('proc_open') || !class_exists('ZipArchive')) {
            throw new RuntimeException('PPTX conversion requires proc_open and the PHP zip extension.');
        }
        $input = realpath($input);
        $tempDir = realpath($tempDir);
        if ($input === false || !is_file($input) || !is_readable($input)) {
            throw new InvalidArgumentException('A readable PPTX file is required.');
        }
        if ($tempDir === false || !is_dir($tempDir) || !is_writable($tempDir)) {
            throw new InvalidArgumentException('A writable temporary directory is required.');
        }
        if (filesize($input) > 100 * 1024 * 1024) {
            throw new InvalidArgumentException('PPTX input exceeds 100 MiB.');
        }
        self::validatePptx($input);
        $work = $tempDir . '/pptx_pdf_' . bin2hex(random_bytes(16));
        if (!mkdir($work, 0700)) {
            throw new RuntimeException('Cannot create PPTX conversion workspace.');
        }
        $process = null;
        $pipes = [];
        // Also release the worker and files if the request exits unexpectedly.
        $cleanup = static function () use (&$work, &$process, &$pipes): void {
            if (is_resource($process)) {
                // GNU timeout forwards TERM to the LibreOffice process group.
                proc_terminate($process);
                $until = microtime(true) + 6;
                while (proc_get_status($process)['running'] && microtime(true) < $until) {
                    usleep(20000);
                }
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) fclose($pipe);
            }
            $pipes = [];
            if (is_resource($process)) proc_close($process);
            $process = null;
            if ($work !== null) self::removeWorkspace($work);
            $work = null;
        };
        register_shutdown_function($cleanup);
        try {
            if (!copy($input, $work . '/input.pptx') || !mkdir($work . '/profile', 0700)
                || !mkdir($work . '/profile/user', 0700)) {
                throw new RuntimeException('Cannot prepare PPTX conversion input.');
            }
            // Separate profiles allow concurrent conversions without sharing user settings.
            // Disable macro execution in this throwaway profile.
            $settings = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<oor:items xmlns:oor="http://openoffice.org/2001/registry">'
                . '<item oor:path="/org.openoffice.Office.Common/Security/Scripting">'
                . '<prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop>'
                . '<prop oor:name="DisableMacrosExecution" oor:op="fuse"><value>true</value></prop></item>'
                . '</oor:items>';
            if (file_put_contents($work . '/profile/user/registrymodifications.xcu', $settings) === false) {
                throw new RuntimeException('Cannot prepare conversion profile.');
            }
            $profileUri = 'file://' . implode('/', array_map('rawurlencode', explode('/', $work . '/profile')));
            // Array arguments bypass the shell. The uploaded filename never becomes an option.
            // Do not stat system binaries: PHP open_basedir may allow only the app directory.
            $process = proc_open([
                '/usr/bin/timeout', '--signal=TERM', '--kill-after=5s', $timeout . 's',
                '/usr/bin/libreoffice', '-env:UserInstallation=' . $profileUri,
                '--headless', '--nologo', '--nodefault', '--norestore',
                '--convert-to', 'pdf:impress_pdf_Export', '--outdir', $work, $work . '/input.pptx',
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $work, [
                'PATH' => '/usr/bin:/bin', 'HOME' => $work, 'TMPDIR' => $work,
                'LANG' => 'C.UTF-8', 'SAL_USE_VCLPLUGIN' => 'svp',
            ]);
            if (!is_resource($process)) throw new RuntimeException('Cannot start PPTX conversion engine.');
            fclose($pipes[0]); unset($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $diagnosticBytes = 0;
            $deadline = microtime(true) + $timeout + 7;
            do {
                foreach ($pipes as $pipe) $diagnosticBytes += strlen(stream_get_contents($pipe));
                if ($diagnosticBytes > 262144) throw new RuntimeException('PPTX conversion exceeded diagnostic limit.');
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) > $deadline) throw new RuntimeException('PPTX conversion timed out.');
                usleep(20000);
            } while (true);
            foreach ($pipes as $pipe) fclose($pipe);
            $pipes = [];
            $closed = proc_close($process); $process = null;
            $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
            if ($exit === 124 || $exit === 137) throw new RuntimeException('PPTX conversion timed out.');
            if ($exit === 127) throw new RuntimeException('LibreOffice or GNU timeout is not installed.');
            if ($exit !== 0) throw new RuntimeException('PPTX conversion engine failed.');
            $output = $work . '/input.pdf';
            // LibreOffice can exit successfully without producing a file.
            if (!is_file($output) || filesize($output) < 5) {
                throw new RuntimeException('PPTX conversion produced no PDF.');
            }
            $pdf = file_get_contents($output);
            if ($pdf === false || !str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('PPTX conversion produced invalid PDF data.');
            }
            return $pdf;
        } finally {
            $cleanup();
        }
    }

    private static function validatePptx(string $input): void
    {
        $zip = new ZipArchive();
        if ($zip->open($input, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('Input is not a PPTX ZIP archive.');
        }
        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('ppt/presentation.xml') === false
                || $zip->numFiles > 20000) {
                throw new InvalidArgumentException('Input is not a supported PPTX presentation.');
            }
            $expandedSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false || ($entry['encryption_method'] ?? 0) !== 0) {
                    throw new InvalidArgumentException('Encrypted PPTX files are not supported.');
                }
                $expandedSize += $entry['size'];
                if ($expandedSize > 512 * 1024 * 1024) {
                    throw new InvalidArgumentException('Expanded PPTX exceeds 512 MiB.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function removeWorkspace(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            if (file_exists($path) || is_link($path)) @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') self::removeWorkspace($path . '/' . $name);
        }
        @rmdir($path);
    }
}
