#!/usr/bin/env php
<?php
// Never expose this filesystem CLI as an HTTP endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/Cli.php';
exit(\Fbp\ImageEffects\Cli::main(array_slice($argv, 1)));
