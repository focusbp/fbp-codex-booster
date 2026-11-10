<?php
require_once __DIR__.'/StandardScreenAction.php';

/** Per-render field definitions and values; never changes saved note definitions. */
final class StandardScreenForm {
    public function __construct(
        public readonly string $operation,
        public readonly string $table,
        public readonly array $setting,
        public readonly ?array $child,
        public readonly bool $single,
        public array $fields,
        public array $row,
    ) {}

    public function hide(string $name): void { $this->flag($name, 'standard_screen_hidden'); }
    public function readOnly(string $name): void { $this->flag($name, 'standard_screen_readonly'); }

    /** Excluding a saved value is explicit: keep it visible or require a new selection. */
    public function excludeOption(string $name, string|int $value, bool $keepCurrent = true): void {
        foreach ($this->fields as &$field) {
            if (($field['parameter_name'] ?? '') !== $name) continue;
            $current = $this->row[$name] ?? null;
            $selected = is_array($current) ? array_map('strval', $current) : [(string)$current];
            if (!$keepCurrent || $this->operation !== 'edit' || !in_array((string)$value, $selected, true)) unset($field['options'][$value]);
        }
        unset($field);
    }

    private function flag(string $name, string $flag): void {
        foreach ($this->fields as &$field) {
            if (($field['parameter_name'] ?? '') === $name) $field[$flag] = true;
        }
        unset($field);
    }

    public function prepare(Controller $ctl, string $class): bool {
        $method = 'prepare_'.$this->operation;
        $instance = StandardScreenAction::handler($ctl, $class, $method);
        if ($instance === null) return false;
        $known = array_column($this->fields, 'parameter_name');
        $idExists = array_key_exists('id', $this->row);
        $id = $this->row['id'] ?? null;
        $instance->$method($ctl, $this);
        $seen = [];
        foreach ($this->fields as $field) {
            if (!is_array($field)) throw new RuntimeException('フォーム項目は配列で指定してください。');
            $name = $field['parameter_name'] ?? null;
            if (!is_string($name) || !in_array($name, $known, true) || isset($seen[$name])) {
                throw new RuntimeException('フォーム項目は元の画面項目から重複なく指定してください。');
            }
            if (isset($field['options']) && !is_array($field['options'])) throw new RuntimeException('選択肢は配列で指定してください。');
            $seen[$name] = true;
        }
        if ($idExists) $this->row['id'] = $id; else unset($this->row['id']);
        if ($this->child !== null) $this->row[$this->child['parent_field']] = $this->child['parent_id'];
        return true;
    }
}
