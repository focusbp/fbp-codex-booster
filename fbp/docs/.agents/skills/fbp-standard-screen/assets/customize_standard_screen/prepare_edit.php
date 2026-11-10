<?php
class contacts_customize_standard_screen {
    public function prepare_edit(Controller $ctl, StandardScreenForm $form): void {
        // keepCurrent=true retains the saved option if it has become unavailable.
        $form->excludeOption('status', 'retired', keepCurrent: true);
        if (($form->row['status'] ?? '') === 'locked') {
            $form->readOnly('name');
        }
        // If reselection is required: excludeOption(..., keepCurrent:false),
        // clear the current value and supply an empty/prompt option explicitly.
        // These are presentation decisions. Validate allowed values in edit() as well.
    }
}
