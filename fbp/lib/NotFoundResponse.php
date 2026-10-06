<?php

/** A terminal 404 response; custom HTML is limited to browser page requests. */
final class NotFoundResponse {
    private static $rendering = false;

    public static function acceptsHtml(array $server, array $post, string $requestedClass): bool {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $accept = strtolower((string) ($server['HTTP_ACCEPT'] ?? ''));
        return in_array($method, ['GET', 'HEAD'], true)
            && ($post['_call_from'] ?? '') !== 'appcon'
            && strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest'
            && strpos($accept, 'application/json') === false
            && !preg_match('/(?:^api$|_api$|^mcp_)/i', $requestedClass);
    }

    public static function body(array $setting, callable $render, bool $allowHtml): array {
        if (self::$rendering) {
            throw new RuntimeException('Recursive 404 handler');
        }
        $class = trim((string) ($setting['not_found_class'] ?? ''));
        $function = trim((string) ($setting['not_found_function'] ?? ''));
        $fallback = ['text/plain; charset=UTF-8', 'Not Found'];
        if (!$allowHtml || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $class)
            || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $function)) {
            return $fallback;
        }
        self::$rendering = true;
        $level = ob_get_level();
        ob_start();
        try {
            $render($class, $function);
            $html = ob_get_clean();
            return trim($html) === '' ? $fallback : ['text/html; charset=UTF-8', $html];
        } catch (Throwable $e) {
            return $fallback;
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            self::$rendering = false;
        }
    }

    public static function send(array $setting, callable $render, string $requestedClass): void {
        [$type, $body] = self::body($setting, $render, self::acceptsHtml($_SERVER, $_POST, $requestedClass));
        header_remove('Location');
        header_remove('Content-Length');
        header_remove('Content-Disposition');
        http_response_code(404);
        header('Content-Type: ' . $type);
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $body;
        }
        exit;
    }
}
