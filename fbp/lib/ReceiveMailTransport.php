<?php

/** Bounded POP3/IMAP transport. Never exposes server responses or credentials in errors. */
final class ReceiveMailTransport {
    private $socket;
    private string $protocol;
    private int $tag = 0;
    private array $capabilities = [];
    public string $uidvalidity = '';
    private const MAX_MESSAGE = 10485760;
    private const MAX_RESPONSE = 52428800;

    public function __construct(array $setting, bool $writable = false) {
        $this->protocol = (string) ($setting['receive_protocol'] ?? '');
        $host = trim((string) ($setting['receive_server'] ?? ''));
        $port = (string) ($setting['receive_port'] ?? '');
        $secure = (string) ($setting['receive_secure'] ?? '');
        $user = (string) ($setting['receive_user'] ?? '');
        $password = (string) ($setting['receive_password'] ?? '');
        if (!in_array($this->protocol, ['pop', 'imap'], true) || !in_array($secure, ['none', 'ssl', 'tls'], true)
            || !(filter_var($host, FILTER_VALIDATE_IP) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
            || !preg_match('/^[0-9]{1,5}$/D', $port) || (int) $port < 1 || (int) $port > 65535
            || $user === '' || $password === '' || preg_match('/[\r\n\x00]/', $user . $password)) {
            throw new InvalidArgumentException('Invalid or incomplete incoming mail settings.');
        }
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $address = (str_contains($host, ':') ? '[' . $host . ']' : $host) . ':' . $port;
        $this->socket = @stream_socket_client(($secure === 'ssl' ? 'tls' : 'tcp') . '://' . $address, $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);
        if ($this->socket === false) throw new RuntimeException('Incoming mail connection failed.');
        stream_set_timeout($this->socket, 10);
        try {
            if ($this->protocol === 'pop') {
                $this->popOk($this->line());
                if ($secure === 'tls') { $this->pop('STLS'); $this->startTls(); }
                $this->pop('USER ' . $user);
                $this->pop('PASS ' . $password);
            } else {
                $greeting = $this->line();
                if (!preg_match('/^\* (OK|PREAUTH)\b/i', $greeting)) throw new RuntimeException('Invalid IMAP greeting.');
                if ($secure === 'tls') { $this->imap('STARTTLS'); $this->startTls(); }
                if (!str_starts_with(strtoupper($greeting), '* PREAUTH')) {
                    $this->imap('LOGIN ' . self::quote($user) . ' ' . self::quote($password));
                }
                $caps = $this->imap('CAPABILITY');
                foreach ($caps['lines'] as $line) {
                    if (preg_match('/^\* CAPABILITY (.+)/i', $line, $m)) $this->capabilities = explode(' ', strtoupper(trim($m[1])));
                }
                $folder = trim((string) ($setting['receive_folder'] ?? '')) ?: 'INBOX';
                if (preg_match('/[\r\n\x00]/', $folder)) throw new InvalidArgumentException('Invalid IMAP folder.');
                if (preg_match('/[^\x20-\x7e]/', $folder)) $folder = mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
                $select = $this->imap(($writable ? 'SELECT ' : 'EXAMINE ') . self::quote($folder));
                foreach ($select['lines'] as $line) {
                    if (preg_match('/\[UIDVALIDITY ([1-9][0-9]*)\]/i', $line, $m)) {
                        if ((float) $m[1] > 4294967295) throw new RuntimeException('Invalid IMAP UIDVALIDITY.');
                        $this->uidvalidity = $m[1];
                    }
                    if ($writable && stripos($line, '[READ-ONLY]') !== false) throw new RuntimeException('Mailbox is read-only.');
                }
                if ($this->uidvalidity === '') throw new RuntimeException('IMAP UIDVALIDITY unavailable.');
            }
        } catch (Throwable $e) {
            $this->abort();
            throw $e;
        }
    }

    private static function quote(string $value): string {
        if (preg_match('/[\r\n\x00]/', $value)) throw new InvalidArgumentException('Invalid mail command argument.');
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    private function startTls(): void {
        if (@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new RuntimeException('Incoming mail TLS negotiation failed.');
        }
    }
    private function write(string $data): void {
        for ($offset = 0, $size = strlen($data); $offset < $size;) {
            $written = @fwrite($this->socket, substr($data, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Incoming mail write failed.');
            $offset += $written;
        }
    }
    private function line(): string {
        $line = @fgets($this->socket, 1048578);
        if ($line === false || !str_ends_with($line, "\r\n")) throw new RuntimeException('Incoming mail response failed or timed out.');
        return substr($line, 0, -2);
    }
    private function popOk(string $line): void {
        if (!preg_match('/^\+OK(?: |$)/', $line)) throw new RuntimeException('POP command or authentication failed.');
    }
    private function pop(string $command): void {
        $this->write($command . "\r\n");
        $this->popOk($this->line());
    }
    private function multiline(int $max): string {
        $data = '';
        while (($line = $this->line()) !== '.') {
            if (str_starts_with($line, '..')) $line = substr($line, 1);
            $data .= $line . "\r\n";
            if (strlen($data) > $max) throw new RuntimeException('Incoming mail response exceeds size limit.');
        }
        return $data;
    }
    private function imap(string $command): array {
        $tag = 'F' . ++$this->tag;
        $this->write($tag . ' ' . $command . "\r\n");
        $lines = []; $literals = []; $total = 0;
        while (true) {
            $line = $this->line();
            $total += strlen($line);
            if ($total > self::MAX_RESPONSE) throw new RuntimeException('IMAP response exceeds size limit.');
            $lines[] = $line;
            if (str_starts_with($line, $tag . ' ')) {
                if (!preg_match('/^' . $tag . ' OK\b/i', $line)) throw new RuntimeException('IMAP command or authentication failed.');
                return ['lines' => $lines, 'literals' => $literals];
            }
            if (preg_match('/\{([0-9]+)\}$/D', $line, $m)) {
                $length = (int) $m[1];
                if ($length > self::MAX_MESSAGE || $total + $length > self::MAX_RESPONSE) throw new RuntimeException('Incoming message exceeds size limit.');
                $literal = '';
                while (strlen($literal) < $length) {
                    $chunk = @fread($this->socket, min(65536, $length - strlen($literal)));
                    if ($chunk === false || $chunk === '') throw new RuntimeException('Incomplete IMAP message.');
                    $literal .= $chunk;
                }
                $total += $length;
                $literals[] = $literal;
            }
        }
    }

    /** Stable id => transient message number (POP); stable id => stable id (IMAP). */
    public function messages(): array {
        if ($this->protocol === 'pop') {
            $this->pop('UIDL');
            $lines = explode("\r\n", trim($this->multiline(self::MAX_RESPONSE)));
            $ids = [];
            foreach ($lines as $line) {
                if ($line === '') continue;
                if (!preg_match('/^([1-9][0-9]*) ([!-~]{1,70})$/D', $line, $m) || isset($ids[$m[2]])) throw new RuntimeException('Invalid or duplicate POP UIDL.');
                $ids[$m[2]] = (int) $m[1];
            }
            return $ids;
        }
        $response = $this->imap('UID SEARCH ALL');
        $ids = [];
        foreach ($response['lines'] as $line) {
            if (!preg_match('/^\* SEARCH(?: (.*))?$/D', $line, $m)) continue;
            foreach (explode(' ', $m[1] ?? '') as $uid) {
                if ($uid === '') continue;
                if ((!preg_match('/^[1-9][0-9]*$/D', $uid) || (float) $uid > 4294967295)) throw new RuntimeException('Invalid IMAP UID.');
                $ids[$uid] = $uid;
            }
        }
        uksort($ids, fn($a, $b) => (int) $a <=> (int) $b);
        return $ids;
    }
    public function fetch($number): string {
        if ($this->protocol === 'pop') {
            $this->pop('RETR ' . (int) $number);
            return $this->multiline(self::MAX_MESSAGE);
        }
        $response = $this->imap('UID FETCH ' . (int) $number . ' (UID BODY.PEEK[])');
        $fetchCount = 0;
        foreach ($response['lines'] as $line) {
            if (preg_match('/^\* [0-9]+ FETCH \(/i', $line)) ++$fetchCount;
        }
        $matched = preg_match('/\bUID ' . (int) $number . '\b/i', implode(' ', $response['lines'])) === 1;
        if (!$matched || $fetchCount !== 1 || count($response['literals']) !== 1) throw new RuntimeException('IMAP message disappeared or response was invalid.');
        return $response['literals'][0];
    }
    public function delete($number): void {
        if ($this->protocol === 'pop') {
            $this->pop('DELE ' . (int) $number);
            // Commit only this session's deletion. A failed QUIT leaves the outcome uncertain.
            $this->pop('QUIT');
            $this->abort();
            return;
        }
        if (!in_array('UIDPLUS', $this->capabilities, true) && !in_array('IMAP4REV2', $this->capabilities, true)) {
            throw new RuntimeException('Safe IMAP deletion requires UIDPLUS or IMAP4rev2.');
        }
        $this->imap('UID STORE ' . (int) $number . ' +FLAGS.SILENT (\\Deleted)');
        $this->imap('UID EXPUNGE ' . (int) $number);
    }
    public function close(): void {
        if (!is_resource($this->socket)) return;
        try {
            if ($this->protocol === 'pop') $this->pop('QUIT');
            else $this->imap('LOGOUT'); // Never CLOSE/EXPUNGE unrelated deleted messages.
        } finally { $this->abort(); }
    }
    public function abort(): void {
        if (is_resource($this->socket)) fclose($this->socket);
        $this->socket = null;
    }
    public function __destruct() { $this->abort(); }
}
