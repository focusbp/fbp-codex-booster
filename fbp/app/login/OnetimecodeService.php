<?php
require_once __DIR__.'/../passkey/PasskeyService.php';

/** API-authorized login capability; raw codes never enter persistent storage. */
final class OnetimecodeService {
    public const CODE_TTL = 60;
    public const SESSION_TTL = 1800;
    private PasskeyRepository $repo;
    private $codes;
    private array $config;
    private static ?array $grant = null;

    public function __construct(private Controller $ctl) {
        self::requireStandardLogin($ctl);
        $this->config = PasskeyConfig::load($ctl);
        // No further DB opens after reading identity/code/user for authentication.
        $this->repo = new PasskeyRepository($ctl);
        $this->codes = $ctl->db('onetimecode', 'login');
        $this->codes->set_strict_field_length(true);
    }

    public static function requireStandardLogin(Controller $ctl): void {
        foreach (['login','user','passkey','password_reset'] as $class) {
            if (realpath($ctl->dirs->get_class_dir($class)) !== realpath($ctl->dirs->appdir_fw.'/'.$class)) {
                throw new RuntimeException('Standard authentication required.');
            }
        }
        foreach (['login_two_factor_guard','app_management_session_guard','app_management_access_guard'] as $guard) {
            if (is_file($ctl->dirs->appdir_user.'/'.$guard.'/'.$guard.'.php')) {
                throw new RuntimeException('Application authentication guard requires a dedicated integration.');
            }
        }
    }

    public function issue(int $userId, string $requestId): array {
        $subject = $this->ctl->get_dsp_system_subject();
        if ($this->ctl->get_dsp_channel() !== 'api' || $this->ctl->get_classname() !== 'login_api'
            || $this->ctl->get_called_function() !== 'issue_onetimecode'
            || ($subject['verified'] ?? false) !== true || ($subject['kind'] ?? '') !== 'api') {
            throw new RuntimeException('Authenticated API required.');
        }
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId)) throw new RuntimeException('Invalid request ID.');
        $requestHash = hash('sha256', $requestId);
        if ($this->codes->select('request_hash', $requestHash, true)) throw new RuntimeException('Request already used.');
        $this->repo->rate('onetimecode:issue', 30, 60);
        $user = $this->repo->user($userId);
        $identity = $this->repo->identity($userId);
        foreach ($this->codes->getall() as $old) {
            if ((int)$old['created_at'] < time()-600) $this->codes->delete($old['id']);
        }
        $code = PasskeyService::encode(random_bytes(32));
        $row = ['user_id'=>$userId, 'code_hash'=>hash('sha256', $code), 'request_hash'=>$requestHash,
            'origin'=>$this->config['origin'], 'epoch'=>$identity['epoch'], 'user_handle'=>$identity['user_handle'],
            'created_at'=>time(), 'expires_at'=>time()+self::CODE_TTL, 'used_at'=>0];
        $id = $this->codes->insert($row);
        error_log('login_onetimecode: issued user='.$user['id'].' record='.$id);
        return ['ok'=>true, 'code'=>$code, 'expires_at'=>gmdate('c', $row['expires_at']),
            'login_url'=>$this->loginUrl()];
    }

    public function loginUrl(): string {
        // PasskeyConfig already verified direct TLS or the configured TLS terminator.
        // get_APP_URL may still see HTTP behind that terminator; retain its app path.
        return preg_replace('/^http:/', 'https:', $this->ctl->get_APP_URL('login', 'onetimecode'));
    }

    public function formToken(): string {
        $token = PasskeyService::encode(random_bytes(32));
        $this->ctl->set_session('onetimecode_form', ['token'=>$token, 'expires'=>time()+600]);
        return $token;
    }

    public function consume(string $code, string $csrf, callable $login): void {
        $form = $this->ctl->get_session('onetimecode_form');
        $this->ctl->set_session('onetimecode_form', null);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !is_array($form) || (int)($form['expires'] ?? 0) <= time()
            || !hash_equals((string)($form['token'] ?? ''), $csrf) || $csrf === ''
            || !hash_equals($this->config['origin'], (string)($_SERVER['HTTP_ORIGIN'] ?? ''))
            || (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'cross-site')) {
            throw new RuntimeException('Invalid login request.');
        }
        $this->repo->rate('onetimecode:consume:'.($_SERVER['REMOTE_ADDR'] ?? ''), 20, 60);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $code)) throw new RuntimeException('Invalid code.');
        $rows = $this->codes->select('code_hash', hash('sha256', $code), true);
        if (count($rows) !== 1) throw new RuntimeException('Invalid code.');
        $row = array_values($rows)[0];
        if ((int)$row['used_at'] !== 0 || (int)$row['expires_at'] <= time()
            || !hash_equals($row['origin'], $this->config['origin'])) throw new RuntimeException('Invalid code.');
        $user = $this->repo->user((int)$row['user_id']);
        $identity = $this->repo->identity((int)$user['id']);
        if (!hash_equals($identity['epoch'], $row['epoch'])
            || !hash_equals($identity['user_handle'], $row['user_handle'])) throw new RuntimeException('Revoked code.');
        $row['used_at'] = time();
        $this->codes->update($row);
        error_log('login_onetimecode: consumed user='.$user['id'].' record='.$row['id']);
        // The grant exists only in this server-side call stack, never in request/session input.
        self::$grant = ['ctl'=>$this->ctl, 'user_id'=>(int)$user['id']];
        try { $login($user); } finally { self::$grant = null; }
    }

    public static function hasGrant(Controller $ctl, int $userId): bool {
        return self::$grant !== null && self::$grant['ctl'] === $ctl && self::$grant['user_id'] === $userId;
    }
}
