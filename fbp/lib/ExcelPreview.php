<?php

/** A per-window view preference; never part of note definitions or release data. */
final class ExcelPreview {
    private static function key(Controller $ctl, array $note): string {
        return 'excel_preview_'.(int)$ctl->get_login_user_id().'_'.(int)$note['id'];
    }
    public static function available(Controller $ctl, array $note): bool {
        return ExcelGrid::isSpreadsheet($note) && ExcelDefinitionGuard::isDeveloper($ctl->dirs->datadir)
            && (bool)ExcelGrid::config($ctl, $note['tb_name']);
    }
    public static function effective(Controller $ctl, array $note): array {
        $key = self::key($ctl, $note);
        $state = $ctl->get_session($key);
        if (!self::available($ctl, $note)) { $ctl->set_session($key, null); return $note; }
        $cfg = ExcelGrid::config($ctl, $note['tb_name']);
        if (is_array($state) && ($state['table'] ?? '') === $note['tb_name']
            && (int)($state['revision'] ?? -1) === (int)$cfg['revision'] && ($state['pattern'] ?? null) === 0) {
            $note['list_type'] = 0;
            // Empty ordering is valid for a spreadsheet, but standard filtering needs a key.
            if (empty($note['sortkey'])) $note['sortkey'] = 'id';
            if (!in_array((int)($note['sort_order'] ?? 0), [SORT_ASC, SORT_DESC], true)) $note['sort_order'] = SORT_ASC;
        } else $ctl->set_session($key, null);
        return $note;
    }
    public static function set(Controller $ctl, array $note, bool $search): void {
        if (!self::available($ctl, $note)) throw new RuntimeException('取り込み済みのExcel風ノートをDeveloper Modeで開いてください。');
        $cfg = ExcelGrid::config($ctl, $note['tb_name']);
        $ctl->set_session(self::key($ctl, $note), $search ? ['table'=>$note['tb_name'], 'revision'=>(int)$cfg['revision'], 'pattern'=>0] : null);
    }
}
