<?php

/** Authenticate only: no message retrieval, mailbox selection, or setting updates. */
function fbp_test_receive_connection(array $values): void {
    $protocol = $values['receive_protocol'] ?? '';
    $secure = $values['receive_secure'] ?? '';
    $host = trim((string) ($values['receive_server'] ?? ''));
    $user = (string) ($values['receive_user'] ?? '');
    $password = (string) ($values['receive_password'] ?? '');
    $port = (string) ($values['receive_port'] ?? '');
    if (!in_array($protocol, ['pop', 'imap'], true) || !in_array($secure, ['none', 'tls', 'ssl'], true)
        || $host === '' || $user === '' || $password === '' || $port === '') {
        throw new DomainException('setting.receive.test_missing');
    }
    // Do not allow c-client mailbox flags, paths, or rsh syntax in the hostname.
    if (!(filter_var($host, FILTER_VALIDATE_IP) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
        || !preg_match('/^[0-9]{1,5}$/D', $port) || (int) $port < 1 || (int) $port > 65535) {
        throw new DomainException('setting.receive.invalid');
    }
    if (!function_exists('imap_open')) {
        throw new DomainException('setting.receive.test_unavailable');
    }
    if (str_contains($host, ':')) $host = '[' . $host . ']';
    $flags = ['none' => '/notls', 'tls' => '/tls/validate-cert', 'ssl' => '/ssl/validate-cert'][$secure];
    $mailbox = '{' . $host . ':' . $port . '/' . ($protocol === 'pop' ? 'pop3' : 'imap') . $flags . '}';
    $timeouts = [];
    $connection = false;
    // Consume c-client diagnostics; server responses must not expose credentials in notifications/logs.
    imap_errors();
    imap_alerts();
    try {
        foreach ([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $type) {
            $timeouts[$type] = imap_timeout($type);
            imap_timeout($type, 10);
        }
        $connection = @imap_open($mailbox, $user, $password, $protocol === 'pop' ? 0 : OP_HALFOPEN | OP_READONLY, 1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        if ($connection === false) {
            throw new DomainException('setting.receive.test_failed');
        }
    } finally {
        if ($connection !== false) @imap_close($connection);
        imap_errors();
        imap_alerts();
        foreach ($timeouts as $type => $timeout) imap_timeout($type, $timeout);
    }
}
