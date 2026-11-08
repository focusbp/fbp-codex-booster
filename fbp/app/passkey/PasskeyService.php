<?php
require_once __DIR__.'/PasskeyConfig.php';
require_once __DIR__.'/PasskeyRepository.php';
require_once __DIR__.'/vendor/lbuchs/src/WebAuthn.php';
final class PasskeyService {
    private $ctl;
    private ?int $verifiedKeyId = null;
    public $repo;
    public $config;
    public function __construct(Controller $ctl) {
        $this->ctl=$ctl;
        $this->config=PasskeyConfig::load($ctl);
        $this->repo=new PasskeyRepository($ctl);
    }
    public static function encode(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }
    public static function decode($s, int $max=16384): string {
        if (!is_string($s) || $s==='' || strlen($s)>ceil($max*4/3) || !preg_match('/^[A-Za-z0-9_-]+$/D',$s)) throw new RuntimeException('認証を確認できませんでした。');
        $raw=base64_decode(strtr($s,'-_','+/'),true);
        if ($raw===false || strlen($raw)>$max || self::encode($raw)!==$s) throw new RuntimeException('認証を確認できませんでした。');
        return $raw;
    }
    public function currentUser(): array {
        if (!$this->ctl->get_session('login')) throw new RuntimeException('ログインし直してください。');
        if ($this->ctl->get_session('passkey_authentication_method') === 'onetimecode'
            && (int)$this->ctl->get_session('onetimecode_session_expires') <= time()) throw new RuntimeException('ログインし直してください。');
        $u=$this->allowedUser((int)$this->ctl->get_session('user_id'));
        $i=$this->repo->identity((int)$u['id']);
        if (!hash_equals($i['epoch'],(string)$this->ctl->get_session('passkey_epoch'))) throw new RuntimeException('ログインし直してください。');
        return $u;
    }
    private function allowedUser(int $id): array {
        return $this->repo->user($id);
    }
    public function csrf(): string {
        $token=$this->ctl->get_session('passkey_csrf');
        if (!is_string($token) || strlen($token)!==43) {
            $token=self::encode(random_bytes(32)); $this->ctl->set_session('passkey_csrf',$token);
        }
        return $token;
    }
    public function checkPost(): void {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || ($_SERVER['HTTP_ORIGIN']??'')!==$this->config['origin'] ||
            !is_string($this->ctl->POST('csrf')) || !hash_equals($this->csrf(),$this->ctl->POST('csrf'))) throw new RuntimeException('画面を開き直してお試しください。');
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>65536) throw new RuntimeException('データが大きすぎます。');
    }
    public function throttle(string $kind): void {
        $ip=(string)($_SERVER['REMOTE_ADDR']??'');
        $this->repo->rate('ip:'.$kind.':'.$ip,60,300);
        $this->repo->rate('session:'.$kind.':'.session_id(),30,300);
    }
    public function confirm($password): array {
        $u=$this->currentUser();
        if ($this->ctl->get_session('passkey_authentication_method') === 'onetimecode' || $this->repo->passwordLoginDisabled((int)$u['id'])) throw new RuntimeException('パスキーで本人確認してください。');
        $this->repo->rate('confirm:'.$u['id'],5,900);
        if (!is_string($password) || $password==='' || strlen($password)>4096) throw new RuntimeException('パスワードを確認してください。');
        $stored=(string)$u['password'];
        $info=password_get_info($stored);
        $ok=($info['algo']??null) ? password_verify($password,$stored) : hash_equals($stored,$password);
        if (!$ok) throw new RuntimeException('パスワードを確認してください。');
        $this->repo->clearRate('confirm:'.$u['id']);
        $i=$this->repo->identity((int)$u['id']);
        $this->ctl->set_session('passkey_confirm',['user_id'=>(int)$u['id'],'epoch'=>$i['epoch'],'expires'=>time()+300,'kind'=>'password']);
        return $u;
    }
    private function confirmed(): array {
        $u=$this->currentUser(); $c=$this->ctl->get_session('passkey_confirm');
        $i=$this->repo->identity((int)$u['id']);
        if (!is_array($c) || ($c['expires']??0)<time() || ($c['user_id']??0)!==(int)$u['id'] || !hash_equals($i['epoch'],(string)($c['epoch']??''))) throw new RuntimeException('もう一度本人確認してください。');
        if (($this->ctl->get_session('passkey_authentication_method') === 'onetimecode' || $this->repo->passwordLoginDisabled((int)$u['id'])) && ($c['kind'] ?? '') !== 'passkey') throw new RuntimeException('パスキーで本人確認してください。');
        if (($c['kind'] ?? '') === 'passkey') {
            $key = $this->repo->keys->get((int)($c['key_id'] ?? 0));
            if (!is_array($key) || (int)($key['status'] ?? 1) !== 0 || (int)$key['parent_id'] !== (int)$u['id'] || $key['rp_id'] !== $this->config['rp_id'] || !hash_equals($i['user_handle'], $key['user_handle'])) throw new RuntimeException('もう一度パスキーで本人確認してください。');
        }
        return $u;
    }
    private function webauthn(): \lbuchs\WebAuthn\WebAuthn { return new \lbuchs\WebAuthn\WebAuthn($this->config['app_code'], $this->config['rp_id'], ['none'], true); }
    private function pending(string $purpose, string $challenge, ?array $u=null): void {
        $i=$u ? $this->repo->identity((int)$u['id']) : null;
        $this->ctl->set_session('passkey_pending',['purpose'=>$purpose,'challenge'=>self::encode($challenge),'expires'=>time()+180,'rp'=>$this->config['rp_id'],'origin'=>$this->config['origin'],'user_id'=>$u?(int)$u['id']:0,'handle'=>$i['user_handle']??'','epoch'=>$i['epoch']??'']);
    }
    private function consume(string $purpose): array {
        $p=$this->ctl->get_session('passkey_pending');
        $this->ctl->set_session('passkey_pending',null);
        if (!is_array($p) || ($p['purpose']??'')!==$purpose || ($p['expires']??0)<time() || ($p['rp']??'')!==$this->config['rp_id'] || ($p['origin']??'')!==$this->config['origin']) throw new RuntimeException('認証を確認できませんでした。もう一度お試しください。');
        return $p;
    }
    private function response($credential, string $type): array {
        if (!is_array($credential) || ($credential['type']??'')!=='public-key' || !is_array($credential['response']??null)) throw new RuntimeException('認証を確認できませんでした。');
        $raw=self::decode($credential['rawId']??null,1023);
        if (!hash_equals(self::encode($raw),(string)($credential['id']??''))) throw new RuntimeException('認証を確認できませんでした。');
        $r=$credential['response']; $client=self::decode($r['clientDataJSON']??null,4096);
        $data=json_decode($client,true,16,JSON_THROW_ON_ERROR);
        if (($data['origin']??'')!==$this->config['origin'] || ($data['type']??'')!==$type || ($data['crossOrigin']??false)!==false || isset($data['topOrigin'])) throw new RuntimeException('認証を確認できませんでした。');
        return [$raw,$r,$client];
    }
    public function registerOptions(bool $transfer=false): object {
        $u=$transfer?$this->transferUser():$this->confirmed(); $i=$this->repo->identity((int)$u['id']);
        $keys=$this->repo->active((int)$u['id'],$this->config['rp_id']);
        if(count($keys)>=10) throw new RuntimeException('このサイトには10件まで登録できます。');
        $w=$this->webauthn();
        $exclude=array_map(static fn($k)=>self::decode($k['credential_id'],1023),$keys);
        $label=$this->keyLabel($u);
        $a=$w->getCreateArgs(self::decode($i['user_handle'],32),$label,$label,120,true,true,null,$exclude);
        $a->publicKey->pubKeyCredParams=[(object)['type'=>'public-key','alg'=>-7]];
        $this->pending($transfer?'transfer-register':'register',$w->getChallenge()->getBinaryString(),$u);
        return $a->publicKey;
    }
    public function register($credential,bool $transfer=false): array {
        $p=$this->consume($transfer?'transfer-register':'register'); $u=$transfer?$this->transferUser():$this->confirmed(); $i=$this->repo->identity((int)$u['id']);
        if ($p['user_id']!==(int)$u['id'] || !hash_equals($i['user_handle'],$p['handle']) || !hash_equals($i['epoch'],$p['epoch'])) throw new RuntimeException('認証を確認できませんでした。');
        [$raw,$r,$client]=$this->response($credential,'webauthn.create');
        $w=$this->webauthn();
        $result=$w->processCreate($client,self::decode($r['attestationObject']??null,16384),self::decode($p['challenge'],32),true,true);
        if(!hash_equals($raw,$result->credentialId) || ($result->isBackedUp && !$result->isBackupEligible)) throw new RuntimeException('認証を確認できませんでした。');
        $detail=openssl_pkey_get_details(openssl_pkey_get_public($result->credentialPublicKey));
        if (!$detail || $detail['type']!==OPENSSL_KEYTYPE_EC || ($detail['ec']['curve_name']??'')!=='prime256v1') throw new RuntimeException('この鍵の形式には対応していません。');
        $existing=$this->repo->keys->select(['rp_id','credential_id_hash'],[$this->config['rp_id'],hash('sha256',$raw)],true);
        if ($existing || count($this->repo->active((int)$u['id'],$this->config['rp_id']))>=10) throw new RuntimeException('登録済みの鍵、または登録上限です。');
        $transports=array_values(array_intersect(is_array($r['transports']??null)?$r['transports']:[],['usb','nfc','ble','hybrid','internal']));
        $row=['parent_id'=>$u['id'],'user_handle'=>$i['user_handle'],'rp_id'=>$this->config['rp_id'],'credential_id'=>self::encode($raw),'credential_id_hash'=>hash('sha256',$raw),'public_key'=>$result->credentialPublicKey,'algorithm'=>-7,'sign_count'=>$result->signatureCounter??0,'transports'=>json_encode($transports),'aaguid'=>bin2hex($result->AAGUID),'backup_eligible'=>(int)$result->isBackupEligible,'backup_state'=>(int)$result->isBackedUp,'label'=>$this->config['app_code'],'created_at'=>date('Y-m-d H:i:s'),'last_used_at'=>'','revoked_at'=>'','status'=>0];
        $row['label']=$this->keyLabel($u);
        if ($transfer) {
            // Consume before insertion: a partial write fails closed rather than permitting replay.
            $grant=$this->ctl->get_session('passkey_device_grant');$ticket=$this->repo->transfers->get($grant['id']);
            $ticket['status']=1;$this->repo->transfers->update($ticket);
            $this->ctl->set_session('passkey_device_grant',null);
        }
        $this->repo->keys->insert($row);
        $this->ctl->set_session('passkey_confirm',null);
        return ['user_id'=>(int)$u['id'],'email'=>(string)($u['email']??''),'label'=>$row['label'],'rp'=>$row['rp_id'],'created_at'=>$row['created_at']];
    }
    public function keyLabel(array $user): string {
        return $this->config['app_code'].' <'.(string)$user['login_id'].'>';
    }
    public function issueTransfer(): array {
        $u=$this->currentUser();$id=(int)$u['id'];$i=$this->repo->identity($id);
        if(!filter_var($u['email']??'',FILTER_VALIDATE_EMAIL))throw new RuntimeException('登録メールアドレスを設定してからお試しください。');
        $this->repo->rate('device-issue:'.$id,5,900);
        foreach($this->repo->transfers->getall() as $old) {
            if ((int)$old['expires']<time())$this->repo->transfers->delete($old['id']);
            elseif((int)$old['user_id']===$id&&$old['rp_id']===$this->config['rp_id']){$old['status']=1;$this->repo->transfers->update($old);}
        }
        $token=self::encode(random_bytes(32));$pin=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$expires=time()+86400;
        $row=['user_id'=>$id,'token_hash'=>hash('sha256',$token),'pin_hash'=>hash_hmac('sha256',$pin,$token),'claim_hash'=>'','epoch'=>$i['epoch'],'user_handle'=>$i['user_handle'],'rp_id'=>$this->config['rp_id'],'expires'=>$expires,'attempts'=>0,'status'=>0];
        $this->repo->transfers->insert($row);
        return ['url'=>$this->ctl->get_APP_URL('passkey','transfer').'#'.$token,'pin'=>$pin,'expires'=>$expires,'email'=>$u['email'],'label'=>$this->keyLabel($u)];
    }
    public function claimTransfer(string $token,string $pin): void {
        $error='URLまたは確認コードが無効か、有効期限が切れています。元の端末で発行し直してください。';
        if(!preg_match('/^[A-Za-z0-9_-]{43}$/D',$token))throw new RuntimeException($error);
        $rows=$this->repo->transfers->select('token_hash',hash('sha256',$token),true);
        if(count($rows)!==1)throw new RuntimeException($error);$row=array_values($rows)[0];
        if((int)$row['status']!==0||(int)$row['expires']<=time()||(int)$row['attempts']>=5||$row['claim_hash']!==''||$row['rp_id']!==$this->config['rp_id'])throw new RuntimeException($error);
        $row['attempts']=(int)$row['attempts']+1;$this->repo->transfers->update($row);
        if(!preg_match('/^[0-9]{4}$/D',$pin)||!hash_equals($row['pin_hash'],hash_hmac('sha256',$pin,$token)))throw new RuntimeException($error);
        $u=$this->repo->user((int)$row['user_id']);$i=$this->repo->identity((int)$u['id']);
        if(!hash_equals($row['epoch'],$i['epoch'])||!hash_equals($row['user_handle'],$i['user_handle']))throw new RuntimeException($error);
        $proof=self::encode(random_bytes(32));$row['claim_hash']=hash('sha256',$proof);$this->repo->transfers->update($row);
        $this->ctl->set_session('passkey_device_grant',['id'=>(int)$row['id'],'proof'=>$proof]);
    }
    private function transferUser(): array {
        $g=$this->ctl->get_session('passkey_device_grant');$row=is_array($g)?$this->repo->transfers->get((int)($g['id']??0)):null;
        if(!is_array($row)||(int)$row['status']!==0||(int)$row['expires']<=time()||$row['rp_id']!==$this->config['rp_id']||$row['claim_hash']===''||!hash_equals($row['claim_hash'],hash('sha256',(string)($g['proof']??''))))throw new RuntimeException('登録の有効期限が切れました。元の端末で発行し直してください。');
        $u=$this->repo->user((int)$row['user_id']);$i=$this->repo->identity((int)$u['id']);
        if(!hash_equals($row['epoch'],$i['epoch'])||!hash_equals($row['user_handle'],$i['user_handle']))throw new RuntimeException('ログイン状態が変更されました。発行し直してください。');
        return $u;
    }
    public function transferRegistration(): array {
        $u=$this->transferUser();$grant=$this->ctl->get_session('passkey_device_grant');
        $row=$this->repo->transfers->get((int)$grant['id']);
        return ['label'=>$this->keyLabel($u),'expires'=>(int)$row['expires']];
    }
    public function loginOptions(): object {
        $w=$this->webauthn(); $a=$w->getGetArgs([],120,true,true,true,true,true,true);
        $this->pending('login',$w->getChallenge()->getBinaryString());
        return $a->publicKey;
    }
    public function authenticate($credential, string $purpose = 'login'): array {
        if (!in_array($purpose, ['login','confirm'], true)) throw new RuntimeException('操作を確認してください。');
        $p=$this->consume($purpose); [$raw,$r,$client]=$this->response($credential,'webauthn.get');
        $key=$this->repo->credential($this->config['rp_id'],$raw);
        $u=$this->allowedUser((int)$key['parent_id']); $i=$this->repo->identity((int)$u['id']);
        if ($purpose === 'confirm') {
            $current = $this->currentUser();
            if ((int)$current['id'] !== (int)$u['id'] || (int)$p['user_id'] !== (int)$u['id'] || !hash_equals($i['epoch'], $p['epoch']) || !hash_equals($i['user_handle'], $p['handle'])) throw new RuntimeException('本人のパスキーで認証してください。');
        }
        $handle=self::encode(self::decode($r['userHandle']??null,64));
        if (!hash_equals($i['user_handle'],$handle) || !hash_equals($key['user_handle'],$handle)) throw new RuntimeException('認証を確認できませんでした。');
        $auth=self::decode($r['authenticatorData']??null,4096);
        $w=$this->webauthn();
        $w->processGet($client,$auth,self::decode($r['signature']??null,1024),$key['public_key'],self::decode($p['challenge'],32),(int)$key['sign_count'],true,true);
        $ad=new \lbuchs\WebAuthn\Attestation\AuthenticatorData($auth);
        if ((int)$ad->getIsBackupEligible()!==(int)$key['backup_eligible'] || ($ad->getIsBackup() && !$ad->getIsBackupEligible())) throw new RuntimeException('認証を確認できませんでした。');
        $key['sign_count']=$ad->getSignCount(); $key['backup_state']=(int)$ad->getIsBackup(); $key['last_used_at']=date('Y-m-d H:i:s');
        $this->repo->keys->update($key);
        $this->verifiedKeyId = (int)$key['id'];
        $this->ctl->set_session('passkey_confirm',null);
        return $u;
    }
    public function manage(int $id, string $action): void {
        $u=$this->confirmed(); $key=$this->repo->keys->get($id);
        if (!is_array($key) || (int)($key['parent_id']??0)!==(int)$u['id']) throw new RuntimeException('操作できませんでした。');
        if ($action === 'revoke' && $this->repo->passwordLoginDisabled((int)$u['id']) && (int)$key['status'] === 0) {
            $identity = $this->repo->identity((int)$u['id']);
            $remaining = array_filter($this->repo->active((int)$u['id'], $key['rp_id']), static fn($r) => hash_equals($identity['user_handle'], $r['user_handle']));
            if (count($remaining) <= 1) throw new RuntimeException('ID/Passwordログインの停止中は、登録先の最後のパスキーを削除できません。先に別のパスキーを追加してください。');
        }
        if ($action==='revoke') { $key['status']=1; $key['revoked_at']=date('Y-m-d H:i:s'); }
        else throw new RuntimeException('操作を確認してください。');
        $this->repo->keys->update($key);
        $this->ctl->set_session('passkey_confirm',null);
    }
    public function confirmationOptions(): object {
        $u=$this->currentUser();
        $w=$this->webauthn();
        $options=$w->getGetArgs([],120,true,true,true,true,true,true);
        $this->pending('confirm',$w->getChallenge()->getBinaryString(),$u);
        return $options->publicKey;
    }
    public function rememberVerifiedPasskey(array $u): void {
        if ($this->verifiedKeyId === null) throw new RuntimeException('パスキー認証が必要です。');
        $identity=$this->repo->identity((int)$u['id']);
        $this->ctl->set_session('passkey_confirm', ['kind'=>'passkey','key_id'=>$this->verifiedKeyId,'user_id'=>(int)$u['id'],'epoch'=>$identity['epoch'],'expires'=>time()+300]);
    }
    public function confirmPasskey($credential): void {
        $u=$this->authenticate($credential,'confirm');
        $this->rememberVerifiedPasskey($u);
    }
    public function passwordChoicePending(): bool {
        try {
            $u=$this->confirmed(); $proof=$this->ctl->get_session('passkey_confirm');
            $choice=$this->ctl->get_session('passkey_password_choice');
            return !$this->repo->passwordLoginDisabled((int)$u['id']) && ($proof['kind']??'')==='passkey' && is_array($choice) && is_string($choice['next']??null) && ($choice['expires']??0)>=time();
        } catch (RuntimeException $e) { return false; }
    }
    public function choosePasswordLogin(string $choice): string {
        if (!in_array($choice,['disable','continue'],true) || !$this->passwordChoicePending()) throw new RuntimeException('もう一度パスキーでログインしてください。');
        $u=$this->currentUser();
        $next=$this->ctl->get_session('passkey_password_choice')['next'];
        if ($choice==='disable') {
            $identity=$this->repo->identity((int)$u['id']);
            $identity['password_login_disabled']=1;
            $identity['epoch']=self::encode(random_bytes(32));
            $this->repo->identities->update($identity);
            foreach (['password_reset_token_hash','password_reset_token_expires_at','password_reset_token_sent_at'] as $field) $u[$field]=str_ends_with($field,'hash')?'':0;
            $this->repo->users->update($u);
            $this->ctl->set_session('passkey_epoch',$identity['epoch']);
            $proof=$this->ctl->get_session('passkey_confirm'); $proof['epoch']=$identity['epoch'];
            $this->ctl->set_session('passkey_confirm',$proof);
            $window=$this->ctl->get_windowcode(); $app=$this->ctl->get_session('appcode');
            foreach ($_SESSION as $id=>$state) {
                if ($id!==$window && is_array($state) && ($state['appcode']??null)===$app && (int)($state['user_id']??0)===(int)$u['id']) {
                    foreach (['login','app_admin','user_id','login_id','password','passkey_epoch','passkey_confirm','passkey_pending','passkey_password_choice','passkey_device_grant','passkey_authentication_method','passkey_csrf','_DISPLAY','__AUTO_LOAD_MAIN_AREA'] as $field) unset($_SESSION[$id][$field]);
                }
            }
            self::cookie('remember_me','',time()-3600);
            self::cookie('login_status','',time()-3600);
            self::renewSession();
        }
        $this->ctl->set_session('passkey_password_choice',null);
        error_log('passkey: password_login_choice='.$choice.' user='.(int)$u['id']);
        return (string)$next;
    }
    public static function cookie(string $name, string $value, int $expires): bool {
        return setcookie($name,$value,['expires'=>$expires,'path'=>cookie_path(),'secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    }
    public static function renewSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        if (!session_regenerate_id(true)) throw new RuntimeException('セッションを更新できませんでした。');
        $params=session_get_cookie_params();
        setcookie(session_name(),session_id(),['expires'=>0,'path'=>$params['path']?:'/','domain'=>$params['domain'],'secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    }
    public static function clearAuth(Controller $ctl): void {
        $appcode=$ctl->get_session('appcode');
        foreach ($_SESSION as $window=>$state) {
            if (is_array($state) && (($state['appcode']??null)===$appcode || $window===$ctl->get_windowcode())) {
                foreach (['login','app_admin','user_id','login_id','password','passkey_epoch','passkey_confirm','passkey_pending','passkey_csrf','passkey_authentication_method','passkey_device_grant','passkey_password_choice','onetimecode_session_expires','onetimecode_form','_DISPLAY','__AUTO_LOAD_MAIN_AREA'] as $key) unset($_SESSION[$window][$key]);
            }
        }
        foreach (['login_id','password','remember_me','login_status'] as $name) self::cookie($name,'',time()-3600);
        self::renewSession();
    }
}
