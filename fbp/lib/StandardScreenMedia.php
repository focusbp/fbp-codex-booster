<?php

/** Management-only attachment resolution. No path tokens or UI changes. */
final class StandardScreenMedia {
    private const SCREENS = [
        'list' => ['rows', 'list'],
        'list_on_side' => ['rows_child', 'list_on_side'],
        'edit' => ['edit', 'list'],
        'delete' => ['delete', 'list'],
        'edit_child' => ['edit_child', 'list_on_side'],
        'delete_child' => ['delete_child', 'list_on_side'],
    ];

    /** Called only by the framework's Standard Screen controller. */
    public static function renderContext(array $note, string $function): ?array {
        if ((int)($note['screen_build_type'] ?? 0) !== 0 || (int)($note['list_type'] ?? 0) === 4) return null;
        $screens = ['rows'=>'list', 'rows_weekly_calendar'=>'list', 'unassigned_tasks'=>'list',
            'rows_child'=>'list_on_side', 'edit'=>'edit', 'edit_child'=>'edit_child',
            'delete'=>'delete', 'delete_child'=>'delete_child', 'add'=>'add', 'add_child'=>'add'];
        $screen = (int)($note['list_type'] ?? 0) === 3 ? 'edit' : ($screens[$function] ?? null);
        return $screen === null ? null : ['db_id'=>(int)$note['id'], 'screen'=>$screen];
    }

    /** Presentation metadata only: no saved paths, old tokens or fullpath. */
    public static function display(Controller $ctl, array $context, array $field, array $row): array {
        $result = ['filename'=>'', 'image_url'=>'', 'thumbnail_url'=>'', 'download_url'=>''];
        $fileId = $row[$field['parameter_name']] ?? null;
        if (empty($fileId)) return $result;
        $file = $ctl->db('file', 'upload')->get($fileId);
        if (!$file) return $result;
        $result['filename'] = (string)($file['filename'] ?? '');
        $id = $row['id'] ?? '';
        if (!is_int($id) && (!is_string($id) || !ctype_digit($id))) $id = $ctl->decrypt((string)$id);
        if (!$id || $context['screen'] === 'add') return $result;
        $args = ['db_id'=>$context['db_id'], 'row_id'=>$id, 'field'=>$field['parameter_name'],
            'screen'=>$context['screen'], 'file_id'=>$fileId];
        $result['download_url'] = self::url($ctl, $args, false);
        if (($field['type'] ?? '') === 'image') {
            $result['image_url'] = self::url($ctl, $args, true);
            $result['thumbnail_url'] = self::url($ctl, $args+['variant'=>'thumbnail'], true);
        }
        return $result;
    }

    /** These are identifiers, not credentials; resolve() rechecks every request. */
    public static function url(Controller $ctl, array $request, bool $image = true): string {
        $args = self::arguments($request);
        return $ctl->get_APP_URL('standard_screen_media', $image ? 'view_image' : 'download_file', $args);
    }

    /** Returns server-only metadata. Never assign this result to a template. */
    public static function resolve(Controller $ctl, array $request, bool $image): array {
        if (!$ctl->verify_management_session()) throw new StandardScreenMediaDenied(401);
        $args = self::arguments($request);
        $definitions = $ctl->db('db', 'db');
        $note = $definitions->get($args['db_id']);
        self::standardNote($note);
        $table = $note['tb_name'];
        $fieldScreen = str_replace('_child', '', $args['screen']);
        $fields = $ctl->get_field_list($table, $fieldScreen);
        $field = null;
        foreach ($fields as $candidate) {
            if (($candidate['parameter_name'] ?? null) === $args['field']) $field = $candidate;
        }
        if (!$field || !in_array($field['type'] ?? '', ['file', 'image'], true)
            || ($image && $field['type'] !== 'image')
            || ($args['variant'] === 'thumbnail' && (!$image || $field['type'] !== 'image'))) {
            throw new StandardScreenMediaDenied();
        }

        // Open all known dependencies before reading business records. DSP and
        // visibility filters retain their own dependency-opening contracts.
        $uploads = $ctl->db('file', 'upload');
        $chain = [];
        $seen = [];
        $current = $note;
        while ($current) {
            self::standardNote($current);
            $id = (int)$current['id'];
            if (isset($seen[$id]) || count($chain) >= 32) throw new StandardScreenMediaDenied();
            $seen[$id] = true;
            $chain[] = [$current, $ctl->db($current['tb_name'], 'common')];
            $parent = (int)($current['parent_tb_id'] ?? 0);
            if (!$parent) break;
            $current = $definitions->get($parent);
            if (!$current) throw new StandardScreenMediaDenied();
        }

        $recordId = $args['row_id'];
        $record = null;
        foreach ($chain as $index => [$setting, $db]) {
            $screen = $index === 0 ? $args['screen'] : 'list';
            $row = self::readableRow($ctl, $setting, $db, $recordId, $screen,
                $index === 0 ? $args['field'] : '');
            if ($index === 0) $record = $row;
            if ($index < count($chain) - 1) {
                $recordId = self::positiveId($row['parent_id'] ?? null);
            }
        }
        // Masked fields, stale links after replacement and unrelated file IDs
        // cannot select an arbitrary upload.
        if (self::positiveId($record[$args['field']] ?? null) !== $args['file_id']) {
            throw new StandardScreenMediaDenied();
        }
        $file = $uploads->get($args['file_id']);
        if (!$file || ($file['table_identifer'] ?? '') !== $chain[0][1]->get_identifier()
            || (string)($file['row_id'] ?? '') !== (string)$args['row_id']) {
            throw new StandardScreenMediaDenied();
        }
        $path = $args['variant'] === 'thumbnail'
            ? (($file['path_th'] ?? '') ?: ($file['path'] ?? '')) : ($file['path'] ?? '');
        $path = self::storedPath($ctl, $path);
        if ($image) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ctl->dirs->datadir . '/upload/' . $path);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'image/avif', 'image/bmp', 'image/x-icon'], true)) throw new StandardScreenMediaDenied();
        }
        return ['path' => $path, 'filename' => (string)($file['filename'] ?? ''), 'type' => $field['type']];
    }

    private static function arguments(array $request): array {
        $field = $request['field'] ?? null;
        $screen = $request['screen'] ?? null;
        $variant = $request['variant'] ?? 'original';
        if (!is_string($field) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $field)
            || !is_string($screen) || !isset(self::SCREENS[$screen])
            || !is_string($variant) || !in_array($variant, ['original', 'thumbnail'], true)) {
            throw new StandardScreenMediaDenied();
        }
        return ['db_id' => self::positiveId($request['db_id'] ?? null),
            'row_id' => self::positiveId($request['row_id'] ?? null), 'field' => $field,
            'screen' => $screen, 'file_id' => self::positiveId($request['file_id'] ?? null), 'variant' => $variant];
    }

    private static function positiveId($value): int {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/\A[1-9][0-9]*\z/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT) === false) throw new StandardScreenMediaDenied();
        return (int)$value;
    }

    private static function standardNote($note): void {
        if (!is_array($note) || !isset($note['id'], $note['tb_name'])
            || !is_string($note['tb_name']) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $note['tb_name'])
            || (int)($note['screen_build_type'] ?? 0) !== 0 || (int)($note['list_type'] ?? 0) === 4) {
            throw new StandardScreenMediaDenied();
        }
    }

    private static function readableRow(Controller $ctl, array $note, $db, int $id, string $screen, string $field): array {
        [$operation, $context] = self::SCREENS[$screen];
        if ((int)($note['list_type'] ?? 0) === 3) $operation = 'page';
        $beforePost = $_POST;
        $beforeGet = $_GET;
        try {
            // Existing application guards can inspect the standard screen's
            // target. Never expose arbitrary media-request parameters to them.
            $_POST = $_GET = ['class' => 'db_exe', 'function' => $operation,
                'db_id' => $note['id'], 'id' => $ctl->encrypt((string)$id)];
            if (!$ctl->authorize_management_access('db_exe', $operation)) throw new StandardScreenMediaDenied();
            $filter = self::visibilityFilter($ctl, $note['tb_name']);
            if ($filter && method_exists($filter, 'can_access')
                && !$filter->can_access($ctl, $operation, $note['tb_name'], $note)) throw new StandardScreenMediaDenied();
            $names = ['id']; $values = [$id]; $patterns = ['='];
            if ($filter && method_exists($filter, 'get_filter_conditions')) {
                $conditions = $filter->get_filter_conditions($ctl, $context);
                if (!is_array($conditions)) throw new StandardScreenMediaDenied();
                foreach ($conditions as $condition) {
                    if (!is_array($condition) || !is_string($condition['field'] ?? null)
                        || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $condition['field'])
                        || !array_key_exists('value', $condition)) throw new StandardScreenMediaDenied();
                    $names[] = $condition['field']; $values[] = $condition['value'];
                    $patterns[] = $condition['match_pattern'] ?? '=';
                }
            }
            // select preserves zero-valued conditions; FFM applies DSP to the
            // query and returned fields. User-entered search filters are absent.
            $rows = $db->select($names, $values, $patterns, 'AND', null, SORT_ASC, 1);
            $row = $rows[0] ?? null;
            if (!is_array($row) || (int)($row['id'] ?? 0) !== $id) throw new StandardScreenMediaDenied();
            if ($filter && method_exists($filter, 'can_read_attachment')
                && !$filter->can_read_attachment($ctl, $row, $field, $note)) throw new StandardScreenMediaDenied();
            return $row;
        } finally {
            $_POST = $beforePost;
            $_GET = $beforeGet;
        }
    }

    private static function visibilityFilter(Controller $ctl, string $table) {
        $class = $table . '_visibility_filter';
        $path = $ctl->dirs->appdir_user . '/' . $class . '/' . $class . '.php';
        if (!is_file($path)) return null;
        require_once $path;
        if (!class_exists($class, false)) throw new StandardScreenMediaDenied();
        return new $class();
    }

    private static function storedPath(Controller $ctl, $path): string {
        if (!is_string($path) || $path === '' || $path[0] === '/' || strpos($path, "\0") !== false
            || strpos($path, '\\') !== false || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new StandardScreenMediaDenied();
        }
        $root = realpath($ctl->dirs->datadir . '/upload');
        $real = realpath($ctl->dirs->datadir . '/upload/' . $path);
        if ($root === false || $real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0
            || !is_file($real) || !is_readable($real)) throw new StandardScreenMediaDenied();
        return $path;
    }
}

final class StandardScreenMediaDenied extends RuntimeException {
    public int $status;
    public function __construct(int $status = 404) {
        $this->status = $status;
        parent::__construct('Attachment unavailable');
    }
}
