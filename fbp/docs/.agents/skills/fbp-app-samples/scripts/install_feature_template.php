<?php
// Usage: php install_feature_template.php advanced-charts|vcard-qr|vimeo-integration <app-source-root>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $name = $argv[1] ?? '';
    if (!in_array($name, ['advanced-charts', 'vcard-qr', 'vimeo-integration'], true) || count($argv) !== 3) {
        throw new InvalidArgumentException('Specify feature name and existing app source root');
    }
    $root = realpath($argv[2]);
    if (!$root || !is_dir($root . '/classes/app')) throw new InvalidArgumentException('App classes/app is missing');
    $source = dirname(__DIR__) . '/assets/' . $name;
    $manifest = json_decode(file_get_contents($source . '/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
    // Complete preflight before writing anything. No DB, secrets, menu or dashboard mutations.
    foreach ($manifest['files'] as $file) {
        if (!preg_match('~^[a-zA-Z0-9_/.-]+$~D', $file) || str_contains($file, '..') || $file[0] === '/') throw new RuntimeException('Invalid manifest path');
        if (!is_file($source . '/' . $file)) throw new RuntimeException('Missing asset: ' . $file);
        $class = explode('/', $file)[0];
        if (file_exists($root . '/classes/app/' . $class) || is_link($root . '/classes/app/' . $class)) {
            throw new RuntimeException('Refusing to overwrite existing class: ' . $class);
        }
    }
    foreach ($manifest['files'] as $file) {
        $dest = $root . '/classes/app/' . $file;
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0775, true)) throw new RuntimeException('Cannot create directory');
        if (!copy($source . '/' . $file, $dest)) throw new RuntimeException('Copy failed: ' . $file);
    }
    echo 'Installed ' . $name . ' (' . count($manifest['files']) . " files). Read its Skill reference before enabling.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
