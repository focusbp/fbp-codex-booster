<?php
/** Same manifest installer, separate source placement and test definition configuration. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
array_splice($argv, 1, 0, ['incoming-mail']);
require __DIR__ . '/install_workflow_sample.php';
