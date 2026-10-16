<?php

require_once __DIR__.'/../interface/DspInterface.php';
require_once __DIR__.'/DspException.php';
require_once __DIR__.'/DspReadDecision.php';

/** Framework-owned access for the authenticated integration settings pipeline. */
final class DspIntegrationPolicy implements DspInterface {
    private function __construct(private Controller $ctl, private string $channel, private string $target) {}

    private static function trusted(Controller $ctl, string $channel): bool {
        if (!method_exists($ctl, 'get_classname')) return false;
        $entry = $ctl->get_classname();
        if ($entry === 'integration_settings' && $channel === 'cli' && PHP_SAPI === 'cli') return true;
        if ($channel !== 'api' || !in_array($entry, ['integration_settings', 'project_integration_api'], true)
            || !method_exists($ctl, 'get_dsp_system_subject')) return false;
        $subject = $ctl->get_dsp_system_subject();
        return ($subject['verified'] ?? false) === true && ($subject['kind'] ?? '') === 'api';
    }

    public static function resolve(?Controller $ctl, string $channel, string $class, string $table): ?self {
        if ($ctl === null || !self::trusted($ctl, $channel)) return null;
        $target = $class.'/'.$table;
        $targets = $ctl->get_classname() === 'project_integration_api'
            ? ['project_integration/items', 'common/project', 'common/sftp', 'common/task', 'common/task_history']
            : ['setting/setting', 'external_keys/external_keys', 'integration_settings/receipts'];
        return in_array($target, $targets, true) ? new self($ctl, $channel, $target) : null;
    }

    private function check(): void {
        $current = self::resolve($this->ctl, $this->channel, ...explode('/', $this->target, 2));
        if ($current === null) $this->deny('authentication_required');
    }
    private function deny(string $reason): never { throw new DspException('framework_integration', $reason); }
    public function authorizeRead(array $request): void { $this->check(); }
    public function inspectRead(array $row): DspReadDecision { $this->check(); return new DspReadDecision(true, null); }
    public function authorizeInsert(array $newRow): void {
        $this->check();
        if (!in_array($this->target, ['project_integration/items', 'integration_settings/receipts', 'external_keys/external_keys', 'common/task_history'], true)) $this->deny('insert_not_allowed');
        if ($this->target === 'common/task_history' && ((int)($newRow['role'] ?? 0) !== 1
            || !str_starts_with($newRow['message'] ?? '', '[INTEGRATION_SAVED request='))) $this->deny('history_not_integration');
    }
    public function authorizeUpdate(array $before, array $after, array $submittedFields): void {
        $this->check();
        $allowed = match ($this->target) {
            'project_integration/items' => ['key', 'part', 'document'],
            'external_keys/external_keys' => ['key', 'title', 'value'],
            'common/project' => ['unfinished_task_count'],
            'common/task' => ['status', 'last_comment_at', 'auto_status_review_history_id', 'auto_status_review_action',
                'auto_status_review_reason', 'auto_status_reviewed_at', 'auto_status_review_version'],
            'common/task_history' => ['message'],
            'setting/setting' => self::settingFields(),
            default => [],
        };
        foreach ($submittedFields as $field) {
            // Existing callers submit complete rows; unchanged fields are not writes.
            if (($before[$field] ?? null) !== ($after[$field] ?? null) && !in_array($field, $allowed, true)) $this->deny('field_not_allowed');
        }
        if ($this->target === 'common/task' && (int)($before['status'] ?? 0) !== (int)($after['status'] ?? 0)
            && !((int)($before['status'] ?? 0) === 3 && (int)($after['status'] ?? 0) === 0)) $this->deny('invalid_resume_status');
        if ($this->target === 'common/task_history' && ((int)($before['role'] ?? 0) !== 1
            || !str_starts_with($before['message'] ?? '', '[INTEGRATION_SUBMITTED request=')
            || !str_starts_with($after['message'] ?? '', '[INTEGRATION_SAVED request='))) $this->deny('history_not_integration');
    }
    private static function settingFields(): array {
        require_once __DIR__.'/IntegrationSettings.php';
        $fields = [];
        foreach (IntegrationSettings::definitions() as $definition) $fields = array_merge($fields, array_keys($definition['fields']));
        return array_unique($fields);
    }
    public function authorizeDelete(array $before): void {
        $this->check();
        if ($this->target !== 'project_integration/items') $this->deny('delete_not_allowed');
    }
}
