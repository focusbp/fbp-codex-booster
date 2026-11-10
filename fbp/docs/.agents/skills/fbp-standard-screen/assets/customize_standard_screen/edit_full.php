<?php
// Full persistence example: copy the method into your configured class.
// Adapt normalization, permissions, concurrency and file validation to your note.
class contacts_customize_standard_screen {
    public function edit(Controller $ctl, StandardScreenAction $action): void {
        $post = $ctl->POST();
        // ここまでに関係する全DBを開く。その後でcurrent()と関連レコードを読み直す。
        $before = $action->operation === 'edit' ? $action->current() : null;
        if ($action->operation === 'edit' && !$before) {
            $ctl->res_error_message('id', '対象がありません。'); return;
        }
        $screen = $action->single || $action->operation === 'edit' ? 'edit' : 'add';
        $row = $before ?: $ctl->get_default_values($action->table);
        // 更新可能なフォーム項目だけ採用する。hiddenや制御パラメータをDBへ丸ごと入れない。
        foreach ($ctl->get_field_list($action->table, $screen) as $field) {
            $name = $field['parameter_name'];
            if (in_array($name, ['id', 'created_at', 'updated_at'], true)) continue;
            if ($field['type'] === 'checkbox') $row[$name] = $post[$name] ?? [];
            elseif (!in_array($field['type'], ['file', 'image'], true) && array_key_exists($name, $post)) $row[$name] = $post[$name];
        }
        if ($action->child) $row[$action->child['parent_field']] = $action->child['parent_id'];
        // 業務上の検証、閲覧・更新権限、改ざん、同時更新をここで確認する。
        $ctl->validate($action->table, $screen, $row, $before === null);
        if ($ctl->count_res_error_message() > 0) return;
        $db = $action->db();
        if ($before) { $row['id'] = $before['id']; $db->update($row); }
        else { unset($row['id']); $db->insert($row); }
        $ctl->save_posted_files($action->table, $row);
        $action->finish($db->get($row['id']), $before);
    }
}
