<?php
final class PasskeyRegistrationMail {
    public static function sendTransfer(Controller $ctl,array $transfer): void {
        try {
            $ctl->close_all_db();
            $ctl->assign('passkey_device_code',$transfer['pin']);
            $ctl->assign('passkey_device_label',$transfer['label']);
            $ctl->send_mail_prepared_format($transfer['email'],'passkey_device_code',null,'他の端末へのパスキー設定：確認コード','device_code_mail.tpl',['throw_on_error'=>true]);
        } catch(Throwable $e) {
            throw new RuntimeException('確認コードをメール送信できませんでした。登録メールアドレスとメール設定を確認して、発行し直してください。');
        }
    }
    public static function send(Controller $ctl,array $registration): string {
        try {
            // Registration is committed before SMTP; delivery failure must not create a second key.
            $ctl->close_all_db();
            if(!filter_var($registration['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('No registered mail address.');
            $ctl->assign('registered_passkey',$registration);
            $ctl->send_mail_prepared_format($registration['email'],'passkey_registered',null,'新しいパスキーが登録されました','registered_mail.tpl',['throw_on_error'=>true]);
            return 'sent';
        } catch(Throwable $e) {
            error_log('passkey: registration_notification_failed user='.(int)$registration['user_id']);
            return 'failed';
        }
    }
}
