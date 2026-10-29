<?php
require_once __DIR__ . '/ReceiveMailTransport.php';
require_once __DIR__ . '/ReceiveMailMime.php';

/** Shared incoming account, explicit acknowledgement, and stable-identity deletion. */
final class ReceiveMail {
    private $ctl;
    private $db;
    private array $setting;
    private string $account;
    private string $key;
    private bool $systemAccount = true;

    public function __construct($ctl, ?array $account = null) {
        $this->ctl = $ctl;
        // Open before reading: FFM may reorder and reacquire existing DB locks here.
        $systemDb = $ctl->db('setting', 'setting');
        $this->db = $systemDb;
        if ($account !== null) {
            if (array_diff(array_keys($account), ['table', 'id', 'password_encrypted']) || !is_string($account['table'] ?? null)
                || !preg_match('/^[a-z][a-z0-9_]*$/D', $account['table']) || !is_int($account['id'] ?? null) || $account['id'] < 1) {
                throw new InvalidArgumentException('Specify an account table and positive integer id.');
            }
            $this->db = $ctl->db($account['table'], 'common');
            $this->systemAccount = false;
        }
        // Read only after all DBs are open and lock ordering is settled.
        $system = (array) $systemDb->get(1);
        $this->setting = (array) $this->db->get($account['id'] ?? 1);
        if (!$this->setting) throw new RuntimeException('Incoming account not found.');
        $this->key = (string) (($system['secret'] ?? '') ?: ($system['api_secret'] ?? ''));
        if ($account['password_encrypted'] ?? false) {
            $this->setting['receive_password'] = $ctl->decrypt($this->setting['receive_password'] ?? '');
        }
        $identity = [];
        foreach (['receive_protocol', 'receive_server', 'receive_port', 'receive_secure', 'receive_user', 'receive_folder'] as $key) {
            $identity[$key] = (string) ($this->setting[$key] ?? '');
        }
        $identity['receive_server'] = strtolower(trim($identity['receive_server']));
        $identity['receive_folder'] = $identity['receive_protocol'] === 'imap' ? (trim($identity['receive_folder']) ?: 'INBOX') : '';
        if ($account !== null) $identity['store'] = [$account['table'], $account['id']];
        $this->account = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        if ($this->key === '') throw new RuntimeException('Incoming mail identity signing key unavailable.');
    }
    private function state(string $validity): array {
        $raw = $this->setting['receive_state'] ?? '';
        if ($raw === '') return [];
        try { $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Invalid stored receive state.'); }
        if (!is_array($state) || ($state['version'] ?? null) !== 1 || !isset($state['account'], $state['uidvalidity'], $state['confirmed']) || !is_array($state['confirmed'])) {
            throw new RuntimeException('Unsupported stored receive state.');
        }
        if ($state['account'] !== $this->account || $state['uidvalidity'] !== $validity) return [];
        return $state['confirmed'];
    }
    private function confirmed(array $confirmed, string $id): bool {
        if (($this->setting['receive_protocol'] ?? '') === 'pop') return in_array($id, $confirmed, true);
        foreach ($confirmed as $range) {
            if (!is_array($range) || count($range) !== 2 || !is_int($range[0]) || !is_int($range[1]) || $range[0] < 1 || $range[1] < $range[0]) {
                throw new RuntimeException('Invalid stored IMAP receive range.');
            }
            if ((int) $id >= $range[0] && (int) $id <= $range[1]) return true;
        }
        return false;
    }
    private function token(string $id, string $validity): string {
        $payload = base64_encode(json_encode(['v' => 1, 'account' => $this->account, 'protocol' => $this->setting['receive_protocol'],
            'uidvalidity' => $validity, 'id' => $id], JSON_THROW_ON_ERROR));
        return $payload . '.' . hash_hmac('sha256', $payload, $this->key);
    }
    private function identity(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], $this->key), $parts[1])) throw new InvalidArgumentException('Invalid received mail identity.');
        $data = json_decode(base64_decode($parts[0], true) ?: '', true);
        if (!is_array($data) || ($data['v'] ?? null) !== 1 || ($data['account'] ?? '') !== $this->account
            || ($data['protocol'] ?? '') !== ($this->setting['receive_protocol'] ?? '') || !is_string($data['id'] ?? null) || !is_string($data['uidvalidity'] ?? null)) {
            throw new InvalidArgumentException('Received mail identity belongs to another account.');
        }
        if ($data['protocol'] === 'imap' && !preg_match('/^[1-9][0-9]*$/D', $data['id'])) throw new InvalidArgumentException('Invalid received UID.');
        if ($data['protocol'] === 'pop' && !preg_match('/^[!-~]{1,70}$/D', $data['id'])) throw new InvalidArgumentException('Invalid received UIDL.');
        return $data;
    }
    private function connection(bool $writable = false): ReceiveMailTransport {
        return new ReceiveMailTransport($this->setting, $writable);
    }
    private function checkValidity(ReceiveMailTransport $connection, array $identity): void {
        if ($connection->uidvalidity !== $identity['uidvalidity']) throw new RuntimeException('IMAP UIDVALIDITY changed; fetch the message again.');
    }
    /** At-least-once delivery; fetching never acknowledges a message. */
    public function receive(int $limit): array {
        if ($limit < 1 || $limit > 100) throw new InvalidArgumentException('Receive limit must be between 1 and 100.');
        if (!function_exists('imap_rfc822_parse_adrlist')) throw new RuntimeException('Incoming MIME address decoding requires the PHP IMAP extension.');
        $connection = $this->connection();
        try {
            $confirmed = $this->state($connection->uidvalidity);
            $messages = []; $bytes = 0;
            foreach ($connection->messages() as $id => $number) {
                $id = (string) $id;
                if ($this->confirmed($confirmed, $id)) continue;
                $raw = $connection->fetch($number);
                $bytes += strlen($raw);
                if ($bytes > 52428800) throw new RuntimeException('Incoming batch exceeds size limit; lower the receive limit.');
                $message = ReceiveMailMime::parse($raw);
                $message['id'] = $this->token($id, $connection->uidvalidity);
                $message['protocol'] = $this->setting['receive_protocol'];
                $message['uid'] = $this->setting['receive_protocol'] === 'imap' ? $id : null;
                $message['uidl'] = $this->setting['receive_protocol'] === 'pop' ? $id : null;
                $message['uidvalidity'] = $connection->uidvalidity;
                $messages[] = $message;
                if (count($messages) >= $limit) break;
            }
            $connection->close();
            return $messages;
        } finally { $connection->abort(); }
    }
    public function confirm(string $token): bool {
        $identity = $this->identity($token);
        // Reconnect even for repeat acknowledgements to detect UIDVALIDITY resets.
        $connection = $this->connection();
        try {
            $this->checkValidity($connection, $identity);
            $confirmed = $this->state($connection->uidvalidity);
            if ($this->confirmed($confirmed, $identity['id'])) { $connection->close(); return true; }
            $ids = $connection->messages();
            if (!array_key_exists($identity['id'], $ids)) throw new RuntimeException('Received message no longer exists.');
            if ($identity['protocol'] === 'pop') {
                // Drop only acknowledgements of messages no longer on this server.
                $confirmed = array_values(array_filter($confirmed, fn($id) => array_key_exists($id, $ids)));
                $confirmed[] = $identity['id'];
            } else {
                $confirmed[] = [(int) $identity['id'], (int) $identity['id']];
                usort($confirmed, fn($a, $b) => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($confirmed as $range) {
                    $last = count($merged) - 1;
                    if ($last >= 0 && $range[0] <= $merged[$last][1] + 1) $merged[$last][1] = max($merged[$last][1], $range[1]);
                    else $merged[] = $range;
                }
                $confirmed = $merged;
            }
            $state = json_encode(['version' => 1, 'account' => $this->account, 'uidvalidity' => $connection->uidvalidity,
                'confirmed' => $confirmed], JSON_THROW_ON_ERROR);
            if (strlen($state) > 65535) throw new RuntimeException('Receive state is full; acknowledgement was not saved.');
            $connection->close();
            // No additional DB is opened between the locked read and update.
            $patch = ['id' => $this->setting['id'], 'receive_state' => $state];
            $this->db->update($patch);
            if ($this->systemAccount) $this->ctl->set_session('setting', $this->db->get(1));
            return true;
        } finally { $connection->abort(); }
    }
    public function delete(string $token): bool {
        $identity = $this->identity($token);
        $connection = $this->connection(true);
        try {
            $this->checkValidity($connection, $identity);
            $ids = $connection->messages();
            // Retry after an uncertain deletion is safe when the stable id has disappeared.
            if (array_key_exists($identity['id'], $ids)) $connection->delete($ids[$identity['id']]);
            $connection->close();
            return true;
        } finally { $connection->abort(); }
    }
}
