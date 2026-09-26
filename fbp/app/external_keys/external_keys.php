<?php
require_once __DIR__ . '/../../lib/ExternalKeys.php';

/** Framework system-settings note; intentionally separate from generic app Note CRUD. */
class external_keys {
    private ExternalKeys $store;
    private $submittedSecret;

    public function __construct(Controller $ctl) {
        $this->submittedSecret = $ctl->POST('external_key_secret');
        unset($_POST['external_key_secret']);
        if (!$ctl->authorize_management_access('external_keys', 'page')) $ctl->deny_forbidden_access();
        $this->store = new ExternalKeys($ctl->db('external_keys', 'external_keys'));
    }

    public function page(Controller $ctl) {
        $search = $ctl->POST('search');
        $search = is_string($search) ? $search : '';
        $ctl->assign('search', $search);
        $ctl->assign('items', $this->store->metadata($search));
        $ctl->reload_area('#setting-tab-external-keys', 'index.tpl');
    }

    public function add(Controller $ctl) {
        $ctl->assign('item', ['id' => 0, 'key' => '', 'title' => '']);
        $ctl->show_multi_dialog('external_key_edit', 'edit.tpl', $ctl->t('external_keys.add'), 700);
    }

    public function edit(Controller $ctl) {
        $item = $this->store->find((int) $ctl->POST('id'));
        if (!$item) { $ctl->show_notification_text($ctl->t('external_keys.not_found')); return; }
        $ctl->assign('item', $item);
        $ctl->show_multi_dialog('external_key_edit', 'edit.tpl', $ctl->t('external_keys.edit'), 700);
    }

    public function save(Controller $ctl) {
        $id = $ctl->POST('id');
        $key = $ctl->POST('key');
        $title = $ctl->POST('title');
        $value = $this->submittedSecret;
        $this->submittedSecret = null;
        // Never echo submitted secrets in dialog metadata, screen contexts or error reporting.
        unset($_POST['external_key_secret']);
        $ctl->clear_error_message();
        if (!is_scalar($id) || !ctype_digit((string) $id) || !is_string($key) || !is_string($title) || !is_string($value)) {
            $ctl->res_error_message('id', $ctl->t('external_keys.invalid')); return;
        }
        $id = (int) $id;
        $errors = $this->store->validate($id, $key, $title, $value);
        foreach ($errors as $field => $message) $ctl->res_error_message($field, $ctl->t('external_keys.' . $message));
        if ($errors) return;
        $this->store->save($id, $key, $title, $value);
        $ctl->close_multi_dialog('external_key_edit');
        $ctl->invoke('page', [], 'external_keys');
    }

    public function delete(Controller $ctl) {
        $item = $this->store->find((int) $ctl->POST('id'));
        if (!$item) { $ctl->show_notification_text($ctl->t('external_keys.not_found')); return; }
        $ctl->assign('item', $item);
        $ctl->show_multi_dialog('external_key_delete', 'delete.tpl', $ctl->t('external_keys.delete'), 600);
    }

    public function delete_exe(Controller $ctl) {
        $id = (int) $ctl->POST('id');
        if (!$this->store->find($id)) { $ctl->show_notification_text($ctl->t('external_keys.not_found')); return; }
        $this->store->delete($id);
        $ctl->close_multi_dialog('external_key_delete');
        $ctl->invoke('page', [], 'external_keys');
    }
}
