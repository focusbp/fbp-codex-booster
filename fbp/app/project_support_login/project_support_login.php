<?php
require_once __DIR__.'/../../lib/ProjectSupportSsoClient.php';
require_once __DIR__.'/../../lib/SupportSsoLoginReturn.php';
class project_support_login {
    function __construct(Controller $ctl){$ctl->set_check_login(false);$ctl->set_dsp_channel('admin');}
    private function admin(Controller $ctl): array {
        if(!$ctl->verify_management_session()||!$ctl->is_app_admin()||!$ctl->authorize_management_access('panel','page'))throw new DomainException('管理者としてログインしてから開いてください。');
        $u=$ctl->db('user','user')->get((int)$ctl->get_login_user_id());
        if(!$u||(int)$u['status']!==0||(int)$u['type']!==0||(string)$u['login_id']!==(string)$ctl->get_session('login_id'))throw new DomainException('管理者としてログインし直してください。');
        return $u;
    }
    private function fail(Throwable $e): never {http_response_code(403);header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><meta charset="utf-8"><p>'.htmlspecialchars($e instanceof DomainException?$e->getMessage():'サポートを開けません。管理メニューから再試行してください。',ENT_QUOTES,'UTF-8').'</p>';exit;}
    function start(Controller $ctl) {
        header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
        try {
            $parameters=SupportSsoLoginReturn::parameters(['project_code'=>$ctl->GET('project_code')??'','request'=>$ctl->GET('request')??'']);
            if(!$ctl->verify_management_session()){
                SupportSsoLoginReturn::remember($ctl,$parameters);
                $ctl->res_redirect($ctl->get_APP_URL('login','page'));return;
            }
            $u=$this->admin($ctl);$state=bin2hex(random_bytes(32));$client=new ProjectSupportSsoClient($ctl);
            $callback=rtrim($ctl->get_APP_URL(),'/').'/fbp/app.php?class=project_support_login&function=callback';
            while(count($_SESSION['support_sso_starts']??[])>=20)array_shift($_SESSION['support_sso_starts']);
            $_SESSION['support_sso_starts'][$state]=['user_id'=>(int)$u['id'],'epoch'=>(string)$ctl->get_session('passkey_epoch'),'expires'=>time()+180];
            foreach($_SESSION['support_sso_starts'] as $k=>$v)if($v['expires']<time())unset($_SESSION['support_sso_starts'][$k]);
            $request=['action'=>'begin','callback'=>$callback,'state'=>$state];
            if(isset($parameters['project_code']))$request['project_code']=$parameters['project_code'];
            $link=$parameters['request']??'';
            if($link!=='')$request['project_code']=$client->requestProject($link,(int)$u['id']);
            $r=$client->request($request);
            if(!preg_match('/^[a-f0-9]{64}$/D',(string)($r['ticket']??'')))throw new DomainException('認証応答を確認できませんでした。');
            header('Location: '.$client->endpoint().'?'.http_build_query(['class'=>'project_support_sso','function'=>'prepare','ticket'=>$r['ticket']]),true,303);exit;
        }catch(Throwable $e){$this->fail($e);}
    }
    function callback(Controller $ctl) {
        header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
        try {
            $u=$this->admin($ctl);$state=(string)$ctl->GET('state');$pending=$_SESSION['support_sso_starts'][$state]??null;unset($_SESSION['support_sso_starts'][$state]);
            if(!$pending||$pending['expires']<time()||$pending['user_id']!==(int)$u['id']||!hash_equals($pending['epoch'],(string)$ctl->get_session('passkey_epoch')))throw new DomainException('認証の有効期限が切れました。管理メニューから開き直してください。');
            $client=new ProjectSupportSsoClient($ctl);$r=$client->request(['action'=>'issue','ticket'=>(string)$ctl->GET('ticket'),'state'=>$state]);
            $code=(string)($r['code']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$code))throw new DomainException('認証応答を確認できませんでした。');
            $action=$client->endpoint().'?class=project_support_sso&function=consume';
            header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><meta charset="utf-8"><form id="support-jump" method="post" action="'.htmlspecialchars($action,ENT_QUOTES).'">'.'<input type="hidden" name="code" value="'.htmlspecialchars($code,ENT_QUOTES).'"><noscript><button>プロジェクトサポートを開く</button></noscript></form><script>document.getElementById("support-jump").submit();</script>';exit;
        }catch(Throwable $e){$this->fail($e);}
    }
}
