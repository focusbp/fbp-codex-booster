<?php
class sample_mail_import implements CodegenActionInterface {
    const ACCOUNTS = 'sample_mail_accounts';
    const MESSAGES = 'sample_mail_messages';
    private function admin(Controller $ctl): void {
        if (!$ctl->get_session('login') || !$ctl->is_app_admin()) throw new RuntimeException('管理者のみ実行できます。');
    }
    private function stores(Controller $ctl): array {
        $ctl->db('setting', 'setting');
        return [$ctl->db(self::ACCOUNTS), $ctl->db(self::MESSAGES)];
    }
    private function rowId(Controller $ctl): int {
        $id = $ctl->decrypt_post('id');
        if (!ctype_digit((string) $id) || (int) $id < 1) throw new RuntimeException('アカウントを選択してください。');
        return (int) $id;
    }
    public function import(Controller $ctl, int $id): int {
        [$accounts, $messages] = $this->stores($ctl);
        $row = $accounts->get($id);
        if (!$row || (string) $row['enabled'] !== '1') return 0;
        $account = ['table' => self::ACCOUNTS, 'id' => $id, 'password_encrypted' => true];
        $count = 0;
        foreach ($ctl->receive_mail(20, $account) as $mail) {
            $key = hash('sha256', $mail['id']);
            // All participating DBs were opened before read/compare/write.
            // An interrupted confirmation can retry without inserting the same mail again.
            if (!$messages->filter('message_key', $key, true)) {
                $record = ['account_id' => $id, 'message_key' => $key, 'receipt' => $mail['id'],
                    'subject' => $mail['subject'], 'sender' => $mail['from'][0]['email'] ?? '',
                    'body' => $mail['text'], 'attachment_names' => implode("\n", array_column($mail['attachments'], 'name')),
                    'received_at' => time()];
                foreach (['receipt' => 2048, 'subject' => 3000, 'sender' => 1000, 'body' => 65535, 'attachment_names' => 6000] as $field => $length) {
                    if (strlen($record[$field]) > $length) throw new RuntimeException('受信メールがノートの保存上限を超えています。');
                }
                $messages->insert($record);
                ++$count;
            }
            $ctl->confirm_received_mail($mail['id'], $account);
        }
        $accounts->update(['id' => $id, 'last_received_at' => time(), 'last_error' => '']);
        return $count;
    }
    function run(Controller $ctl): void {
        $this->admin($ctl);
        try { $count = $this->import($ctl, $this->rowId($ctl)); $ctl->show_notification_text($count . '件を保存しました。'); }
        catch (Throwable $e) { $ctl->show_notification_text('受信できませんでした。接続設定と保存上限を確認してください。'); }
        $ctl->reload_work_area();
    }
    function cron(Controller $ctl): void {
        $subject = $ctl->get_dsp_system_subject();
        if (($subject['kind'] ?? '') !== 'cron' || ($subject['verified'] ?? false) !== true) throw new RuntimeException('検証済みcron専用です。');
        [$accounts] = $this->stores($ctl);
        $failed = 0;
        foreach ($accounts->getall() as $row) {
            if ((string) $row['enabled'] !== '1') continue;
            try { $this->import($ctl, (int) $row['id']); }
            catch (Throwable $e) { ++$failed; $accounts->update(['id' => $row['id'], 'last_error' => '受信または保存に失敗しました。設定と保存上限を確認してください。']); }
        }
        if ($failed) throw new RuntimeException($failed . '件のアカウントで受信に失敗しました。');
    }
    function configure(Controller $ctl): void {
        $this->admin($ctl);
        [$accounts] = $this->stores($ctl);
        $row = $accounts->get($this->rowId($ctl));
        if (!$row) throw new RuntimeException('アカウントがありません。');
        $token = bin2hex(random_bytes(24));
        $ctl->set_session('sample_mail_config', ['id' => (int) $row['id'], 'token' => $token, 'expires' => time() + 1800,
            'fingerprint' => $this->fingerprint($row)]);
        unset($row['receive_password'], $row['receive_state']);
        $ctl->assign('account', $row); $ctl->assign('token', $token);
        $ctl->show_multi_dialog('sample-mail-config', 'configure.tpl', '受信接続設定', 700);
    }
    private function fingerprint(array $row): string {
        unset($row['receive_state'], $row['last_received_at'], $row['last_error']);
        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }
    function save(Controller $ctl): void {
        $this->admin($ctl);
        [$accounts] = $this->stores($ctl);
        $pending = $ctl->get_session('sample_mail_config');
        $token = $ctl->POST('token');
        if (!is_array($pending) || !is_string($token) || !hash_equals($pending['token'], $token) || $pending['expires'] < time()) {
            $ctl->res_error_message('token', '接続設定を開き直してください。'); return;
        }
        $row = $accounts->get($pending['id']);
        if (!$row || $this->fingerprint($row) !== $pending['fingerprint']) { $ctl->res_error_message('token', '設定が変更されています。開き直してください。'); return; }
        $values = [];
        foreach (['receive_protocol', 'receive_server', 'receive_port', 'receive_secure', 'receive_user', 'receive_folder', 'receive_password'] as $key) {
            $value = $ctl->POST($key);
            if (!is_string($value) || strlen($value) > 255 || preg_match('/[\r\n\x00]/', $value)) { $ctl->res_error_message('token', '入力値を確認してください。'); return; }
            $values[$key] = $key === 'receive_password' ? $value : trim($value);
        }
        if (!in_array($values['receive_protocol'], ['pop', 'imap'], true) || !in_array($values['receive_secure'], ['ssl', 'tls', 'none'], true)
            || !(filter_var($values['receive_server'], FILTER_VALIDATE_IP) || filter_var($values['receive_server'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
            || !preg_match('/^[0-9]{1,5}$/D', $values['receive_port']) || (int) $values['receive_port'] < 1 || (int) $values['receive_port'] > 65535 || $values['receive_user'] === '') {
            $ctl->res_error_message('token', '受信方式・サーバー・ポート・暗号化・ユーザー名を確認してください。'); return;
        }
        if ($values['receive_password'] === '') unset($values['receive_password']);
        else $values['receive_password'] = $ctl->encrypt($values['receive_password']);
        $accounts->update(['id' => $row['id']] + $values);
        $ctl->set_session('sample_mail_config', null); $ctl->close_multi_dialog('sample-mail-config');
        $ctl->reload_work_area(); $ctl->show_notification_text('接続設定を保存しました。');
    }
}
