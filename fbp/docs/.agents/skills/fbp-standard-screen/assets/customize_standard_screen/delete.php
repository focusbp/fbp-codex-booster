<?php
// Copy this METHOD into <tb_name>_customize_standard_screen.
class contacts_customize_standard_screen {
public function delete(Controller $ctl, StandardScreenAction $action): void {
    // Open every reference DB first; check related rows before deleting files or records.
    $before = $action->current();
    if (!$before) { $ctl->res_error_message('id', '対象がありません。'); return; }
    // If referenced: $ctl->res_error_message('id', '関連記録があります。'); return;
    $action->standard(); // configured cascades, files, UI and post_action_class
}
}
