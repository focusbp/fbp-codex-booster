<?php
require_once __DIR__.'/../../lib/NoteSyncClient.php';

trait ExcelConversionActions {
    private function conversionSession(): string { return 'excel_conversion_'.$this->db_setting_id; }
    private function conversionAllowed(Controller $ctl): bool {
        if (!$this->can_execute($ctl)) return false;
        if (!$ctl->is_app_admin()) throw new RuntimeException('表示形式の変換は管理者のみ実行できます。');
        if (!$ctl->get_login_user_id()) throw new RuntimeException('ログインして操作してください。');
        ExcelDefinitionGuard::assertEditable($ctl->dirs->datadir);
        return true;
    }
    private function conversionRender(Controller $ctl,array $flow): void {
        $ctl->set_session($this->conversionSession(),$flow);
        $ctl->assign('conversion',$flow);
        $ctl->assign('conversion_fields',ExcelStandardLayout::candidates(ExcelGrid::config($ctl,$this->table_name)));
        $ctl->assign('conversion_screens',ExcelStandardLayout::SCREENS);
        $ctl->assign('conversion_screen',array_keys(ExcelStandardLayout::SCREENS)[$flow['step']]??'');
        $ctl->show_multi_dialog('excel-conversion','excel_conversion.tpl','データベース形式に変換',560,null,['modal'=>true]);
    }
    function excel_convert(Controller $ctl) {
        try {
            if (!$this->conversionAllowed($ctl) || !$this->excelReady($ctl)) return;
            $config=$this->excelConfig($ctl);$old=$ctl->get_session($this->conversionSession());
            if ($old && (int)$config['revision']===(int)$old['revision']) { $old['time']=time();$this->conversionRender($ctl,$old);return; }
            if ($old && !empty($old['pending']) && ($config['standard_request_key']??'')===$old['key']) { $this->conversionRender($ctl,$old);return; }
            $names=array_keys(ExcelStandardLayout::candidates($config));
            if (!$names) throw new RuntimeException('変換する項目がありません。先に列の項目名を設定してください。');
            $flow=['key'=>$old['key']??bin2hex(random_bytes(24)),'name'=>$this->table_name,'revision'=>(int)$config['revision'],
                'step'=>0,'screens'=>array_fill_keys(array_keys(ExcelStandardLayout::SCREENS),$names),'time'=>time(),'pending'=>false];
            $this->conversionRender($ctl,$flow);
        } catch (Throwable $e) { $ctl->show_notification_text($e->getMessage()); }
    }
    private function conversionFlow(Controller $ctl): array {
        $flow=$ctl->get_session($this->conversionSession());
        if (!$flow || !hash_equals($flow['key'],(string)$ctl->POST('conversion_key')) || $flow['name']!==$this->table_name
            || (int)$ctl->POST('step')!==$flow['step'] || (empty($flow['pending']) && $flow['time']<time()-3600)) throw new RuntimeException('操作の有効期限が切れました。変換リンクから開き直してください。');
        return $flow;
    }
    function excel_convert_step(Controller $ctl) {
        $ctl->clear_error_message();
        try {
            if (!$this->conversionAllowed($ctl) || !$this->excelReady($ctl)) return;
            $flow=$this->conversionFlow($ctl);
            if ($flow['pending']) throw new RuntimeException('変換受付済みです。OKで結果を確認してください。');
            if ((int)ExcelGrid::config($ctl,$this->table_name)['revision']!==$flow['revision']) throw new RuntimeException('列設定が変更されました。変換リンクから項目を選び直してください。');
            $direction=$ctl->POST('direction');
            if (!in_array($direction,['back','next'],true)) throw new RuntimeException('操作が不正です。');
            if ($flow['step']<4) {
                $screen=array_keys(ExcelStandardLayout::SCREENS)[$flow['step']];
                $selected=$ctl->POST('fields')??[];
                if (!is_array($selected)) throw new RuntimeException('項目指定が不正です。');
                $candidates=ExcelStandardLayout::candidates(ExcelGrid::config($ctl,$this->table_name));
                foreach ($selected as $name) if (!is_string($name) || !isset($candidates[$name])) throw new RuntimeException('項目指定が不正です。');
                if ($direction==='next' && $screen==='list' && !$selected) throw new RuntimeException('一覧には1項目以上選択してください。');
                $flow['screens'][$screen]=array_values(array_filter(array_keys($candidates),fn($n)=>in_array($n,$selected,true)));
            }
            $flow['step']=max(0,min(4,$flow['step']+($direction==='back'?-1:1)));
            if ($flow['step']===4) $flow['screens']=ExcelStandardLayout::screens($flow['screens'],ExcelGrid::config($ctl,$this->table_name));
            $this->conversionRender($ctl,$flow);
        } catch (Throwable $e) { $ctl->res_error_message('conversion_error',$e->getMessage()); }
    }
    function excel_convert_apply(Controller $ctl) {
        $ctl->clear_error_message();
        try {
            if (!$this->conversionAllowed($ctl)) return;
            $flow=$this->conversionFlow($ctl);
            if ($flow['step']!==4) throw new RuntimeException('表示項目の確認を完了してください。');
            $config=ExcelGrid::config($ctl,$this->table_name);
            if (empty($flow['pending']) && (int)$config['revision']!==$flow['revision']) throw new RuntimeException('列設定が変更されました。変換リンクから項目を選び直してください。');
            $flow['pending']=true;$ctl->set_session($this->conversionSession(),$flow);
            $local=ExcelDefinitionGuard::isDeveloper($ctl->dirs->datadir);
            if ($local) ExcelStandardLayout::apply($ctl,(int)$this->db_setting_id,$flow['name'],$flow['screens'],$flow['revision'],$flow['key']);
            else (new NoteSyncClient($ctl))->convert((int)$this->db_setting_id,$flow['name'],$flow['screens'],$flow['revision'],$flow['key']);
            $ctl->set_session($this->conversionSession(),null);
            $ctl->close_multi_dialog('excel-conversion');
            $ctl->invoke('page',['db_id'=>$this->db_setting_id],'db_exe');
            $ctl->show_notification_text($local?'開発環境でデータベース形式に変換しました。':'データベース形式に変換しました。テスト環境への同期を受け付けました。');
        } catch (Throwable $e) { $ctl->res_error_message('conversion_error',$e->getMessage()); }
    }
}
