<?php
/** Production capability and operational switches are separate. */
final class SimpleEcPaymentGuard {
    public const PRODUCTION_PAYMENTS_ENABLED = true;
    public const RECURRING_CHARGES_ENABLED = true;
    private static function runtime(array $db): array {
        $rows=$db['runtime']->getall();
        if(count($rows)>1)throw new RuntimeException('ショップ運用設定は1件にしてください。');
        return $rows[0]??[];
    }
    public static function recurringStartMonth(array $db): string {
        $row=self::runtime($db);$date=$row['recurring_start_date']??0;
        $stamp=is_numeric($date)?(int)$date:strtotime((string)$date);
        if(!$stamp)throw new RuntimeException('ショップ運用設定で定期課金開始日を設定してください。');
        return date('Y-m',$stamp);
    }
    public static function isSandbox(Controller $ctl): bool {
        return (int)($ctl->get_setting()['force_testmode']??0)===1 && (bool)$ctl->get_session('testserver');
    }
    public static function enabled(array $db,string $flag='live_enabled'): bool {
        $runtime=self::runtime($db);
        return (int)($runtime['live_enabled']??0)===1 && (int)($runtime[$flag]??0)===1;
    }
    public static function assertProduction(Controller $ctl,array $db,string $flag='live_enabled'): void {
        if((int)($ctl->get_setting()['force_testmode']??0)!==0 || $ctl->get_session('testserver')
            || !self::PRODUCTION_PAYMENTS_ENABLED || !self::enabled($db,$flag))throw new RuntimeException('現在、本番の受付・決済は停止しています。');
    }
    public static function assertRecurringAllowed(?Controller $ctl=null,?array $db=null): void {
        if(!$ctl || !$db || !self::RECURRING_CHARGES_ENABLED)throw new RuntimeException('本番定期決済の実行条件を確認してください。');
        self::assertProduction($ctl,$db,'cron_enabled');
    }
    public static function assertSandbox(Controller $ctl): void {
        if (!$ctl->get_session('testserver')) {
            throw new RuntimeException('Sandbox専用の操作です。');
        }
    }
}
