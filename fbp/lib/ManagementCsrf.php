<?php

/** Synchronizer tokens for browser management routes, independent of per-tab state. */
final class ManagementCsrf {
    private const SESSION_KEY = '_FBP_MANAGEMENT_CSRF';
    public const FIELD = '_fbp_csrf';

    private static function key(string $dataDir): string {
        return hash('sha256', realpath($dataDir) ?: $dataDir);
    }

    public static function token(string $dataDir): string {
        $key = self::key($dataDir);
        $token = $_SESSION[self::SESSION_KEY][$key] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY][$key] = $token;
        }
        return $token;
    }

    public static function forget(string $dataDir): void {
        unset($_SESSION[self::SESSION_KEY][self::key($dataDir)]);
    }

    /** Never generate/return a replacement token on a rejected request. */
    public static function valid(string $dataDir): bool {
        $expected = $_SESSION[self::SESSION_KEY][self::key($dataDir)] ?? null;
        $provided = $_SERVER['HTTP_X_FBP_CSRF_TOKEN'] ?? $_POST[self::FIELD] ?? null;
        return is_string($expected) && is_string($provided) && strlen($expected) === 64
            && hash_equals($expected, $provided);
    }

    public static function isManagement(Controller $ctl, string $class, string $function): bool {
        // Standard logout is hosted by the otherwise public login class.
        if (strtolower($class) === 'login' && strtolower($function) === 'logout') return true;
        return $ctl->get_dsp_channel() === 'admin' && $ctl->get_check_login();
    }

    public static function readOnlyRoute(string $class, string $function, object $app): bool {
        $class = strtolower($class);
        $function = strtolower($function);
        if ($class === 'login' && $function === 'logout') return false;
        // Custom GET functions retain their existing contract; this guard also
        // protects their POST operations. App-owned GET mutations need app changes.
        if (!method_exists($app, $function)) return true;
        $file = (new ReflectionMethod($app, $function))->getFileName();
        $framework = realpath(__DIR__ . '/../app') . DIRECTORY_SEPARATOR;
        if (!$file || !str_starts_with($file, $framework)) return true;
        if ($function === 'page') return true;
        $routes = [
            'base' => ['img'],
            'password_reset' => ['force_page'],
            'db_exe' => ['view_image', 'download_file'],
            'standard_screen_media' => ['view_image', 'download_file'],
            'upload' => ['pull', 'favicon'],
            'user' => ['image_sample'],
            // This flow uses its own expiring state and authenticated user binding.
            'project_support_login' => ['start', 'callback'],
        ];
        return in_array($function, $routes[$class] ?? [], true);
    }

    /** Check after route construction, before legacy init() and the action. */
    public static function enforce(Controller $ctl, string $class, string $function, object $app): void {
        if (PHP_SAPI === 'cli' || !self::isManagement($ctl, $class, $function)) return;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD'], true)) {
            if (!self::readOnlyRoute($class, $function, $app)) self::reject($ctl, 405);
            return;
        }
        if ($method === 'OPTIONS') self::reject($ctl, 405);
        if (!self::valid($ctl->dirs->datadir)) self::reject($ctl, 403);
        unset($_POST[self::FIELD]);
    }

    private static function reject(Controller $ctl, int $status): never {
        header('Cache-Control: no-store');
        header('X-FBP-CSRF-Error: 1');
        header('X-FBP-Download-Error: 1');
        $url = $ctl->get_APP_URL('login', 'page');
        header('X-FBP-Login-URL: '.$url);
        if (($_POST['_call_from'] ?? '') === 'appcon'
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || (isset($_POST['_download_mode']) && $_POST['_download_mode'] !== 'open_new_tab')) {
            http_response_code($status);
            if ($status === 405) header('Allow: POST');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['location'=>$url], JSON_UNESCAPED_UNICODE);
        } else {
            header('Location: '.$url, true, 303);
        }
        exit;
    }
}
