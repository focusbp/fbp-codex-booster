<?php
/** Screen choices for Excel-origin notes. Column identities and business rows stay intact. */
final class ExcelStandardLayout {
    public const SCREENS=['add'=>'追加','edit'=>'編集','list'=>'一覧','delete'=>'削除'];
    public static function candidates(array $config): array {
        $fields=[];
        foreach (ExcelGrid::orderedNames($config) as $name) {
            if (!isset($config['columns'][$name])) continue;
            $column=ExcelGrid::column($name,$config['columns'][$name]);
            if (empty($column['generated']) && trim($column['title'])==='' && $column['mode']==='input') continue;
            $fields[$name]=ExcelGrid::field($name,$column);
        }
        return $fields;
    }
    public static function screens(array $input,array $config): array {
        $fields=self::candidates($config);$result=[];
        if (!$fields) throw new RuntimeException('変換する項目がありません。先に列の項目名を設定してください。');
        if (array_diff(array_keys($input),array_merge(array_keys(self::SCREENS),['search']))) throw new RuntimeException('画面の指定が不正です。');
        foreach (self::SCREENS as $screen=>$label) {
            $names=$input[$screen]??null;
            if (!is_array($names)) throw new RuntimeException($label.'の項目指定が不正です。');
            foreach ($names as $name) if (!is_string($name) || !isset($fields[$name])) throw new RuntimeException('選択項目が変更されています。最初から確認してください。');
            if (count(array_unique($names))!==count($names)) throw new RuntimeException($label.'の項目指定が不正です。');
            $result[$screen]=array_values(array_filter(array_keys($fields),fn($name)=>in_array($name,$names,true)));
        }
        if (!$result['list']) throw new RuntimeException('一覧には1項目以上選択してください。');
        $result['search']=array_values(array_filter($result['list'],fn($n)=>$fields[$n]['excel_mode']==='input'
            && !in_array($fields[$n]['type'],['textarea'],true)));
        return $result;
    }
    public static function apply(Controller $ctl,int $id,string $name,array $screens,int $revision,string $key): array {
        $notes=$ctl->db('db','db');$settings=$ctl->db('settings','excel_grid');$ctl->db('setting','setting');
        $note=$notes->get($id);$config=ExcelGrid::config($ctl,$name);
        if (!$note || ($note['tb_name']??'')!==$name || !empty($note['parent_tb_id']) || (int)$note['screen_build_type']!==0)
            throw new RuntimeException('対象ノートを確認できません。');
        ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
        if (!preg_match('/^[a-f0-9]{48}$/D',$key)) throw new RuntimeException('変換受付番号が不正です。');
        $screens=self::screens($screens,$config);
        $own=($config['standard_request_key']??'')===$key;
        if ((int)$note['list_type']===0 && $own && ($config['standard_screens']??[])===$screens) return $note;
        if ((int)$note['list_type']!==4) throw new RuntimeException('このノートは既に変換されています。');
        if (!$own && (int)$config['revision']!==$revision) throw new RuntimeException('列設定が変更されました。変換リンクから項目を選び直してください。');
        if ($own && ((int)$config['revision']!==$revision+1 || ($config['standard_screens']??[])!==$screens))
            throw new RuntimeException('列設定が変更されました。変換リンクから項目を選び直してください。');
        if (!$own) {
            $columns=$config['columns'];
            foreach (self::candidates($config) as $fieldName=>$field) $columns[$fieldName]['generated']=true;
            ExcelGrid::saveConfig($ctl,$name,$columns,ExcelGrid::orderedNames($config),$screens,$key);
        }
        // Publish the new pattern last. A retry can finish a saved layout after interruption.
        $note['list_type']=0;$note['sortkey']='excel_sort';$note['sort_order']=SORT_ASC;
        $notes->update($note);
        return $note;
    }
    public static function writablePost(array $post,array $fields): array {
        foreach ($fields as $field) if (($field['excel_mode']??'input')!=='input') unset($post[$field['parameter_name']]);
        return $post;
    }
}
