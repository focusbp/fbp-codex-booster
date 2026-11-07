<?php
require_once __DIR__.'/PasskeyService.php';
class passkey {
    function __construct(Controller $ctl) {
        $ctl->set_check_login(false);
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
    }
    function emergency_cli_recovery(Controller $ctl) {
        if (PHP_SAPI !== 'cli' || !defined('PASSKEY_ADMIN_RECOVERY_CONFIRMED') || PASSKEY_ADMIN_RECOVERY_CONFIRMED !== true) {
            http_response_code(403);
            throw new RuntimeException('CLI only.');
        }
        $repo=new PasskeyRepository($ctl);
        $repo->recoverAdmin(PASSKEY_ADMIN_RECOVERY_USER_ID);
        define('PASSKEY_ADMIN_RECOVERY_COMPLETED',true);
        echo "管理者パスキーをリセットしました。現在のID／Passwordでログインし、パスキーを再登録してください。\n";
    }
    function page(Controller $ctl,bool $transfer=false) {
        try {
            $s=new PasskeyService($ctl);
            $logged=!$transfer&&$ctl->GET('view')!=='login'&&(bool)$ctl->get_session('login');
            $registration=null;
            if($transfer&&$ctl->get_session('passkey_device_grant')) {
                try {$registration=$s->transferRegistration();}catch(RuntimeException $e){$ctl->set_session('passkey_device_grant',null);}
            }
            $ctl->assign('passkey_transfer',$transfer);
            $ctl->assign('passkey_login_url',$ctl->get_APP_URL('passkey','page',['view'=>'login']));
            $ctl->assign('passkey_transfer_expires',$registration['expires']??0);
            $keys=[];
            if ($logged) {
                $u=$s->currentUser();
                if ($ctl->GET('view') === 'password-login' && !$s->passwordChoicePending()) {
                    $ctl->res_redirect($ctl->get_APP_URL('base','page'));
                    return;
                }
                if ($ctl->GET('view') === 'choice') {
                    $identity = $s->repo->identity((int)$u['id']);
                    $registered = array_filter($s->repo->active((int)$u['id'], $s->config['rp_id']),
                        static fn($key) => hash_equals($identity['user_handle'], (string)$key['user_handle']));
                    if ($registered) {
                        $ctl->res_redirect($ctl->get_APP_URL('base', 'page'));
                        return;
                    }
                }
                foreach ($s->repo->owned((int)$u['id']) as $key) {
                    $keys[]=['id'=>$key['id'],'label'=>$s->keyLabel($u),'rp_id'=>$key['rp_id'],'current'=>$key['rp_id']===$s->config['rp_id'],'status'=>(int)$key['status'],'created_at'=>$key['created_at'],'last_used_at'=>$key['last_used_at']];
                }
            }
            $ctl->assign('pk', ['password_disabled'=>$logged && $s->repo->passwordLoginDisabled((int)$u['id']),'password_choice'=>$logged && $ctl->GET('view')==='password-login','app_code'=>$s->config['app_code'],'choice'=>$logged && $ctl->GET('view')==='choice','manage'=>$ctl->GET('view')==='manage','manage_url'=>$ctl->get_APP_URL('passkey','page',['view'=>'manage']),'register_url'=>$ctl->get_APP_URL('passkey','page'),'logged'=>$logged,'keys'=>$keys,'rp'=>$s->config['rp_id'],'csrf'=>$s->csrf(),'window'=>$ctl->get_windowcode(),'cookie_path'=>parse_url($ctl->get_APP_URL(),PHP_URL_PATH),'endpoint'=>$ctl->get_APP_URL('passkey','api'),'base'=>$ctl->get_APP_URL('base','page'),'login'=>$ctl->get_APP_URL('login','page')]);
            $ctl->assign('passkey_registration_label',$registration['label']??($logged?$s->keyLabel($u):$s->config['app_code']));
            $ctl->display('page.tpl');
        } catch(Throwable $e) {
            http_response_code(403);
            echo '<!doctype html><meta charset="utf-8"><p>パスキー画面を開けませんでした。通常のログインから入り直してください。</p>';
        }
    }
    function api(Controller $ctl) {
        $action=(string)$ctl->POST('action');
        try {
            $s=new PasskeyService($ctl); $s->checkPost();
            if (in_array($action,['login_options','login_verify','register_options','register_verify','confirm_options','confirm_verify','transfer_claim','transfer_register_options','transfer_register_verify'],true)) $s->throttle($action);
            switch ($action) {
                case 'transfer_issue':
                    $result=$s->issueTransfer();
                    require_once __DIR__.'/PasskeyRegistrationMail.php';
                    PasskeyRegistrationMail::sendTransfer($ctl,$result);
                    unset($result['pin'],$result['email'],$result['label']);break;
                case 'transfer_claim': $s->claimTransfer((string)$ctl->POST('token'),(string)$ctl->POST('pin'));$result=$s->transferRegistration();break;
                case 'transfer_register_options': $result=['options'=>$s->registerOptions(true)];break;
                case 'transfer_register_verify':
                    $registration=$s->register(json_decode((string)$ctl->POST('credential'),true,32,JSON_THROW_ON_ERROR),true);
                    $result=['registered'=>true,'notification'=>$this->notifyRegistration($ctl,$registration)];break;
                case 'confirm_options': $result=['options'=>$s->confirmationOptions()]; break;
                case 'confirm_verify':
                    $s->confirmPasskey(json_decode((string)$ctl->POST('credential'),true,32,JSON_THROW_ON_ERROR)); $result=[]; break;
                case 'password_login_choice': $result=['redirect'=>$s->choosePasswordLogin((string)$ctl->POST('choice'))]; break;
                case 'confirm': $s->confirm($ctl->POST('password')); $result=[]; break;
                case 'register_options': $result=['options'=>$s->registerOptions()]; break;
                case 'register_verify':
                    $registration=$s->register(json_decode((string)$ctl->POST('credential'),true,32,JSON_THROW_ON_ERROR));
                    $result=['registered'=>true,'notification'=>$this->notifyRegistration($ctl,$registration)]; break;
                case 'login_options': $result=['options'=>$s->loginOptions()]; break;
                case 'login_verify':
                    $user=$s->authenticate(json_decode((string)$ctl->POST('credential'),true,32,JSON_THROW_ON_ERROR));
                    // Keep only trusted OAuth return state; renew the PHP session before login.
                    PasskeyService::clearAuth($ctl);
                    require_once $ctl->dirs->get_class_dir('login').'/login.php';
                    $_POST['_call_from']='appcon';
                    $login=new login($ctl); $login->login_ok($ctl,$user,(string)$user['login_id'],'','passkey');
                    ob_start(); $ctl->res(); $response=json_decode(ob_get_clean(),true);
                    if (!$ctl->get_session('login') || empty($response['location'])) throw new RuntimeException('通常のログインからお試しください。');
                    $s->rememberVerifiedPasskey($user);
                    if (!$s->repo->passwordLoginDisabled((int)$user['id'])) {
                        $ctl->set_session('passkey_password_choice',['expires'=>time()+300,'next'=>$response['location']]);
                        $result=['redirect'=>$ctl->get_APP_URL('passkey','page',['view'=>'password-login'])];
                    } else {
                        $result=['redirect'=>$response['location']];
                    }
                    break;
                case 'revoke':
                    $s->manage((int)$ctl->POST('id'),$action); $result=[]; break;
                case 'logout':
                    $s->currentUser(); PasskeyService::clearAuth($ctl);
                    $result=['redirect'=>$ctl->get_APP_URL('passkey','page')]; break;
                default: throw new RuntimeException('操作を確認してください。');
            }
            error_log('passkey: success action='.$action.' user='.(int)$ctl->get_session('user_id'));
            self::json(['ok'=>true]+$result);
        } catch(Throwable $e) {
            // Do not log credentials, clientData, signatures or raw POST bodies.
            error_log('passkey: operation_failed action='.(in_array($action,['confirm','register_options','register_verify','login_options','login_verify','confirm_options','confirm_verify','password_login_choice','rename','revoke','logout'],true)?$action:'unknown'));
            $message=($e instanceof RuntimeException && !($e instanceof \lbuchs\WebAuthn\WebAuthnException))?$e->getMessage():'認証を確認できませんでした。もう一度お試しください。';
            self::json(['ok'=>false,'message'=>$message],400);
        }
    }
    private function notifyRegistration(Controller $ctl,array $registration): string {
        require_once __DIR__.'/PasskeyRegistrationMail.php';
        return PasskeyRegistrationMail::send($ctl,$registration);
    }
    function transfer(Controller $ctl) {
        header('Referrer-Policy: no-referrer');header("Cache-Control: no-store");
        $this->page($ctl,true);
    }
    function device_issue(Controller $ctl) {
        $s=new PasskeyService($ctl);$s->currentUser();
        $ctl->assign('share_url','');
        $ctl->assign('share_url_help','確認コードを登録メールアドレスへ送信しました。');
        $ctl->assign('share_url_open_label','パスキー登録画面を開く');
        $ctl->assign('device_endpoint',$ctl->get_APP_URL('passkey','api'));
        $ctl->assign('device_csrf',$s->csrf());$ctl->assign('device_window',$ctl->get_windowcode());
        $ctl->show_multi_dialog('passkey-device','device_issue.tpl','他の端末にパスキーを設定する',650);
    }
    private static function json(array $data,int $status=200): void {
        http_response_code($status); header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    }
}
