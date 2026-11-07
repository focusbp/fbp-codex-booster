<?php
// Copy to classes/app/passkey/config.php in the application; do not add passkey.php there.
// Fill explicit environment-owned values. No automatic Host/Forwarded header trust.
return [
    'app_code' => 'app-example',
    'production' => ['enabled' => false, 'hosts' => [], 'tls_peers' => []],
    'test' => ['enabled' => false, 'hosts' => [], 'tls_peers' => []],
];
