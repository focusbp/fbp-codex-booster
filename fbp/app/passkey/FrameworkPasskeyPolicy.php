<?php
require_once __DIR__.'/PasskeyAccess.php';
/** Fixed authentication storage; service methods enforce ownership and ceremonies. */
final class FrameworkPasskeyPolicy implements DspInterface {
    public function __construct(private Controller $ctl,private string $channel) {}
    private function check(): void {
        if (PHP_SAPI==='cli' && $this->channel==='cli' && $this->ctl->get_classname()==='passkey' && $this->ctl->get_called_function()==='emergency_cli_recovery' && defined('PASSKEY_ADMIN_RECOVERY_CONFIRMED') && PASSKEY_ADMIN_RECOVERY_CONFIRMED===true) return;
        $subject=$this->ctl->get_dsp_system_subject();
        if ($this->channel==='api' && $this->ctl->get_classname()==='login_api' && $this->ctl->get_called_function()==='issue_onetimecode' && ($subject['verified']??false)===true && ($subject['kind']??'')==='api') return;
        if ($this->channel==='api' && $this->ctl->get_classname()==='db_api' && ($subject['verified']??false)===true && ($subject['kind']??'')==='api') return;
        // Production frameworks classify unauthenticated authentication entries as public.
        if (in_array($this->channel,['admin','public'],true) && in_array($this->ctl->get_classname(),['passkey','login','password_reset'],true)) return;
        if ($this->channel==='admin' && PasskeyAccess::active()) return;
        if ($this->channel==='cli' && PasskeyAccess::active() && $this->ctl->get_classname()==='user' && $this->ctl->get_called_function()==='delete_exe') return;
        throw new DspException('passkey.storage','operation_denied');
    }
    public function authorizeRead(array $request): void {$this->check();}
    public function inspectRead(array $row): DspReadDecision {$this->check();return new DspReadDecision(true,null);}
    public function authorizeInsert(array $row): void {$this->check();}
    public function authorizeUpdate(array $before,array $after,array $fields): void {$this->check();}
    public function authorizeDelete(array $before): void {$this->check();}
}
