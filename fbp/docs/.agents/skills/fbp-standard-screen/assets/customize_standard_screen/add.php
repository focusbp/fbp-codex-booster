<?php
// Copy this METHOD into <tb_name>_customize_standard_screen. Do not include this file at runtime.
// Option A: validation/normalization followed by the complete standard implementation.
class contacts_customize_standard_screen {
public function add(Controller $ctl, StandardScreenAction $action): void {
    $post = $ctl->POST();
    // Open all related DBs first, then read and validate the latest records.
    // $ctl->res_error_message('field_name', '入力を確認してください。'); return;
    $action->standard($post); // standard validation, INSERT, files, UI and post_action_class
}
}
