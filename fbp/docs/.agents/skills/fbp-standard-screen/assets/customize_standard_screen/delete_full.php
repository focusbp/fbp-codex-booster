<?php
// Full delete template: open related databases and implement business checks before deletion.
class contacts_customize_standard_screen {
    public function delete(Controller $ctl, StandardScreenAction $action): void {
        $before = $action->current();
        if (!$before) { $ctl->res_error_message('id', '対象がありません。'); return; }
        // Check ownership, references and child cascades here; reject before any mutation.
        if ($ctl->count_res_error_message() > 0) return;
        $action->deleteRecord((int)$before['id']);
        $action->finish($before); // post_action_class and standard display updates exactly once
    }
}
