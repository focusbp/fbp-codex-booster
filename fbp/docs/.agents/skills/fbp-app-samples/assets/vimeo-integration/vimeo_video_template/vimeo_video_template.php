<?php
require_once __DIR__ . '/vimeo_api.php';

class vimeo_video_template {
    // Adapt these four hooks to the app. Until then, every entry refuses access.
    // A local record is: id, revision, vimeo_id (empty for a new upload).
    // canManage must authorize a TRUSTED OPERATOR who may know the Vimeo account token.
    protected function canManage(Controller $ctl): bool { return false; }
    protected function loadRecord(Controller $ctl, int $id): ?array { return null; }
    protected function saveVideo(Controller $ctl, array $record, string $videoId): void {
        throw new LogicException('Implement an atomic revision-checked FFM update');
    }
    protected function detachVideo(Controller $ctl, array $record): void {
        throw new LogicException('Implement an atomic revision-checked FFM update');
    }

    protected function api(Controller $ctl): sample_vimeo_api {
        $setting = $ctl->get_setting();
        return new sample_vimeo_api((string) ($setting['vimeo_access_token'] ?? ''));
    }

    function upload(Controller $ctl) {
        $record = $this->record($ctl);
        if (!$record) return;
        if (!empty($record['vimeo_id'])) { $ctl->show_notification_text('登録済みです。'); return; }
        if (empty($ctl->get_setting()['vimeo_access_token'])) { $ctl->show_notification_text('Vimeo設定が必要です。'); return; }
        $nonce = $this->grant($ctl, 'upload', $record);
        // Legacy FBP uploader exposes its token to the browser. Trusted operators only.
        $ctl->show_vimeo_uploader('vimeo_video_upload', 'vimeo_video_template', 'uploaded', ['nonce' => $nonce]);
    }

    function uploaded(Controller $ctl) {
        if (!$this->canManage($ctl)) { $this->deny($ctl); return; }
        $params = $ctl->get_vimeo_callback_parameter_array();
        if (!is_array($params)) { $this->deny($ctl); return; }
        $record = $this->consume($ctl, 'upload', (string) ($params['nonce'] ?? ''));
        if (!$record || !empty($record['vimeo_id'])) { $this->deny($ctl); return; }
        try {
            $id = sample_vimeo_api::id($ctl->get_vimeo_id_uploaded());
            $this->api($ctl)->ownedVideo($id); // The callback is browser POST, not a signed Vimeo webhook.
            $this->saveVideo($ctl, $record, $id);
        } catch (Throwable $e) {
            $ctl->show_notification_text('動画を登録できませんでした。Vimeoと登録先の状態を確認してください。'); return;
        }
        $ctl->close_multi_dialog('vimeo_video_upload');
        $ctl->show_notification_text('動画を登録しました。');
    }

    function play(Controller $ctl) {
        $record = $this->record($ctl);
        if (!$record) return;
        try { $id = sample_vimeo_api::id($record['vimeo_id'] ?? ''); }
        catch (Throwable $e) { $this->deny($ctl); return; }
        $ctl->assign('video_id', $id);
        $ctl->assign('record_id', (int) $record['id']);
        $ctl->show_multi_dialog('vimeo_video_player', 'play.tpl', '動画', 800);
    }

    function confirm_delete(Controller $ctl) {
        $record = $this->record($ctl);
        if (!$record || empty($record['vimeo_id'])) return;
        $ctl->assign('delete_nonce', $this->grant($ctl, 'delete', $record));
        $ctl->show_multi_dialog('vimeo_video_delete', 'delete.tpl', '動画削除の確認', 620);
    }

    function delete_video(Controller $ctl) {
        if (!$this->canManage($ctl)) { $this->deny($ctl); return; }
        $record = $this->consume($ctl, 'delete', (string) ($ctl->POST('delete_nonce') ?? ''));
        if (!$record || empty($record['vimeo_id'])) { $this->deny($ctl); return; }
        try {
            $this->api($ctl)->delete(sample_vimeo_api::id($record['vimeo_id']));
            // Keep the local binding when the remote deletion fails. Do not hold a DB lock across HTTP.
            $this->detachVideo($ctl, $record);
        } catch (Throwable $e) {
            $ctl->show_notification_text('削除結果を確認できませんでした。再送する前にVimeoと登録先の状態を確認してください。'); return;
        }
        $ctl->close_multi_dialog('vimeo_video_delete');
        $ctl->close_multi_dialog('vimeo_video_player');
        $ctl->show_notification_text('動画を削除しました。');
    }

    private function record(Controller $ctl): ?array {
        if (!$this->canManage($ctl)) { $this->deny($ctl); return null; }
        $id = filter_var($ctl->POST('record_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $record = $id ? $this->loadRecord($ctl, $id) : null;
        if (!$record || (int) ($record['id'] ?? 0) !== $id || !isset($record['revision'])) { $this->deny($ctl); return null; }
        return $record;
    }

    private function grant(Controller $ctl, string $operation, array $record): string {
        $nonce = bin2hex(random_bytes(24));
        $ctl->set_session('vimeo_template_' . $operation, ['nonce' => $nonce, 'record' => $record,
            'user_id' => (string) $ctl->get_login_user_id(), 'expires' => time() + 3600]);
        return $nonce;
    }

    private function consume(Controller $ctl, string $operation, string $nonce): ?array {
        $key = 'vimeo_template_' . $operation;
        $grant = $ctl->get_session($key);
        if (!is_array($grant) || $nonce === '' || !hash_equals((string) ($grant['nonce'] ?? ''), $nonce)
            || ($grant['expires'] ?? 0) < time() || ($grant['user_id'] ?? '') !== (string) $ctl->get_login_user_id()) return null;
        $ctl->set_session($key, null); // One shot; unknown external outcomes require review rather than blind retry.
        $old = $grant['record'];
        $current = $this->loadRecord($ctl, (int) $old['id']);
        if (!$current || (int) ($current['id'] ?? 0) !== (int) $old['id']
            || !isset($current['revision']) || (string) $current['revision'] !== (string) $old['revision']
            || (string) ($current['vimeo_id'] ?? '') !== (string) ($old['vimeo_id'] ?? '')) return null;
        return $current;
    }

    private function deny(Controller $ctl): void { $ctl->show_notification_text('対象がないか、操作が許可されていません。'); }
}
