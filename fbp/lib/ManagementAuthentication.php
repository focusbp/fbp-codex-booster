<?php
require_once __DIR__.'/DspException.php';

/** A framework authentication boundary failure, distinct from policy refusals. */
final class ManagementAuthenticationRequired extends DspException {
    public function __construct() { parent::__construct('admin_session', 'authentication_required'); }
}

/** Shared response for an absent/expired management session. Does not grant access. */
final class ManagementAuthentication {
    public static function respond(Controller $ctl): void {
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: '.$ctl->get_APP_URL('login', 'page'));
            return;
        }
        header('Content-Type: application/json; charset=UTF-8');
        if (($_POST['class'] ?? '') === 'lang') {
            echo json_encode([]);
            return;
        }
        fbp_store_pending_auto_load_main_area($ctl);
        echo json_encode(['location'=>$ctl->get_APP_URL('login', 'page')]);
    }
}
