<?php

class DspException extends RuntimeException {
    private array $details;

    public function __construct(string $rule, string $reason = 'denied', array $details = [], ?Throwable $previous = null) {
        $this->details = ['rule' => $rule, 'reason' => $reason] + $details;
        parent::__construct('データ操作が許可されていないか、安全に判定できません。', 0, $previous);
    }

    public function getDetails(): array { return $this->details; }
}
