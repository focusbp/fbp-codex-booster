<?php
require_once __DIR__ . '/ExcelFormula.php';
require_once __DIR__ . '/ExcelStandardLayout.php';
/** Runtime-owned definitions. This data directory is deliberately outside release datasets. */
final class ExcelGrid {
    public const PATTERN = 4;
    public const DEFAULT_COLUMN_WIDTH = 132;
    public const MIN_COLUMN_WIDTH = 64;
    public const MAX_COLUMN_WIDTH = 1200;
    public static function columnWidth($value): int {
        $width=filter_var($value,FILTER_VALIDATE_INT);
        if ($width===false || $width<self::MIN_COLUMN_WIDTH || $width>self::MAX_COLUMN_WIDTH) throw new InvalidArgumentException('列幅は64〜1200pxで指定してください。');
        return $width;
    }
    public static function isSpreadsheet(array $note): bool {
        return (int)($note['screen_build_type'] ?? 0)===0 && (int)($note['list_type'] ?? 0)===self::PATTERN;
    }
    public static function configurationErrors(array $note): array {
        if ((int)($note['list_type'] ?? 0)!==self::PATTERN) return [];
        if (!self::isSpreadsheet($note)) return ['list_type'=>'スプレッドシートは標準画面で設定してください。'];
        if (!empty($note['parent_tb_id'])) return ['parent_tb_id'=>'スプレッドシートは親ノートで設定してください。'];
        return [];
    }
    public const TYPES = ['text'=>255,'textarea'=>3000,'number'=>24,'float'=>24,'date'=>15,'datetime'=>15,'time'=>8,'year_month'=>15,'dropdown'=>255];
    private static array $preparing = [];
    public static function names(): array { return array_merge(range('a','z'), array_map(fn($c)=>'a'.$c, range('a','z'))); }
    public static function orderedNames(array $config): array {
        $saved=$config['column_order'] ?? [];
        return array_values(array_unique(array_merge(array_values(array_intersect(is_array($saved)?$saved:[],self::names())),self::names())));
    }
    public static function validateOrder(array $order): void {
        if (count($order)!==52 || count(array_unique($order))!==52 || array_diff($order,self::names())) throw new InvalidArgumentException('列順が不正です。');
    }
    public static function config(Controller $ctl, string $table): array {
        if (!is_file($ctl->dirs->datadir . '/excel_grid/settings.dat')) return [];
        return $ctl->db('settings', 'excel_grid')->select('tb_name', $table)[0] ?? [];
    }
    public static function saveConfig(Controller $ctl, string $table, array $columns, ?array $columnOrder = null, ?array $standardScreens = null, ?string $standardKey = null): array {
        $db = $ctl->db('settings', 'excel_grid');
        $db->set_strict_field_length(true);
        $current = $db->select('tb_name', $table)[0] ?? [];
        $row = ['tb_name'=>$table,'columns'=>$columns,'revision'=>(int)($current['revision'] ?? 0)+1];
        $row['standard_screens']=$standardScreens??($current['standard_screens']??[]);
        $row['standard_request_key']=$standardKey??($current['standard_request_key']??'');
        if ($columnOrder!==null) { self::validateOrder($columnOrder); $row['column_order']=$columnOrder; }
        if ($current) { $row['id']=$current['id']; $db->update($row); } else $row['id']=$db->insert($row);
        return $row;
    }
    public static function column(string $name, array $c = []): array {
        $c=array_replace(['parameter_name'=>$name, 'title'=>'', 'type'=>'text', 'length'=>255, 'mode'=>'input', 'formula'=>'', 'digits'=>0, 'rounding'=>'round', 'generated'=>false, 'options'=>[], 'options_order'=>[], 'options_next_value'=>1], $c);
        $c['column_width']=self::columnWidth($c['column_width'] ?? self::DEFAULT_COLUMN_WIDTH);
        $ordered=[];
        foreach ($c['options_order'] as $key) if (array_key_exists($key,$c['options'])) $ordered[$key]=$c['options'][$key];
        $c['options']=$ordered+$c['options'];
        return $c;
    }
    /** Support-owned functions cannot be enabled or disabled by the column editor. */
    public static function userMode(string $type,string $requested,string $current): string {
        if ($current==='function') return 'function';
        if (!in_array($requested,['input','calculation'],true)) throw new InvalidArgumentException('入力方法が不正です。関数はプロジェクトサポートで設定してください。');
        if ($requested==='calculation' && !in_array($type,['number','float'],true)) throw new InvalidArgumentException('「計算」は整数または小数の型にしてください。');
        return $requested;
    }
    /** Values are permanent identities; numeric allocation never follows display order. */
    public static function editOption(array $column,string $action,string $value,string $label,array $order=[]): array {
        $c=self::column('', $column); $options=$c['options'];
        if (in_array($action,['add','edit'],true)) {
            $label=trim($label);
            if ($label==='' || strlen($label)>255) throw new InvalidArgumentException('選択肢を255バイト以内で入力してください。');
        }
        if ($action==='add') {
            $next=max(1,(int)$c['options_next_value']);
            foreach (array_keys($options) as $key) if (ctype_digit((string)$key)) $next=max($next,(int)$key+1);
            if ($next>2147483647) throw new InvalidArgumentException('選択肢の登録上限に達しました。');
            $options[$next]=$label; $c['options_next_value']=$next+1;
        } elseif ($action==='edit' || $action==='delete') {
            if (!array_key_exists($value,$options)) throw new InvalidArgumentException('対象の選択肢がありません。');
            if ($action==='edit') $options[$value]=$label;
            else unset($options[$value]);
        } elseif ($action==='move') {
            $keys=array_map('strval',array_keys($options));
            if (count($order)!==count($keys) || count(array_unique($order,SORT_STRING))!==count($keys) || array_diff($order,$keys)) throw new InvalidArgumentException('選択肢の並び順が不正です。');
            $sorted=[]; foreach ($order as $key) $sorted[$key]=$options[$key]; $options=$sorted;
        } else throw new InvalidArgumentException('選択肢の操作が不正です。');
        $c['options']=$options; $c['options_order']=array_map('strval',array_keys($options));
        return $c;
    }
    public static function optionRows(array $column): array {
        $rows=[];
        foreach (self::column('', $column)['options'] as $value=>$label) $rows[]=['value'=>(string)$value,'label'=>(string)$label];
        return $rows;
    }
    public static function field(string $name, array $c): array {
        $c=self::column($name,$c);
        $f = ['id'=>0,'parameter_name'=>$name,'parameter_title'=>$c['title'] ?: strtoupper($name),'type'=>$c['type'],'length'=>$c['length'],
              'validation'=>0,'constant_array_name'=>'','parameter_description'=>'','default_value'=>'','duplicate_check'=>0,'format_check'=>'',
              'display_format'=>0,'title_color'=>'','options'=>$c['options'] ?? [],'colors'=>[],'is_table_dropdown'=>false,'max_bytes'=>$c['length'],
              'excel_mode'=>$c['mode'],'column_width'=>$c['column_width'],'sort'=>array_search($name,self::names(),true)];
        return $f;
    }
    public static function fields(Controller $ctl, string $table, array $base, $screen = null): array {
        $config = self::config($ctl,$table);
        if (!$config) return $base;
        $columns = $config['columns'] ?: []; $map=[];
        foreach ($base as $f) $map[$f['parameter_name']]=$f;
        foreach (self::names() as $name) {
            if (empty($columns[$name]['generated'])) continue;
            $f=self::field($name,$columns[$name]);
            // Search remains an existing standard-screen feature, outside Excel mode.
            $map[$name]=$f;
        }
        if (is_array($screen)) return array_values(array_intersect_key($map,array_flip($screen)));
        return array_values($map);
    }
    /** Alternate format directory prevents deployment of stale test-generated column formats. */
    public static function formatDir(Controller $ctl, string $table, string $baseDir): string {
        $key = spl_object_id($ctl).'/'.$table;
        if (isset(self::$preparing[$key])) return $baseDir;
        self::$preparing[$key]=true;
        try {
            $config=self::config($ctl,$table);
            if (!$config) return $baseDir;
            return self::writeFormat($ctl->dirs->datadir,$table,$baseDir,$config);
        } finally { unset(self::$preparing[$key]); }
    }
    public static function writeFormat(string $dataRoot,string $table,string $baseDir,array $config): string {
            $lines=[];
            $base=$baseDir.'/'.$table.'.fmt';
            foreach (is_file($base) ? file($base, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : ['id,24,N'] as $line) {
                $parts=explode(',',$line); $lines[$parts[0]]=$line;
            }
            foreach (($config['columns'] ?: []) as $name=>$c) if (!empty($c['generated'])) {
                // Excel columns preserve empty separately from numeric zero; semantic types remain in the definitions.
                $type = 'T';
                $lines[$name]=$name.','.(int)$c['length'].','.$type;
            }
            $lines['excel_sort']='excel_sort,24,N';
            $dir=$dataRoot.'/excel_grid/fmt';
            if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('項目形式の作成に失敗しました。');
            $text=implode("\n",$lines)."\n"; $path=$dir.'/'.$table.'.fmt';
            if (!is_file($path) || file_get_contents($path)!==$text) {
                $temp=$path.'.'.bin2hex(random_bytes(6));
                if (file_put_contents($temp,$text)===false || !rename($temp,$path)) throw new RuntimeException('項目形式の更新に失敗しました。');
            }
            return $dir;
    }
    public static function validateValue(array $c, $value): void {
        if (is_array($value) || is_object($value)) throw new InvalidArgumentException('値が不正です。');
        $value=(string)$value;
        if (strlen($value)>(int)$c['length']) throw new InvalidArgumentException('文字数が上限を超えています。');
        if ($value==='') return;
        if (in_array($c['type'],['number','float'],true)) {
            if (!is_numeric($value) || !is_finite((float)$value) || ($c['type']==='number' && (float)$value!=(float)(int)$value)) throw new InvalidArgumentException('数値を入力してください。');
        }
        if ($c['type']==='dropdown' && !array_key_exists($value,$c['options'] ?? [])) throw new InvalidArgumentException('選択肢から選んでください。');
        if ($c['type']==='date' && !is_numeric($value) && !preg_match('/^\d{4}[\/-]\d{1,2}[\/-]\d{1,2}$/',$value)) throw new InvalidArgumentException('日付を入力してください。');
        if ($c['type']==='time' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$value)) throw new InvalidArgumentException('時刻を入力してください。');
        if ($c['type']==='year_month' && !preg_match('/^\d{4}[\/-](0[1-9]|1[0-2])$/',$value)) throw new InvalidArgumentException('年月を入力してください。');
    }
    public static function errors(Controller $ctl,string $table,int $id): array {
        return $ctl->db('states','excel_grid')->select(['tb_name','row_id'],[$table,$id])[0]['errors'] ?? [];
    }
    public static function setErrors(Controller $ctl,string $table,int $id,array $errors): void {
        $db=$ctl->db('states','excel_grid'); $db->set_strict_field_length(true); $old=$db->select(['tb_name','row_id'],[$table,$id])[0] ?? [];
        $row=['tb_name'=>$table,'row_id'=>$id,'errors'=>$errors];
        if ($old) { $row['id']=$old['id']; $db->update($row); } elseif ($errors) $db->insert($row);
    }
    public static function version(array $row): string { return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION)); }
    public static function recalculate(Controller $ctl,string $table,array $row,callable $hook): array {
        $columns=self::config($ctl,$table)['columns'] ?? [];
        if (!$columns) { $hook($row); return []; }
        $db=$ctl->db($table,'common'); $ctl->db('states','excel_grid');
        $row=$db->get($row['id']);
        $result=ExcelFormula::calculate($columns,$row); $errors=$result['errors']; $update=['id'=>$row['id']];
        foreach ($columns as $n=>$c) if (!empty($c['generated']) && $c['mode']==='calculation') $update[$n]=$result['row'][$n] ?? '';
        $db->update($update); $calculated=$db->get($row['id']);
        self::setErrors($ctl,$table,(int)$row['id'],$errors);
        try {
            $hook($calculated);
            if ($errors) foreach ($columns as $n=>$c) if (!empty($c['generated']) && $c['mode']==='function') $errors[$n]='参照元の計算にERRORがあります。';
            $after=$db->get($row['id']);
            $restore=['id'=>$row['id']];
            foreach ($columns as $n=>$c) if (!empty($c['generated']) && $c['mode']!=='function' && ($after[$n] ?? '')!==($calculated[$n] ?? '')) $restore[$n]=$calculated[$n] ?? '';
            if (count($restore)>1) $db->update($restore);
            foreach ($columns as $n=>$c) if (!empty($c['generated']) && $c['mode']==='function') {
                try { self::validateValue($c,$after[$n] ?? ''); } catch (Throwable $e) { $errors[$n]=$e->getMessage(); }
            }
        } catch (Throwable $e) {
            foreach ($columns as $n=>$c) if (!empty($c['generated']) && $c['mode']==='function') $errors[$n]=$e->getMessage();
            if (!$errors) $errors['_post_action']=$e->getMessage();
        }
        self::setErrors($ctl,$table,(int)$row['id'],$errors);
        return $errors;
    }
    public static function guardInput(Controller $ctl,string $table,array $post): void {
        foreach ((self::config($ctl,$table)['columns'] ?? []) as $name=>$c) {
            if (($c['mode'] ?? 'input')!=='input' && array_key_exists($name,$post)) throw new InvalidArgumentException(strtoupper($name).'は自動計算のため変更できません。');
        }
    }
}
