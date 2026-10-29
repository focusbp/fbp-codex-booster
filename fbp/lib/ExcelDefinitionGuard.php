<?php

/** Dedicated import validation and compatibility hooks for Excel definitions. */
final class ExcelDefinitionReadOnly extends RuntimeException {
    public const ERROR_CODE = 'excel_definition_read_only';
    public function __construct(string $message = 'このExcel定義操作は専用の実行経路から行ってください。') { parent::__construct($message); }
}

final class ExcelDefinitionGuard {
    private static ?array $import = null;
    private static ?array $noteImport = null;
    private static ?array $standardImport = null;
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
    // 0/empty preserves ON for existing projects; 1 is explicit OFF.
    public static function noteCreationEnabled(array $setting): bool {
        return !in_array($setting['spreadsheet_note_add_disabled'] ?? null, [1, '1'], true);
    }
    public static function assertNoteCreationAllowed(Controller $ctl): void {
        if (!$ctl->is_app_admin()) throw new RuntimeException('ノートの追加は管理者のみ実行できます。');
        $setting = $ctl->db('setting', 'setting')->get(1) ?: [];
        if (!self::noteCreationEnabled($setting)) throw new RuntimeException('Excel風ノートの追加はシステム設定で無効になっています。');
    }
    public static function assertEditable(string $dataRoot): void {
        // Both runtime modes permit local definition edits. Keep invalid-mode detection.
        self::isDeveloper($dataRoot);
    }
    /** Compatibility hook: ordinary FFM authorization still applies in both modes. */
    public static function write(string $table, string $directory, callable $callback): mixed {
        return $callback(null);
    }
    public static function check(?array $context, string $operation, ?array $before, ?array $after): void {
        // Developer Mode now permits the same definition writes as Production Mode.
        // Production sync/import validation below remains separate from ordinary writes.
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
            if (($current['tb_name'] ?? '') === $note['tb_name'] && (int)($current['screen_build_type']??0)===0 && empty($current['parent_tb_id'])) {
                if ((int)$current['list_type']!==4) { $current['list_type']=4; $db->update($current); }
                return ['id'=>(int)$note['id'], 'created'=>false];
            }
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

    /** Import only a production-confirmed pattern change; retain runtime-owned columns. */
    public static function importStandardNote(string $dataRoot, fixed_file_manager $db, array $note, array $snapshot): array {
        if (PHP_SAPI !== 'cli' || !defined('FBP_EXCEL_STANDARD_IMPORT_COMMAND') || FBP_EXCEL_STANDARD_IMPORT_COMMAND !== true) throw new ExcelDefinitionReadOnly();
        $root = realpath($dataRoot);
        if ($root === false || realpath($db->get_path_dat()) !== $root.'/db/db.dat') throw new RuntimeException('取込み先のノート定義DBが一致しません。');
        $name = (string)($note['tb_name'] ?? '');
        if (($snapshot['ok'] ?? null) !== true || ($snapshot['schema_version'] ?? null) !== 1
            || ($snapshot['tb_name'] ?? null) !== $name || (int)($snapshot['list_type'] ?? -1) !== 0
            || !preg_match('/^[a-z][a-z0-9_]*$/', $name)
            || (int)($note['id'] ?? 0) < 1 || (int)($note['screen_build_type'] ?? -1) !== 0
            || (int)($note['list_type'] ?? -1) !== 0 || !empty($note['parent_tb_id'])) throw new RuntimeException('本番で検索＋一覧へ変更済みの親ノート定義を指定してください。');
        return self::references($root, function ($rows, $refs) use ($root, $db, $note, $snapshot, $name) {
            $current = $db->get((int)$note['id']);
            if (!$current || ($current['tb_name'] ?? '') !== $name || (int)$current['screen_build_type'] !== 0
                || !in_array((int)$current['list_type'], [0,4], true) || !empty($current['parent_tb_id'])) throw new RuntimeException('既存ノートのID・内部名・画面パターンが一致しません。');
            $matches = array_values(array_filter($rows['excel_grid/settings'] ?? [], fn($cfg) => ($cfg['tb_name'] ?? '') === $name));
            $config = count($matches) === 1 ? $refs['excel_grid/settings']->get((int)$matches[0]['id']) : [];
            if (count($matches) !== 1 || !is_array($snapshot['columns'] ?? null)
                || ($config['columns'] ?? null) != $snapshot['columns']
                || ExcelGrid::orderedNames($config) !== ($snapshot['column_order'] ?? null)) throw new RuntimeException('本番と開発環境の列定義が一致しません。変更前の列定義を先に同期してください。');
            if ((int)$current['list_type'] === 0) return ['id'=>(int)$current['id'], 'changed'=>false];
            if (self::$standardImport !== null) throw new RuntimeException('画面パターン取込みの再入はできません。');
            $after = $current; $after['list_type'] = 0;
            self::$standardImport = ['root'=>$root, 'content'=>$after];
            try { $db->update($after); }
            finally { self::$standardImport = null; }
            return ['id'=>(int)$current['id'], 'changed'=>true];
        });
    }

    /** Dedicated CLI-only import of a verified production layout and pattern. */
    public static function importLayoutNote(Controller $ctl,array $source,array $snapshot): array {
        if (PHP_SAPI!=='cli' || (!defined('FBP_EXCEL_CONVERSION_IMPORT_COMMAND') && !defined('FBP_EXCEL_STANDARD_IMPORT_COMMAND')) || !defined('CLI_APP_CALL')) throw new ExcelDefinitionReadOnly();
        $db=$ctl->db('db','db');$current=$db->get((int)($source['id']??0));
        if (!$current || ($current['tb_name']??'')!==($source['tb_name']??null) || !empty($current['parent_tb_id'])
            || (int)$current['screen_build_type']!==0 || !in_array((int)$current['list_type'],[0,1,2,3,4],true)
            || !empty($source['parent_tb_id']) || (int)$source['screen_build_type']!==0
            || !in_array((int)$source['list_type'],[0,4],true) || (int)$snapshot['list_type']!==(int)$source['list_type']) throw new RuntimeException('本番と開発環境のノートID・内部名・画面形式が一致しません。');
        $config=ExcelGrid::config($ctl,$current['tb_name']);
        if (($config['columns']??null)!=$snapshot['columns'] || ($config['standard_screens']??[])!=($snapshot['standard_screens']??[])
            || ($config['standard_request_key']??'')!==($snapshot['standard_request_key']??'')) throw new RuntimeException('取込み済みの表示定義が一致しません。');
        $after=$current;$after['list_type']=(int)$source['list_type'];$after['sortkey']='excel_sort';$after['sort_order']=SORT_ASC;
        if (self::$standardImport!==null) throw new RuntimeException('画面形式取込みの再入はできません。');
        self::$standardImport=['root'=>realpath($ctl->dirs->datadir),'content'=>$after];
        try { $db->update($after); } finally { self::$standardImport=null; }
        return $after;
    }

    /** Internal import entry; not a general-purpose bypass for app_call/method_call. */
    public static function import(Controller $ctl, string $table, array $columns, array $order, ?array $screens=null, ?string $key=null): array {
        if (PHP_SAPI !== 'cli' || !defined('FBP_EXCEL_IMPORT_COMMAND') || FBP_EXCEL_IMPORT_COMMAND !== true || !defined('CLI_APP_CALL')) throw new ExcelDefinitionReadOnly();
        $root = realpath($ctl->dirs->datadir) ?: $ctl->dirs->datadir;
        if (self::$import !== null) throw new RuntimeException('設定取込みの再入はできません。');
        $current=ExcelGrid::config($ctl,$table);
        $screens=$screens??($current['standard_screens']??[]);$key=$key??($current['standard_request_key']??'');
        self::$import = ['root' => $root, 'content' => ['tb_name' => $table, 'columns' => $columns, 'column_order' => $order, 'standard_screens'=>$screens, 'standard_request_key'=>$key]];
        try { return ExcelGrid::saveConfig($ctl, $table, $columns, $order, $screens, $key); }
        finally { self::$import = null; }
    }
}
