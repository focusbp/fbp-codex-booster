<?php
// Usage: php install_admin_pdf_delivery.php <app-root>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = isset($argv[1]) ? realpath($argv[1]) : false;
if ($root === false || !is_dir($root . '/classes/app')) {
    fwrite(STDERR, "Specify an existing app root containing classes/app.\n"); exit(1);
}
$assets = dirname(__DIR__) . '/assets/admin-pdf-delivery';
$manifest = json_decode(file_get_contents($assets . '/admin-pdf-delivery.json'), true, 512, JSON_THROW_ON_ERROR);
if (file_exists($root . '/classes/app/pdf_delivery_admin')) {
    fwrite(STDERR, "pdf_delivery_admin already exists; refusing to overwrite.\n"); exit(1);
}
foreach ($manifest['files'] as $file) {
    if (!is_file($assets . '/' . $file)) throw new RuntimeException('Missing asset: ' . $file);
}
foreach ($manifest['files'] as $file) {
    $destination = $root . '/classes/app/' . $file;
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
        throw new RuntimeException('Cannot create destination directory');
    }
    if (!copy($assets . '/' . $file, $destination)) throw new RuntimeException('Copy failed: ' . $file);
}
echo 'Installed Admin PDF Delivery (' . count($manifest['files']) . " files). Open pdf_delivery_admin/run through the management Ajax path; run verify_admin_pdf_delivery.cjs.\n";
