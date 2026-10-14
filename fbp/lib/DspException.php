<?php

class DspException extends RuntimeException {
    private array $details;
    private array $runtimeContext = [];

    public function __construct(string $rule, string $reason = 'denied', array $details = [], ?Throwable $previous = null) {
        $this->details = ['rule' => $rule, 'reason' => $reason] + $details;
        parent::__construct('データ操作が許可されていないか、安全に判定できません。', 0, $previous);
    }

    public function getDetails(): array { return $this->details; }

    public function setRuntimeContext(array $context): void {
        $this->runtimeContext = array_intersect_key($context, array_flip(['operation', 'channel', 'trusted_cron']));
    }

    /** Only symbolic codes and flags; never copy rows, identities or credentials. */
    public function getServerErrorMetadata(string $fallbackChannel = 'unknown'): array {
        $code = static fn($v) => is_string($v) && preg_match('/\A[A-Za-z0-9_.:-]{1,100}\z/D', $v) ? $v : '';
        $channel = $this->runtimeContext['channel'] ?? $fallbackChannel;
        if (!in_array($channel, ['admin','public','mcp','api','cron','cli','unknown'], true)) $channel = 'unknown';
        return [
            'version' => 1,
            'rule' => $code($this->details['rule'] ?? ''),
            'reason' => $code($this->details['reason'] ?? ''),
            'channel' => $channel,
            'operation' => $code($this->runtimeContext['operation'] ?? ''),
            'expected_denial' => ($this->details['expected_denial'] ?? false) === true,
            'trusted_cron' => ($this->runtimeContext['trusted_cron'] ?? false) === true,
        ];
    }
}
