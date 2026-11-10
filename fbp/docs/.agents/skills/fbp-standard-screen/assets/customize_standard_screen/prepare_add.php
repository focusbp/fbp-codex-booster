<?php
class contacts_customize_standard_screen {
    public function prepare_add(Controller $ctl, StandardScreenForm $form): void {
        // Use actual parameter names registered in this note's add screen.
        $form->row['status'] = 'active'; // initial value for this rendering only
        $form->excludeOption('status', 'retired');
        $form->hide('internal_code'); // hidden input keeps its value; not confidentiality/auth
        $form->readOnly('assigned_to'); // value display + hidden input; enforce again on save
        foreach ($form->fields as &$field) {
            if ($field['parameter_name'] === 'name') $field['parameter_title'] = '担当者名';
        }
        unset($field);
    }
}
