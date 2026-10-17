<?php

/** Framework invariant, independent of project DSP and caller privileges. */
final class ExcelDefinitionReadOnly extends RuntimeException {
    public const ERROR_CODE = 'excel_definition_read_only';
    public function __construct(string $message = 'Developer ModeではExcel風ノートの作成・列設定の変更はできません。本番の列設定を取り込んでください。') { parent::__construct($message); }
}

final class ExcelDefinitionGuard {
    private static ?array $import = null;
    private static ?array $noteImport = null;
    private static bool $reading = false;
    public static function readingContext(): bool { return self::$reading; }

    private static function root(string $directory): string {
        return realpath(dirname(rtrim($directory, '/'))) ?: dirname(rtrim($directory, '/'));
    }
    public static function target(string $table, string $directory): ?string {
        $directory = realpath($directory) ?: $directory;
        $class = basename(rtrim($directory, '/'));
        return in_array($class.'/'.$table, ['db/db', 'db/db_fields', 'excel_grid/settings'], true) ? $class.'/'.$table : null;
    }
    /** Open all dependencies first; FFM restores lock order and refreshes headers. */
    private static function references(string $root, callable $callback): mixed {
        $opened = []; $refs = [];
        try {
            foreach (['db/db', 'excel_grid/settings', 'setting/setting'] as $name) {
                $path = $root.'/'.$name.'.dat';
                if (!is_file($path)) continue;
                $path = realpath($path);
                foreach ($GLOBALS['lock_class_arr'] ?? [] as $ffm) {
                    if (realpath($ffm->path_dat) === $path && is_resource($ffm->hf)) { $refs[$name] = $ffm; break; }
                }
                if (!isset($refs[$name])) $opened[] = $refs[$name] = fixed_file_manager::open_dat_header_readonly($path);
            }
            // No further database opens inside the comparison callback.
            $rows = [];
            self::$reading = true;
            try { foreach ($refs as $name => $ffm) $rows[$name] = $ffm->excel_guard_snapshot(); }
            finally { self::$reading = false; }
            return $callback($rows, $refs);
        } finally { foreach (array_reverse($opened) as $ffm) $ffm->close(); }
    }
    private static function developer(array $rows): bool {
        $mode = $rows['setting/setting'][1]['force_testmode'] ?? null;
        if (!in_array($mode, [0, 1, '0', '1'], true)) throw new ExcelDefinitionReadOnly('モード設定を確認できないため、Excel定義の更新を停止しました。');
        return (int)$mode === 1;
    }
    public static function isDeveloper(string $dataRoot): bool {
        return self::references(realpath($dataRoot) ?: $dataRoot, fn($rows) => self::developer($rows));
    }
    public static function assertEditable(string $dataRoot): void {
        if (self::isDeveloper($dataRoot)) throw new ExcelDefinitionReadOnly();
    }
    public static function write(string $table, string $directory, callable $callback): mixed {
        $target = self::target($table, $directory);
        if ($target === null) return $callback(null);
        return self::references(self::root($directory), fn($rows, $refs) => $callback(['target' => $target, 'root' => self::root($directory), 'refs' => $refs]));
    }
    private static function changed(array $before, array $after): bool {
        // FFM normalizes numeric fields and JSON array keys on disk.
        return $before != $after;
    }
    public static function check(?array $context, string $operation, ?array $before, ?array $after): void {
        if ($context === null) return;
        // DSP preparation may have reordered locks; reread metadata under current locks.
        $rows = [];
        self::$reading = true;
        try { foreach ($context['refs'] as $name => $ffm) $rows[$name] = $ffm->excel_guard_snapshot(); }
        finally { self::$reading = false; }
        if (!self::developer($rows) || ($before !== null && $after !== null && !self::changed($before, $after))) return;
        $context['rows'] = $rows;
        $target = $context['target'];
        if ($operation === 'clear') throw new ExcelDefinitionReadOnly();
        if ($target === 'db/db') {
            if ($operation === 'insert' && self::$noteImport !== null && self::$noteImport['root'] === $context['root']) {
                $candidate = $after ?? []; unset($candidate['id']);
                if ($candidate == self::$noteImport['content']) return;
            }
            if ($operation === 'delete') return;
            $oldExcel = (int)($before['list_type'] ?? -1) === 4;
            $newExcel = (int)($after['list_type'] ?? -1) === 4;
            if ((!$oldExcel && $newExcel) || ($oldExcel && (!$newExcel || (int)($after['screen_build_type'] ?? 0) !== 0))) throw new ExcelDefinitionReadOnly();
            foreach ($context['rows']['excel_grid/settings'] ?? [] as $cfg) {
                if (($cfg['tb_name'] ?? '') === ($before['tb_name'] ?? '') && ($after['tb_name'] ?? '') !== ($before['tb_name'] ?? '')) throw new ExcelDefinitionReadOnly();
            }
        } elseif ($target === 'excel_grid/settings') {
            $allowed = self::$import;
            $candidate = array_intersect_key($after ?? [], array_flip(['tb_name', 'columns', 'column_order']));
            if ($operation !== 'delete' && $allowed !== null && $allowed['root'] === $context['root'] && $candidate == $allowed['content']) return;
            throw new ExcelDefinitionReadOnly();
        } else {
            foreach ([$before, $after] as $field) {
                if (!$field || !preg_match('/^(?:[a-z]|a[a-z])$/', (string)($field['parameter_name'] ?? ''))) continue;
                $note = $context['rows']['db/db'][(int)($field['db_id'] ?? 0)] ?? [];
                if ((int)($note['list_type'] ?? -1) === 4) throw new ExcelDefinitionReadOnly();
                foreach ($context['rows']['excel_grid/settings'] ?? [] as $cfg) if (($cfg['tb_name'] ?? '') === ($note['tb_name'] ?? '')) throw new ExcelDefinitionReadOnly();
            }
        }
    }
    /** Import an existing production spreadsheet note identity, never renumber local notes. */
    public static function importNote(string $dataRoot, fixed_file_manager $db, array $note): array {
        if (PHP_SAPI !== 'cli' || !defined('FBP_EXCEL_NOTE_IMPORT_COMMAND') || FBP_EXCEL_NOTE_IMPORT_COMMAND !== true) throw new ExcelDefinitionReadOnly();
        $root = realpath($dataRoot);
        if ($root === false || realpath($db->get_path_dat()) !== $root.'/db/db.dat') throw new RuntimeException('取込み先のノート定義DBが一致しません。');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', (string)($note['tb_name'] ?? '')) || !ExcelGrid::isSpreadsheet($note) || !empty($note['parent_tb_id']) || (int)($note['id'] ?? 0)<1) throw new RuntimeException('本番のExcel風親ノート定義を指定してください。');
        $existing = $db->get((int)$note['id']);
        $byName = $db->select('tb_name', $note['tb_name']);
        if ($existing !== null || $byName) {
            unset($note['_id_enc']); $current = $existing ?? []; unset($current['_id_enc']);
            if (($current['tb_name'] ?? '') === $note['tb_name'] && ExcelGrid::isSpreadsheet($current) && !$current['parent_tb_id']) return ['id'=>(int)$note['id'], 'created'=>false];
            throw new RuntimeException('ノートIDまたは名前が既存定義と衝突しています。上書きせず停止しました。');
        }
        $sourceId = (int)$note['id'];
        $maxId = (int)$db->get_header_info()['maxid'];
        if ($sourceId > $maxId+1) throw new RuntimeException('本番ノートIDと次の採番が一致しません。定義の同期が必要です。');
        unset($note['id'], $note['_id_enc']);
        if (self::$noteImport !== null) throw new RuntimeException('ノート定義取込みの再入はできません。');
        self::$noteImport = ['root'=>$root, 'content'=>$note];
        try {
            if ($sourceId <= $maxId) { $db->restore_deleted_record(['id'=>$sourceId]+$note); $id=$sourceId; }
            else $id=$db->insert($note);
            return ['id'=>$id,'created'=>true];
        }
        finally { self::$noteImport = null; }
    }

    /** Internal import entry; not a general-purpose bypass for app_call/method_call. */
    public static function import(Controller $ctl, string $table, array $columns, array $order): array {
        if (PHP_SAPI !== 'cli' || !defined('FBP_EXCEL_IMPORT_COMMAND') || FBP_EXCEL_IMPORT_COMMAND !== true || !defined('CLI_APP_CALL')) throw new ExcelDefinitionReadOnly();
        $root = realpath($ctl->dirs->datadir) ?: $ctl->dirs->datadir;
        if (self::$import !== null) throw new RuntimeException('設定取込みの再入はできません。');
        self::$import = ['root' => $root, 'content' => ['tb_name' => $table, 'columns' => $columns, 'column_order' => $order]];
        try { return ExcelGrid::saveConfig($ctl, $table, $columns, $order); }
        finally { self::$import = null; }
    }
}
