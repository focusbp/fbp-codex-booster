<?php
require_once __DIR__.'/../../lib/ExcelGrid.php';

trait ExcelGridActions {
    private function excelReady(Controller $ctl): bool {
        $ctl->db('setting', 'setting');
        if (!$this->can_execute($ctl)) return false;
        if (!ExcelGrid::isSpreadsheet($this->source_db_setting ?: $this->db_setting) || !empty($this->db_setting['parent_tb_id']) || (int)($this->db_setting['screen_build_type'] ?? 0)!==0 || (int)($this->db_setting['list_type'] ?? 0)===3) throw new RuntimeException('画面パターンがスプレッドシートの親ノートで操作してください。');
        $ctl->db('settings','excel_grid'); $ctl->db('states','excel_grid');
        $ctl->db('db_fields','db'); $ctl->db('screen_fields','db'); $ctl->db('additionals','db_additionals');
        return true;
    }
    private function excelConfig(Controller $ctl): array {
        $cfg=ExcelGrid::config($ctl,$this->table_name);
        if ($cfg) return $cfg;
        ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
        $columns=[];
        foreach ($ctl->get_field_list($this->table_name) as $f) {
            $name=$f['parameter_name'];
            if (!in_array($name,ExcelGrid::names(),true)) {
                if (!empty($f['validation']) && ($f['default_value'] ?? '')==='') throw new RuntimeException('Excel外の必須項目に既定値を設定してください。');
                continue;
            }
            if (!isset(ExcelGrid::TYPES[$f['type']])) throw new RuntimeException('既存項目の型はExcel風表示に未対応です。');
            $options=$f['type']==='dropdown' ? $ctl->get_constant_array($f['constant_array_name'] ?? '',false) : [];
            $columns[$name]=ExcelGrid::column($name,['title'=>$f['parameter_title'],'type'=>$f['type'],'length'=>(int)$f['length'],'generated'=>true,'options'=>$options]);
        }
        return ExcelGrid::saveConfig($ctl,$this->table_name,$columns);
    }
    private function excelReopen(Controller $ctl): void {
        $ctl->close_db_by_ffm($this->ffm);
        $this->ffm=$ctl->db($this->table_name,'common');
    }
    private function excelRows(Controller $ctl): array {
        $names=[]; $values=[]; $patterns=[];
        $this->append_visibility_filter_conditions($ctl,$names,$values,$patterns,'list');
        return $this->ffm->filter($names,$values,true,'AND','excel_sort',SORT_ASC,null,$last,$patterns);
    }
    private function excelRow(Controller $ctl,int $id): array {
        foreach ($this->excelRows($ctl) as $row) if ((int)$row['id']===$id) return $row;
        throw new RuntimeException('対象行が見つからないか、操作権限がありません。');
    }
    private function excelToken(Controller $ctl,string $action,array $data=[]): string {
        $token=bin2hex(random_bytes(18));
        $tickets=$ctl->get_session('excel_tickets') ?: [];
        $tickets[$token]=['action'=>$action,'table'=>$this->table_name,'data'=>$data,'time'=>time(),'status'=>'ready'];
        foreach ($tickets as $k=>$v) if ($v['time']<time()-3600) unset($tickets[$k]);
        if (count($tickets)>80) $tickets=array_slice($tickets,-80,null,true);
        $ctl->set_session('excel_tickets',$tickets);
        return $token;
    }
    private function excelTicket(Controller $ctl,string $action): array {
        $all=$ctl->get_session('excel_tickets') ?: [];
        $token=(string)$ctl->POST('excel_token'); $ticket=$all[$token] ?? [];
        if (!$ticket || $ticket['action']!==$action || $ticket['table']!==$this->table_name || $ticket['time']<time()-3600) throw new RuntimeException('操作の有効期限が切れました。開き直してください。');
        if ($ticket['status']!=='ready') throw new RuntimeException('この操作は処理済みです。画面を再表示して結果を確認してください。');
        return $ticket;
    }
    private function excelDone(Controller $ctl): void {
        $all=$ctl->get_session('excel_tickets') ?: []; $token=(string)$ctl->POST('excel_token');
        if (isset($all[$token])) $all[$token]['status']='done';
        $ctl->set_session('excel_tickets',$all);
    }
    private function excelError(Controller $ctl,Throwable $e): void {
        $ctl->res_error_message('excel_error',$e->getMessage());
        if ($e instanceof ExcelDefinitionReadOnly) {
            $ctl->append_res_data('ok', false);
            $ctl->append_res_data('error', ['code'=>ExcelDefinitionReadOnly::ERROR_CODE, 'message'=>$e->getMessage()]);
        }
    }
    private function excelRefresh(Controller $ctl): void { $this->excel_page($ctl); }

    function excel_preview_set(Controller $ctl) { $this->excelPreviewChange($ctl, true); }
    function excel_preview_reset(Controller $ctl) { $this->excelPreviewChange($ctl, false); }
    private function excelPreviewChange(Controller $ctl, bool $search): void {
        try {
            if (!$this->can_execute($ctl)) return;
            if (!defined('FBP_EXCEL_PREVIEW_COMMAND')) $this->excelTicket($ctl, 'preview');
            ExcelPreview::set($ctl, $this->source_db_setting, $search);
            $this->db_setting = ExcelPreview::effective($ctl, $this->source_db_setting);
            $ctl->assign('excel_preview_active', $search);
            $ctl->append_res_data('excel_preview', ['source_pattern'=>4, 'effective_pattern'=>$search ? 0 : 4]);
            $this->page($ctl);
        } catch (Throwable $e) { $this->excelError($ctl, $e); }
    }

    function excel_import(Controller $ctl) {
        if (PHP_SAPI !== 'cli' || !defined('CLI_APP_CALL') || !defined('FBP_EXCEL_IMPORT_COMMAND')) throw new ExcelDefinitionReadOnly('取込みは専用のexcel_settings_importコマンドで実行してください。');
        $conversion=defined('FBP_EXCEL_CONVERSION_IMPORT_COMMAND');
        $standardSync=defined('FBP_EXCEL_STANDARD_IMPORT_COMMAND');
        if ($conversion || $standardSync) {
            if (!$this->can_execute($ctl)) throw new RuntimeException('操作権限がありません。');
            foreach ([['settings','excel_grid'],['setting','setting'],['db','db']] as [$table,$class]) $ctl->db($table,$class);
        } elseif (!$this->excelReady($ctl)) throw new RuntimeException('画面パターンをスプレッドシートにしてください。');
        $snapshot=json_decode((string)$ctl->POST('snapshot'),true,64,JSON_THROW_ON_ERROR);
        if (($snapshot['schema_version'] ?? null)!==1 || ($snapshot['tb_name'] ?? '')!==$this->table_name || !is_array($snapshot['columns'] ?? null)) throw new RuntimeException('対象ノートまたは設定形式が一致しません。');
        if ($conversion || $standardSync) {
            $source=json_decode((string)$ctl->POST('source_note'),true,64,JSON_THROW_ON_ERROR);
            $current=$ctl->db('db','db')->get((int)$this->db_setting_id);
            if (($snapshot['ok']??false)!==true || ($source['tb_name']??'')!==$this->table_name || (int)($source['id']??0)!==(int)$this->db_setting_id
                || !empty($source['parent_tb_id']) || (int)($source['screen_build_type']??-1)!==0 || !in_array((int)($source['list_type']??-1),[0,4],true)
                || !empty($current['parent_tb_id']) || (int)$current['screen_build_type']!==0 || !in_array((int)$current['list_type'],[0,1,2,3,4],true)
                || (int)($snapshot['list_type']??-1)!==(int)$source['list_type'] || ($conversion && !preg_match('/^[a-f0-9]{48}$/D',(string)($snapshot['standard_request_key']??'')))) throw new RuntimeException('変換定義のノート情報が一致しません。');
        }
        $columns=[];
        foreach ($snapshot['columns'] as $name=>$input) {
            if (!in_array($name,ExcelGrid::names(),true) || !is_array($input)) throw new RuntimeException('列定義が不正です。');
            $c=ExcelGrid::column($name,$input);
            if (!isset(ExcelGrid::TYPES[$c['type']]) || !in_array($c['mode'],['input','calculation','function'],true) || !is_bool($c['generated']) || (int)$c['length']<1 || (int)$c['length']>10000 || strlen($c['title'])>255 || !is_array($c['options']) || $c['digits']<0 || $c['digits']>99 || !in_array($c['rounding'],['round','truncate','away'],true)) throw new RuntimeException('列の型・長さ・丸め設定が不正です。');
            if ($c['mode']==='calculation' && !in_array($c['type'],['number','float'],true)) throw new RuntimeException('計算列は数値型にしてください。');
            $columns[$name]=$c;
        }
        ExcelFormula::plan($columns);
        $rows=$this->ffm->getall(); $old=ExcelGrid::config($ctl,$this->table_name)['columns'] ?? [];
        foreach ($rows as $row) {
            foreach ($columns as $name=>$c) ExcelGrid::validateValue($c,$row[$name] ?? '');
            foreach ($old as $name=>$c) if (!empty($c['generated']) && empty($columns[$name]['generated']) && ($row[$name] ?? '')!=='') throw new RuntimeException('テストデータが残る列は同期で削除できません。対象を確認してください。');
        }
        $order=$snapshot['column_order'] ?? ExcelGrid::names();
        if (!is_array($order)) throw new RuntimeException('列順が不正です。');
        ExcelGrid::validateOrder($order);
        $hasLayout=!empty($snapshot['standard_screens']);
        $screens=$hasLayout?ExcelStandardLayout::screens($snapshot['standard_screens'],['columns'=>$columns,'column_order'=>$order]):[];
        if ($hasLayout && $screens!==$snapshot['standard_screens']) throw new RuntimeException('表示項目定義が不正です。');
        ExcelDefinitionGuard::import($ctl,$this->table_name,$columns,$order,$screens,(string)($snapshot['standard_request_key']??''));
        if ($conversion || $standardSync) ExcelDefinitionGuard::importLayoutNote($ctl,$source,$snapshot);
        $this->excelReopen($ctl);
        $ctl->show_notification_text('列定義を取り込みました。');
    }
    function excel_conversion_import(Controller $ctl) {
        if (!defined('FBP_EXCEL_CONVERSION_IMPORT_COMMAND')) throw new ExcelDefinitionReadOnly();
        $this->excel_import($ctl);
    }
    function excel_page(Controller $ctl) {
        if (!ExcelGrid::isSpreadsheet($this->db_setting) && ExcelGrid::isSpreadsheet($this->source_db_setting)) { $this->page($ctl); return; }
        try {
            if (!$this->excelReady($ctl)) return;
            $cfg=$this->excelConfig($ctl); $this->excelReopen($ctl);
            $rows=$this->excelRows($ctl);
            // Older records receive a stable order without changing their IDs or cell values.
            $max=0; foreach ($rows as $r) $max=max($max,(int)($r['excel_sort'] ?? 0));
            foreach ($rows as &$r) if (empty($r['excel_sort'])) { $r['excel_sort']=++$max; $this->ffm->update(['id'=>$r['id'],'excel_sort'=>$r['excel_sort']]); } unset($r);
            $rows=$this->excelRows($ctl);
            foreach ($rows as &$row) { $row['_version']=ExcelGrid::version($row); $row['_errors']=ExcelGrid::errors($ctl,$this->table_name,(int)$row['id']); } unset($row);
            $fields=[];
            foreach (ExcelGrid::orderedNames($cfg) as $name) $fields[]=ExcelGrid::field($name,ExcelGrid::column($name,$cfg['columns'][$name] ?? []));
            $additionals=$ctl->db('additionals','db_additionals')->select(['tb_name','place'],[$this->table_name,0],true,'AND','sort',SORT_DESC);
            $this->add_show_button_class($ctl,$additionals);
            $ctl->assign('excel_admin_controls',$ctl->is_app_admin());
            $ctl->assign('additionals',$additionals); $ctl->assign('excel_fields',$fields); $ctl->assign('excel_rows',$rows);
            $ctl->assign('excel_columns',$cfg['columns'] ?: []);
            $ctl->assign('excel_table_width',66+array_sum(array_column($fields,'column_width')));
            $ctl->assign('excel_min_column_width',ExcelGrid::MIN_COLUMN_WIDTH); $ctl->assign('excel_max_column_width',ExcelGrid::MAX_COLUMN_WIDTH);
            $ctl->assign('excel_column_width_token',$this->excelToken($ctl,'column_width',['revision'=>(int)$cfg['revision']]));
            $ctl->assign('excel_add_row_token',$this->excelToken($ctl,'add_row'));
            $ctl->assign('excel_column_move_token',$this->excelToken($ctl,'column_move',['revision'=>(int)$cfg['revision']]));
            $ctl->assign('excel_move_token',$this->excelToken($ctl,'move',['ids'=>array_map(fn($r)=>(int)$r['id'],$rows)]));
            $ctl->show_main_area('excel.tpl',$this->title);
        } catch (Throwable $e) { if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e); $ctl->show_notification_text($e->getMessage()); }
    }
    function excel_column(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
            $cfg=$this->excelConfig($ctl); $name=strtolower((string)$ctl->POST('column'));
            if (!in_array($name,ExcelGrid::names(),true)) throw new InvalidArgumentException('列が不正です。');
            $c=ExcelGrid::column($name,$cfg['columns'][$name] ?? []);
            if (!isset($cfg['columns'][$name]['digits'])) $c['digits']=(int)($ctl->get_setting()['number_decimal_digits'] ?? 2);
            $ctl->assign('excel_col',$name); $ctl->assign('excel_column',$c);
            $ctl->assign('excel_token',$this->excelToken($ctl,'column',['column'=>$name,'revision'=>$cfg['revision']]));
            $ctl->assign('excel_types',['text'=>'文字列','textarea'=>'長文','number'=>'整数','float'=>'小数','date'=>'日付','datetime'=>'日時','time'=>'時刻','year_month'=>'年月','dropdown'=>'選択肢']);
            $ctl->assign('excel_option_rows',ExcelGrid::optionRows($c));
            $ctl->show_multi_dialog('excel-editor','excel_column.tpl',strtoupper($name).' 項目設定',360,null,['modal'=>true]);
        } catch (Throwable $e) { if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e); $ctl->show_notification_text($e->getMessage()); }
    }
    function excel_column_save(Controller $ctl) {
        $ctl->clear_error_message();
        try {
            if (!$this->excelReady($ctl)) return;
            ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
            $ticket=$this->excelTicket($ctl,'column'); $cfg=$this->excelConfig($ctl);
            if ((int)$cfg['revision']!==(int)$ticket['data']['revision']) throw new RuntimeException('項目設定が更新されています。開き直してください。');
            $name=$ticket['data']['column']; $old=ExcelGrid::column($name,$cfg['columns'][$name] ?? []); $c=$old;
            $c['title']=trim((string)$ctl->POST('title')); $c['type']=(string)$ctl->POST('type');
            $c['mode']=ExcelGrid::userMode($c['type'],(string)($ctl->POST('mode') ?: 'input'),$old['mode']);
            if (strlen($c['title'])>255 || !isset(ExcelGrid::TYPES[$c['type']]) || !in_array($c['mode'],['input','calculation','function'],true)) throw new InvalidArgumentException('項目設定が不正です。');
            $c['length']=ExcelGrid::TYPES[$c['type']];
            $c['formula']=trim((string)$ctl->POST('formula')); $c['digits']=$ctl->POST('digits')===null || $ctl->POST('digits')==='' ? (int)($ctl->get_setting()['number_decimal_digits'] ?? 2) : (int)$ctl->POST('digits'); $c['rounding']=(string)$ctl->POST('rounding');
            if ($c['digits']<0 || $c['digits']>99 || !in_array($c['rounding'],['round','truncate','away'],true)) throw new InvalidArgumentException('丸め設定が不正です。');
            if ($c['type']==='number') $c['digits']=0;
            if ($c['mode']==='calculation' && !in_array($c['type'],['number','float'],true)) throw new InvalidArgumentException('「計算」は整数または小数の型にしてください。');
            if ($c['mode']==='function' && empty($this->db_setting['post_action_class'])) throw new InvalidArgumentException('関数にはサポートによるpost_actionの準備が必要です。');
            $optionAction=(string)$ctl->POST('option_action');
            if ($optionAction!=='') {
                if ($c['type']!=='dropdown') throw new InvalidArgumentException('型を「選択肢」にしてください。');
                $order=json_decode((string)($ctl->POST('option_order') ?: '[]'),true,64,JSON_THROW_ON_ERROR);
                if (!is_array($order)) throw new InvalidArgumentException('選択肢の並び順が不正です。');
                $c=ExcelGrid::editOption($c,$optionAction,(string)$ctl->POST('option_value'),(string)$ctl->POST('option_label'),$order);
            }
            $c['generated']=$old['generated'] || $c['title']!=='';
            $columns=$cfg['columns'] ?: []; $columns[$name]=$c;
            // Validate draft formulas too, while retaining lazy physical field creation.
            $check=$columns; if ($c['mode']==='calculation') $check[$name]['generated']=true;
            ExcelFormula::plan($check);
            foreach ($this->ffm->getall() as $row) {
                if ($optionAction==='delete' && (string)($row[$name] ?? '')===(string)$ctl->POST('option_value')) throw new InvalidArgumentException('データで使用中の選択肢は削除できません。先にデータを変更してください。');
                ExcelGrid::validateValue($c,$row[$name] ?? '');
            }
            ExcelGrid::saveConfig($ctl,$this->table_name,$columns); $this->excelReopen($ctl); $this->excelDone($ctl);
            $failures=[];
            if ($c['generated'] && ($old['type']!==$c['type'] || $old['mode']!==$c['mode'] || $old['formula']!==$c['formula'] || $old['digits']!==$c['digits'] || $old['rounding']!==$c['rounding'])) {
                foreach ($this->excelRows($ctl) as $row) { $errors=$this->excelCalculate($ctl,$row,$row,'recalculate'); if ($errors) $failures=array_merge($failures,array_values($errors)); }
            }
            $this->excelRefresh($ctl);
            if ($failures) {
                $current=ExcelGrid::config($ctl,$this->table_name);
                $ctl->append_res_data('excel_editor_token',$this->excelToken($ctl,'column',['column'=>$name,'revision'=>$current['revision']]));
                $ctl->res_error_message('excel_error','設定は保存済みです。計算エラー: '.implode(' / ',array_unique($failures)));
            }
            elseif ($optionAction!=='') {
                $current=ExcelGrid::config($ctl,$this->table_name);
                $ctl->append_res_data('excel_editor_token',$this->excelToken($ctl,'column',['column'=>$name,'revision'=>$current['revision']]));
            }
            else $ctl->close_multi_dialog('excel-editor');
            if ($optionAction!=='') $ctl->append_res_data('excel_options_result',['ok'=>true,'rows'=>ExcelGrid::optionRows($c),'action'=>$optionAction,'type'=>$c['type'],'generated'=>$c['generated']]);
        } catch (Throwable $e) {
            if ($ctl->POST('option_action')) $ctl->append_res_data('excel_options_result',['ok'=>false]);
            $this->excelError($ctl,$e);
        }
    }
    function excel_cell(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $cfg=$this->excelConfig($ctl); $name=strtolower((string)$ctl->POST('column'));
            if (!in_array($name,ExcelGrid::names(),true)) throw new RuntimeException('列が不正です。');
            $c=ExcelGrid::column($name,$cfg['columns'][$name] ?? []); $id=(int)$ctl->POST('row_id');
            $row=$id ? $this->excelRow($ctl,$id) : [];
            $errors=$id ? ExcelGrid::errors($ctl,$this->table_name,$id) : [];
            $ctl->assign('excel_field',ExcelGrid::field($name,$c)); $ctl->assign('excel_readonly',$c['mode']!=='input');
            $ctl->assign('excel_error_text',$errors[$name] ?? ''); $ctl->assign('excel_col',$name); $ctl->assign('row',$row);
            $ctl->assign('excel_token',$this->excelToken($ctl,'cell',['column'=>$name,'id'=>$id,'version'=>ExcelGrid::version($row),'revision'=>$cfg['revision']]));
            $ctl->show_multi_dialog('excel-editor','excel_cell.tpl',strtoupper($name).' '.($c['title'] ?: ($id?'入力':'新しい行')),320,null,['modal'=>true]);
        } catch (Throwable $e) { if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e); $ctl->show_notification_text($e->getMessage()); }
    }
    private function excelCalculate(Controller $ctl,array $row,array $before,string $from): array {
        $previous=$this->excel_in_hook; $this->excel_in_hook=true;
        try { return ExcelGrid::recalculate($ctl,$this->table_name,$row,function(array $calculated) use ($ctl,$before,$from) {
            $this->invoke_post_action_class($ctl,$calculated,$from,null,$before ?: null);
        }); } finally { $this->excel_in_hook=$previous; }
    }
    function excel_retry(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $ticket=$this->excelTicket($ctl,'cell');
            $row=$this->excelRow($ctl,(int)$ticket['data']['id']);
            $errors=$this->excelCalculate($ctl,$row,$row,'recalculate'); $this->excelRefresh($ctl);
            if ($errors) $ctl->res_error_message('excel_error','ERROR: '.implode(' / ',array_unique(array_values($errors))));
            else { $this->excelDone($ctl); $ctl->close_multi_dialog('excel-editor'); }
        } catch (Throwable $e) { $this->excelError($ctl,$e); }
    }

    function excel_save(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $ticket=$this->excelTicket($ctl,'cell'); $d=$ticket['data']; $cfg=$this->excelConfig($ctl);
            if ((int)$cfg['revision']!==(int)$d['revision']) throw new RuntimeException('項目設定が変わりました。開き直してください。');
            $c=ExcelGrid::column($d['column'],$cfg['columns'][$d['column']] ?? []);
            if ($c['mode']!=='input') throw new RuntimeException('この列は手動変更できません。');
            $before=$d['id'] ? $this->excelRow($ctl,(int)$d['id']) : [];
            if (ExcelGrid::version($before)!==$d['version']) throw new RuntimeException('他の操作で行が変更されました。開き直してください。');
            $value=$ctl->POST($d['column']) ?? '';
            if (array_key_exists('saved_value',$d) && (string)$value===(string)$d['saved_value']) throw new RuntimeException('入力は保存済みです。値を修正して確定するか、ERRORの計算セルから再計算してください。');
            if ($c['type']==='date' && $value!=='' && !is_numeric($value)) { $stamp=strtotime(str_replace('/','-',(string)$value)); if ($stamp===false) throw new InvalidArgumentException('日付が不正です。'); $value=$stamp; }
            ExcelGrid::validateValue($c,$value);
            if (!$before && $value==='') { $ctl->close_multi_dialog('excel-editor'); return; }
            if (!$c['generated']) { ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir); $c['generated']=true; $columns=$cfg['columns'] ?: []; $columns[$d['column']]=$c; ExcelGrid::saveConfig($ctl,$this->table_name,$columns); $this->excelReopen($ctl); }
            $row=$before ?: $ctl->get_default_values($this->table_name); $row[$d['column']]=$value;
            if (!$before) { $row['excel_sort']=$this->excelNextSort($ctl); $id=$this->ffm->insert($row); $row=$this->ffm->get($id); }
            else { $this->ffm->update(['id'=>$before['id'],$d['column']=>$value]); $row=$this->ffm->get($before['id']); }
            $this->excelDone($ctl); // Input is committed; a failed hook must not repeat the insert.
            $errors=$this->excelCalculate($ctl,$row,$before,$before?'edit':'add');
            $this->excelRefresh($ctl);
            if ($errors) {
                $after=$this->ffm->get($row['id']); $current=ExcelGrid::config($ctl,$this->table_name);
                $ctl->append_res_data('excel_editor_token',$this->excelToken($ctl,'cell',['column'=>$d['column'],'id'=>(int)$row['id'],'version'=>ExcelGrid::version($after),'revision'=>$current['revision'],'saved_value'=>$value]));
                $ctl->res_error_message('excel_error','入力は保存済みです。ERROR: '.implode(' / ',array_unique(array_values($errors))));
            }
            else $ctl->close_multi_dialog('excel-editor');
        } catch (Throwable $e) { $this->excelError($ctl,$e); }
    }
    private function excelNextSort(Controller $ctl): int {
        $max=0; foreach ($this->ffm->getall() as $r) $max=max($max,(int)($r['excel_sort'] ?? 0)); return $max+1;
    }
    function excel_row_add(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $this->excelTicket($ctl,'add_row'); $this->excelConfig($ctl);
            $new=$ctl->get_default_values($this->table_name); $new['excel_sort']=$this->excelNextSort($ctl); $id=$this->ffm->insert($new);
            $this->excelDone($ctl);
            $errors=$this->excelCalculate($ctl,$this->ffm->get($id),[],'add');
            $this->excelRefresh($ctl);
            if ($errors) $ctl->show_notification_text('行を追加しました。ERROR: '.implode(' / ',array_unique(array_values($errors))));
        } catch (Throwable $e) { if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e); $ctl->show_notification_text($e->getMessage()); }
    }
    function excel_row_menu(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $cfg=$this->excelConfig($ctl); $id=(int)$ctl->POST('row_id'); $row=$id?$this->excelRow($ctl,$id):[];
            $ctl->assign('excel_row_id',$id); $ctl->assign('excel_clip_available',(bool)$ctl->get_session('excel_clip_'.$this->table_name));
            $ctl->assign('excel_token',$this->excelToken($ctl,'row',['id'=>$id,'version'=>ExcelGrid::version($row),'revision'=>$cfg['revision'],'clip_version'=>($ctl->get_session('excel_clip_'.$this->table_name)['version'] ?? '')]));
            $ctl->show_multi_dialog('excel-editor','excel_row.tpl',$id?'行の操作':'新しい行',280,null,['modal'=>true]);
        } catch (Throwable $e) { if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e); $ctl->show_notification_text($e->getMessage()); }
    }
    function excel_row_action(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $ticket=$this->excelTicket($ctl,'row'); $cfg=$this->excelConfig($ctl); $id=(int)$ticket['data']['id'];
            if ((int)$cfg['revision']!==(int)$ticket['data']['revision']) throw new RuntimeException('項目設定が変更されました。開き直してください。');
            $row=$id?$this->excelRow($ctl,$id):[];
            if (ExcelGrid::version($row)!==$ticket['data']['version']) throw new RuntimeException('行が更新されました。開き直してください。');
            $action=(string)$ctl->POST('action'); $columns=$cfg['columns'] ?: []; $errors=[];
            if ($action==='copy') {
                if (!$row) throw new RuntimeException('コピー元を選択してください。');
                $values=[]; foreach ($columns as $n=>$c) if ($c['generated'] && $c['mode']==='input' && array_key_exists($n,$row)) $values[$n]=$row[$n];
                $ctl->set_session('excel_clip_'.$this->table_name,['values'=>$values,'revision'=>$cfg['revision'],'source'=>$id,'version'=>bin2hex(random_bytes(12))]);
            } elseif ($action==='paste') {
                $clip=$ctl->get_session('excel_clip_'.$this->table_name);
                if (!$clip || ($clip['version'] ?? '')!==($ticket['data']['clip_version'] ?? '') || (int)$clip['revision']!==(int)$cfg['revision']) throw new RuntimeException('コピー内容がありません。再コピーしてください。');
                $this->excelRow($ctl,(int)$clip['source']);
                foreach ($clip['values'] as $n=>$v) {
                    if (empty($columns[$n]['generated']) || $columns[$n]['mode']!=='input') throw new RuntimeException('貼付け先の列設定を確認してください。');
                    ExcelGrid::validateValue($columns[$n],$v);
                }
                $before=$row;
                if ($row) { $update=['id'=>$id]+$clip['values']; $this->ffm->update($update); }
                else { $update=array_replace($ctl->get_default_values($this->table_name),$clip['values']); $update['excel_sort']=$this->excelNextSort($ctl); $id=$this->ffm->insert($update); }
                $this->excelDone($ctl); $errors=$this->excelCalculate($ctl,$this->ffm->get($id),$before,$before?'edit':'add');
            } elseif ($action==='add') {
                $new=$ctl->get_default_values($this->table_name); $new['excel_sort']=$this->excelNextSort($ctl); $newId=$this->ffm->insert($new);
                if ($row) $this->excelPlace($ctl,(int)$newId,$id,false);
                $this->excelDone($ctl); $errors=$this->excelCalculate($ctl,$this->ffm->get($newId),[],'add');
            } elseif ($action==='delete') {
                if (!$row || $ctl->POST('confirmed')!=='1') throw new RuntimeException('削除を確認してください。');
                $this->delete_recurring($ctl,$this->table_name,$id); ExcelGrid::setErrors($ctl,$this->table_name,$id,[]); $this->excelDone($ctl);
                $this->invoke_post_action_class($ctl,$row,'delete');
            } else throw new RuntimeException('操作が不正です。');
            $this->excelDone($ctl); $this->excelRefresh($ctl);
            if ($errors) $ctl->res_error_message('excel_error','保存済みです。ERROR: '.implode(' / ',array_unique(array_values($errors))));
            else $ctl->close_multi_dialog('excel-editor');
        } catch (Throwable $e) { $this->excelError($ctl,$e); }
    }
    private function excelPlace(Controller $ctl,int $id,int $target,bool $before=true): void {
        $rows=$this->excelRows($ctl); $ids=array_map(fn($r)=>(int)$r['id'],$rows);
        if (!in_array($id,$ids,true) || !in_array($target,$ids,true)) throw new RuntimeException('移動先が見つかりません。');
        $ids=array_values(array_filter($ids,fn($n)=>$n!==$id)); $at=array_search($target,$ids,true);
        array_splice($ids,$at+($before?0:1),0,[$id]);
        foreach ($ids as $i=>$n) $this->ffm->update(['id'=>$n,'excel_sort'=>$i+1]);
    }
    function excel_column_width(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
            $ticket=$this->excelTicket($ctl,'column_width'); $cfg=$this->excelConfig($ctl);
            if ((int)$ticket['data']['revision']!==(int)$cfg['revision']) throw new RuntimeException('項目設定が変更されています。表を再表示してください。');
            $name=(string)$ctl->POST('column');
            if (!in_array($name,ExcelGrid::names(),true)) throw new InvalidArgumentException('列が不正です。');
            $width=ExcelGrid::columnWidth($ctl->POST('column_width'));
            $columns=$cfg['columns'] ?: []; $c=ExcelGrid::column($name,$columns[$name] ?? []); $c['column_width']=$width; $columns[$name]=$c;
            $saved=ExcelGrid::saveConfig($ctl,$this->table_name,$columns); $this->excelDone($ctl);
            $ctl->append_res_data('excel_column_width_result',['ok'=>true,'db_id'=>(int)$this->db_setting_id,'column'=>$name,'column_width'=>$width,
                'token'=>$this->excelToken($ctl,'column_width',['revision'=>(int)$saved['revision']]),
                'move_token'=>$this->excelToken($ctl,'column_move',['revision'=>(int)$saved['revision']])]);
        } catch (Throwable $e) {
            if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e);
            $ctl->append_res_data('excel_column_width_result',['ok'=>false,'db_id'=>(int)$this->db_setting_id,'error'=>$e->getMessage()]);
        }
    }
    function excel_column_move(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
            $ticket=$this->excelTicket($ctl,'column_move'); $cfg=$this->excelConfig($ctl);
            if ((int)$ticket['data']['revision']!==(int)$cfg['revision']) throw new RuntimeException('項目設定や列順が変更されました。再表示してください。');
            $name=(string)$ctl->POST('column'); $target=(string)$ctl->POST('target_column');
            if (!in_array($name,ExcelGrid::names(),true) || !in_array($target,ExcelGrid::names(),true) || $name===$target) throw new InvalidArgumentException('移動する列が不正です。');
            $order=array_values(array_filter(ExcelGrid::orderedNames($cfg),fn($n)=>$n!==$name));
            $at=array_search($target,$order,true); array_splice($order,$at+($ctl->POST('after')==='1'?1:0),0,[$name]);
            ExcelGrid::saveConfig($ctl,$this->table_name,$cfg['columns'] ?: [],$order);
            $this->excelDone($ctl); $this->excelRefresh($ctl);
        } catch (Throwable $e) {
            if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e);
            $ctl->assign('excel_message',$e->getMessage());
            $ctl->show_multi_dialog('excel-editor','excel_message.tpl','列の移動',320,null,['modal'=>true]);
        }
    }
    function excel_move(Controller $ctl) {
        try {
            if (!$this->excelReady($ctl)) return;
            $ticket=$this->excelTicket($ctl,'move');
            $ids=array_map(fn($r)=>(int)$r['id'],$this->excelRows($ctl));
            if ($ids!==$ticket['data']['ids']) throw new RuntimeException('行順が変更されました。再表示してください。');
            $id=(int)$ctl->POST('row_id'); $target=(int)$ctl->POST('target_id');
            if ($id===$target) return;
            $this->excelRow($ctl,$id); $this->excelRow($ctl,$target);
            $this->excelPlace($ctl,$id,$target,$ctl->POST('after')!=='1'); $this->excelDone($ctl); $this->excelRefresh($ctl);
        } catch (Throwable $e) {
            if ($e instanceof ExcelDefinitionReadOnly) $this->excelError($ctl,$e);
            $ctl->assign('excel_message',$e->getMessage());
            $ctl->show_multi_dialog('excel-editor','excel_message.tpl','行の移動',320,null,['modal'=>true]);
        }
    }
}
