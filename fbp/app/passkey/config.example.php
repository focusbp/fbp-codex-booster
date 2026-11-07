<?php
// Copy to classes/app/passkey/config.php in the application; do not add passkey.php there.
// Provision from registered application domains; there is no enable/disable switch.
// No automatic Host/Forwarded header trust.
return [
    'app_code' => 'app-example',
    'production' => ['hosts' => [], 'tls_peers' => []],
    'test' => ['hosts' => [], 'tls_peers' => []],
];
