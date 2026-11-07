<?php
final class PasskeyConfig {
    public static function load(Controller $ctl): array {
        $file = dirname(__DIR__, 3) . '/classes/app/passkey/config.php';
        if (!is_file($file)) throw new RuntimeException('パスキーは現在利用できません。');
        $all = require $file;
        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        // Gateway host is explicitly configured; legacy FBP testserver detection only recognizes test-* hosts.
        $test = in_array($requestHost, $all['test']['hosts'] ?? [], true);
        $config = $all[$test ? 'test' : 'production'] ?? [];
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        // No forwarded-host trust and no ports/paths accepted from the request.
        if (!preg_match('/^[a-z0-9.-]+$/D', $host) || !in_array($host, $config['hosts'] ?? [], true)) {
            throw new RuntimeException('このサイトではパスキーを利用できません。');
        }
        $directTls = in_array(strtolower((string)($_SERVER['HTTPS'] ?? $_SERVER['REDIRECT_HTTPS'] ?? '')), ['on','1'], true);
        // TLS ends at the configured private gateway; never infer trust from a client header.
        $trustedTlsPeer = in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), $config['tls_peers'] ?? [], true);
        if (!$directTls && !$trustedTlsPeer) throw new RuntimeException('HTTPSが必要です。');
        $appCode = $all['app_code'] ?? '';
        if (!is_string($appCode) || !preg_match('/^app-[a-z0-9-]+$/D', $appCode)) throw new RuntimeException('パスキー設定を確認してください。');
        return ['app_code'=>$appCode, 'rp_id'=>$host, 'origin'=>'https://' . $host];
    }
    public static function available(Controller $ctl): bool {
        try { self::load($ctl); return true; } catch (Throwable $e) { return false; }
    }
}
