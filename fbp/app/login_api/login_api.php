<?php
require_once __DIR__.'/../login/OnetimecodeService.php';

class login_api {
    function __construct(Controller $ctl) { $ctl->set_check_login(false); }
    function issue_onetimecode(Controller $ctl) {
        header('Cache-Control: no-store');
        header('Content-Type: application/json; charset=utf-8');
        header('Referrer-Policy: no-referrer');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405); header('Allow: POST');
            echo json_encode(['ok'=>false, 'error_code'=>'post_required']); exit;
        }
        if ($ctl->verify_api_request() !== true) exit;
        try {
            // HMAC covers the query, not the JSON body. Never accept the user ID from POST.
            $userId = $ctl->GET('user_id');
            $requestId = $ctl->GET('request_id');
            if (!is_string($userId) || !preg_match('/^[1-9][0-9]{0,8}$/D', $userId)
                || !is_string($requestId)) throw new RuntimeException('Invalid request.');
            $result = (new OnetimecodeService($ctl))->issue((int)$userId, $requestId);
            echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['ok'=>false, 'error_code'=>'onetimecode_issue_denied']);
        }
        exit;
    }
}
