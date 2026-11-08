<?php
/** Fixed framework credential storage: cannot be created/edited through generic DB APIs. */
final class FrameworkOnetimecodePolicy implements DspInterface {
    public function __construct(private Controller $ctl, private string $channel) {}
    private function check(): void {
        if (in_array($this->channel, ['public','admin'], true) && $this->ctl->get_classname() === 'login'
            && $this->ctl->get_called_function() === 'onetimecode') return;
        $subject = $this->ctl->get_dsp_system_subject();
        if ($this->channel === 'api' && $this->ctl->get_classname() === 'login_api'
            && $this->ctl->get_called_function() === 'issue_onetimecode'
            && ($subject['verified'] ?? false) === true && ($subject['kind'] ?? '') === 'api') return;
        throw new DspException('login.onetimecode', 'operation_denied');
    }
    public function authorizeRead(array $request): void {$this->check();}
    public function inspectRead(array $row): DspReadDecision {$this->check(); return new DspReadDecision(true, null);}
    public function authorizeInsert(array $row): void {$this->check();}
    public function authorizeUpdate(array $before, array $after, array $fields): void {$this->check();}
    public function authorizeDelete(array $before): void {$this->check();}
}
