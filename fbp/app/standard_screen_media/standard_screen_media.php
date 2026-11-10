<?php
require_once __DIR__ . '/../../lib/StandardScreenMedia.php';

/** Separate management route. Legacy/public media endpoints are unchanged. */
class standard_screen_media {
    public function __construct(Controller $ctl) {
        $ctl->set_check_login(true);
        $ctl->set_db_read_only(true);
        // Binary requests must not redirect to HTML when authentication expires.
        if (!$ctl->verify_management_session()) self::deny(401);
    }

    public function view_image(Controller $ctl): void { $this->send($ctl, true); }
    public function download_file(Controller $ctl): void { $this->send($ctl, false); }

    private function send(Controller $ctl, bool $image): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, $image ? ['GET', 'HEAD'] : ['GET', 'HEAD', 'POST'], true)) {
            header('Allow: ' . ($image ? 'GET, HEAD' : 'GET, HEAD, POST'));
            self::deny(405);
        }
        try {
            // Download-link sends POST to a URL containing target identifiers.
            // URL identifiers take precedence over unrelated surrounding form values.
            $request = $method === 'POST' ? array_replace($ctl->POST(), $ctl->GET()) : $ctl->GET();
            $file = StandardScreenMedia::resolve($ctl, $request, $image);
        } catch (StandardScreenMediaDenied $error) {
            self::deny($error->status);
        } catch (DspException $error) {
            self::deny(404);
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        if ($image) $ctl->res_saved_media($file['path'], ['cache' => false]);
        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }
        $ctl->res_saved_file($file['path'], $file['filename']);
        exit;
    }

    private static function deny(int $status): void {
        http_response_code($status);
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/plain; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $status === 401 ? 'Unauthorized' : 'Attachment unavailable';
        exit;
    }
}
