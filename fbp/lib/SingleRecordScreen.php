<?php

/** Shared policy for the Standard Screen single-record pattern (UI and CLI). */
final class SingleRecordScreen {
    public static function is_single(array $table): bool {
        return (int) ($table['screen_build_type'] ?? 0) === 0 && (int) ($table['list_type'] ?? 0) === 3;
    }

    public static function configuration_errors(array $table, callable $open_table, callable $buttons): array {
        if ((int) ($table['list_type'] ?? 0) !== 3) {
            return [];
        }
        if (!self::is_single($table)) {
            return ['list_type' => 'db.single.standard_only'];
        }
        if (!empty($table['parent_tb_id'])) {
            return ['parent_tb_id' => 'db.single.parent_not_supported'];
        }
        if (!empty($table['id'])) {
            if (count(self::rows($open_table((string) $table['tb_name']))) > 1) {
                return ['list_type' => 'db.single.multiple_records'];
            }
            foreach ($buttons((string) $table['tb_name']) as $button) {
                if (!self::allows_button($table, $button['place'] ?? 0)) {
                    return ['list_type' => 'db.single.top_buttons_only'];
                }
            }
        }
        return [];
    }

    public static function allows_button(array $table, $place): bool {
        return !self::is_single($table) || (string) $place === '0';
    }

    /** Read at most two active records, ignoring search and visibility filters. */
    public static function rows($db): array {
        return array_values($db->filter([], [], true, 'AND', 'id', SORT_ASC, 2));
    }
}
