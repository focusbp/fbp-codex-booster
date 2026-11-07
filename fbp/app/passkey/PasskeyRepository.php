<?php
final class PasskeyRepository {
    public $users;
    public $identities;
    public $keys;
    public $rates;
    public $transfers;
    public function __construct(Controller $ctl) {
        // Open all participants before reading: ordered FFM reopen may release locks.
        $this->users = $ctl->db('user', 'user');
        // login::__construct also opens setting: pre-open it before signature verification
        // so the authentication-to-session transition never releases the key locks.
        $ctl->db('setting', 'setting');
        $this->identities = $ctl->db('identity', 'passkey');
        $this->keys = $ctl->db('user_passkey', 'passkey');
        $this->rates = $ctl->db('rate', 'passkey');
        $this->transfers = $ctl->db('device_transfer', 'passkey');
        foreach ([$this->identities,$this->keys,$this->rates,$this->transfers] as $db) $db->set_strict_field_length(true);
    }
    public function user(int $id): array {
        $u = $this->users->get($id);
        if (!is_array($u) || empty($u['id']) || (int)$u['status'] !== 0 || !in_array($u['type'] ?? null, [0,1,'0','1'], true)) throw new RuntimeException('認証を確認できませんでした。');
        return $u;
    }
    public function identity(int $userId): array {
        $rows = $this->identities->select('user_id', $userId, true);
        if (count($rows) > 1) throw new RuntimeException('認証設定を確認してください。');
        if ($rows) return array_values($rows)[0];
        $row = ['user_id'=>$userId,'user_handle'=>PasskeyService::encode(random_bytes(32)), 'epoch'=>PasskeyService::encode(random_bytes(32)), 'created_at'=>date('Y-m-d H:i:s'),'password_login_disabled'=>0];
        $row['id'] = $this->identities->insert($row);
        return $row;
    }
    public function passwordLoginDisabled(int $userId): bool { return (int)($this->identity($userId)['password_login_disabled'] ?? 0) === 1; }
    public function owned(int $userId): array { return array_values($this->keys->select('parent_id', $userId, true)); }
    public function active(int $userId, string $rp): array {
        return array_values(array_filter($this->owned($userId), static fn($r)=>(int)$r['status']===0 && $r['rp_id']===$rp));
    }
    public function credential(string $rp, string $raw): array {
        $rows = $this->keys->select(['rp_id','credential_id_hash'], [$rp,hash('sha256',$raw)],true);
        if (count($rows)!==1) throw new RuntimeException('認証を確認できませんでした。');
        $key = array_values($rows)[0];
        if ((int)$key['status']!==0 || !hash_equals($key['credential_id'],PasskeyService::encode($raw))) throw new RuntimeException('認証を確認できませんでした。');
        return $key;
    }
    public function rate(string $bucket, int $limit, int $seconds): void {
        $hash = hash('sha256',$bucket);
        $rows = $this->rates->select('bucket',$hash,true);
        $row = $rows ? array_values($rows)[0] : ['bucket'=>$hash,'started_at'=>time(),'hits'=>0];
        if (time()-(int)$row['started_at'] >= $seconds) { $row['started_at']=time(); $row['hits']=0; }
        if ((int)$row['hits'] >= $limit) throw new RuntimeException('操作回数が上限に達しました。しばらく待ってお試しください。');
        $row['hits']=(int)$row['hits']+1;
        if (empty($row['id'])) $this->rates->insert($row); else $this->rates->update($row);
        // Bound persistent rate storage; raw IPs and session identifiers are never stored.
        foreach ($this->rates->getall() as $old) if (time()-(int)$old['started_at'] > 3600) $this->rates->delete($old['id']);
    }
    public function clearRate(string $bucket): void {
        foreach ($this->rates->select('bucket',hash('sha256',$bucket),true) as $r) $this->rates->delete($r['id']);
    }
    /** Emergency local recovery. Never callable through HTTP or ordinary app_call. */
    public function recoverAdmin(int $userId): void {
        if (PHP_SAPI !== 'cli' || !defined('PASSKEY_ADMIN_RECOVERY_CONFIRMED') || PASSKEY_ADMIN_RECOVERY_CONFIRMED !== true) {
            throw new RuntimeException('CLI recovery confirmation required.');
        }
        $user=$this->user($userId);
        if ((int)$user['type'] !== 0) throw new RuntimeException('管理者ユーザーを指定してください。');
        // Epoch and handle first: interrupted recovery must invalidate old credentials.
        $this->revokeAll($userId);
        foreach (['password_reset_token_hash','password_reset_token_expires_at','password_reset_token_sent_at'] as $field) $user[$field]=str_ends_with($field,'hash')?'':0;
        $this->users->update($user);
        $identity=$this->identity($userId);
        $identity['password_login_disabled']=0;
        $this->identities->update($identity);
        error_log('passkey: emergency_cli_recovery user='.$userId);
    }
    public function revokeAll(int $userId): void {
        // Change the epoch first, so partial failures also block old sessions/cookies.
        $identity=$this->identity($userId);
        $identity['epoch']=PasskeyService::encode(random_bytes(32));
        $identity['user_handle']=PasskeyService::encode(random_bytes(32));
        $this->identities->update($identity);
        foreach ($this->owned($userId) as $key) {
            $key['status']=1; $key['revoked_at']=date('Y-m-d H:i:s'); $this->keys->update($key);
        }
    }
}
