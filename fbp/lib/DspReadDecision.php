<?php

final class DspReadDecision {
    public function __construct(
        public readonly bool $visible,
        public readonly ?array $fields = null
    ) {
        if ($fields !== null) {
            foreach ($fields as $field) {
                if (!is_string($field) || $field === '') throw new InvalidArgumentException('Invalid DSP field');
            }
        }
    }

    public function project(array $row): array {
        return $this->fields === null ? $row : array_intersect_key($row, array_flip($this->fields));
    }
}
