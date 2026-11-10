<?php
// Copy this METHOD into <tb_name>_customize_standard_screen.
class contacts_customize_standard_screen {
public function edit(Controller $ctl, StandardScreenAction $action): void {
    $post = $ctl->POST();
    // Open every related DB before current(); additional opens may relock FFM.
    $before = $action->current();
    if (!$before) { $ctl->res_error_message('id', '対象がありません。'); return; }
    // Compare $post and $before; validate related rows before any writes.
    $action->standard($post); // standard validation, UPDATE, files, UI and post_action_class
}
}
