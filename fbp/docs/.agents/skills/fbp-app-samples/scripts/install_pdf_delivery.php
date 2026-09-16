<?php
// Usage: php install_pdf_delivery.php <app-root>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? realpath($argv[1]) : false;
if ($root === false || !is_dir($root . '/classes/app')) {
    fwrite(STDERR, "Specify an existing app root containing classes/app.\n"); exit(1);
}
$assets = dirname(__DIR__) . '/assets/pdf-delivery';
$manifest = json_decode(file_get_contents($assets . '/pdf-delivery.json'), true, 512, JSON_THROW_ON_ERROR);
if (file_exists($root . '/classes/app/public_pages')) {
    fwrite(STDERR, "public_pages already exists; install in a clean app or adapt manually.\n"); exit(1);
}
foreach ($manifest['files'] as $file) {
    $destination = $root . '/classes/app/' . $file;
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
        throw new RuntimeException('Cannot create destination directory');
    }
    if (!copy($assets . '/' . $file, $destination)) { throw new RuntimeException('Copy failed: ' . $file); }
}
echo "Installed PDF Delivery (3 files). Open public_pages/page and run the Playwright verification.\n";
