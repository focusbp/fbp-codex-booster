<?php
// Server-side metadata/delete adapter. Never print the token or raw API errors.
final class sample_vimeo_api {
    private $token;
    private $transport;

    function __construct(string $token, ?callable $transport = null) {
        if ($token === '' || preg_match('/[\r\n]/', $token)) throw new InvalidArgumentException('Vimeo token is not configured');
        $this->token = $token;
        $this->transport = $transport;
    }

    static function id($value): string {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string) $value)) {
            throw new InvalidArgumentException('Use a Vimeo numeric ID, not a URL');
        }
        return (string) $value;
    }

    function ownedVideo(string $id): array {
        $id = self::id($id);
        $me = $this->request('GET', '/me', null, [200]);
        $video = $this->request('GET', '/videos/' . $id, null, [200]);
        $owner = (string) ($me['uri'] ?? '');
        if (!preg_match('~^/users/[1-9][0-9]*$~D', $owner) || ($video['user']['uri'] ?? '') !== $owner || ($video['uri'] ?? '') !== '/videos/' . $id) {
            throw new RuntimeException('Video does not belong to the configured Vimeo account');
        }
        return $video;
    }

    function edit(string $id, string $title, string $description): void {
        $id = self::id($id);
        $this->ownedVideo($id);
        // Deliberately preserve privacy/embed settings, unlike the legacy edit helper.
        $this->request('PATCH', '/videos/' . $id, ['name' => $title, 'description' => $description], [200]);
    }

    function delete(string $id): void {
        $id = self::id($id);
        $this->ownedVideo($id);
        $this->request('DELETE', '/videos/' . $id, null, [204]);
    }

    private function request(string $method, string $path, ?array $data, array $allowed): array {
        if ($this->transport !== null) {
            [$status, $body] = ($this->transport)($method, $path, $data);
        } else {
            $ch = curl_init('https://api.vimeo.com' . $path);
            $headers = ['Authorization: Bearer ' . $this->token, 'Accept: application/vnd.vimeo.*+json;version=3.4'];
            if ($data !== null) $headers[] = 'Content-Type: application/json';
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => false]);
            if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false) throw new RuntimeException('Vimeo connection failed; check remote state before retry');
        }
        if (!in_array((int) $status, $allowed, true)) throw new RuntimeException('Vimeo request failed (HTTP ' . (int) $status . ')', (int) $status);
        if ((int) $status === 204) return [];
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || isset($decoded['error'])) throw new RuntimeException('Invalid Vimeo response');
        return $decoded;
    }
}
