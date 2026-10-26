<?php
require_once __DIR__.'/../simple_ec/Store.php';
require_once __DIR__.'/../simple_ec/RecurringEngine.php';
require_once __DIR__.'/../simple_ec/SandboxPayment.php';
require_once __DIR__.'/../simple_ec/ProductionPayment.php';
class simple_ec_cron {
    function run(Controller $ctl) {
        $subject=$ctl->get_dsp_system_subject();
        if(PHP_SAPI!=='cli' && !SimpleEcStore::administrator($ctl) && !(($subject['kind']??'')==='cron' && !empty($subject['verified'])))throw new RuntimeException('定期決済の実行権限がありません。');
        date_default_timezone_set((string)($ctl->get_setting()['timezone']??'Asia/Tokyo')?:'Asia/Tokyo');
        if(!SimpleEcTypes::allows($ctl,4)){$ctl->res_json(['ok'=>true,'disabled'=>true,'reason'=>'subscription_not_enabled']);return;}
        if(SimpleEcPaymentGuard::isSandbox($ctl)){$this->sandbox_run($ctl);return;}
        $db=SimpleEcStore::open($ctl);
        if(!SimpleEcPaymentGuard::enabled($db,'cron_enabled')){$ctl->res_json(['ok'=>true,'disabled'=>true,'succeeded'=>0,'failed'=>0,'held'=>0,'recovered'=>0]);return;}
        SimpleEcPaymentGuard::assertRecurringAllowed($ctl,$db);
        $ctl->res_json(SimpleEcProductionPayment::chargeSubscriptions($ctl,$db));
    }
    private function testJob(Controller $ctl): void {
        SimpleEcSandboxPayment::configure($ctl);
        date_default_timezone_set((string)($ctl->get_setting()['timezone']??'Asia/Tokyo')?:'Asia/Tokyo');
        $subject=$ctl->get_dsp_system_subject();
        if(PHP_SAPI!=='cli'&&!SimpleEcStore::administrator($ctl)&&!(($subject['kind']??'')==='cron'&&!empty($subject['verified'])))throw new RuntimeException('テストジョブの実行権限がありません。');
    }
    function sandbox_run(Controller $ctl){
        SimpleEcTypes::requireType($ctl,4);$db=SimpleEcStore::open($ctl);
        if(!SimpleEcPaymentGuard::enabled($db,'cron_enabled')){$ctl->res_json(['ok'=>true,'disabled'=>true]);return;}
        $this->testJob($ctl);$ctl->res_json(SimpleEcSandboxPayment::chargeSubscriptions($ctl,$db));
    }

}
