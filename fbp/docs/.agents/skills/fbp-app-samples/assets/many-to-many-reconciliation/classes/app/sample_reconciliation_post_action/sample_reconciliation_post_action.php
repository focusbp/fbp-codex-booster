<?php
require_once __DIR__.'/../sample_reconciliation/Store.php';
class sample_reconciliation_post_action implements CodegenActionInterface {
    function run(Controller $ctl):void {
        if(!$ctl->get_session('login')||!$ctl->is_app_admin())return;
        (new SampleReconciliationStore($ctl))->refresh();
    }
}
