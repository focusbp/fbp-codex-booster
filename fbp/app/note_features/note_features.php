<?php
require_once __DIR__.'/../../lib/ExcelGrid.php';
require_once __DIR__.'/../../lib/NoteFeatureCatalog.php';
require_once __DIR__.'/../../lib/NoteFeatureRequestClient.php';
class note_features {
    private function context(Controller $ctl):array {
        if(!$ctl->get_session('login'))throw new RuntimeException('ログインしてください。');
        if(!$ctl->is_app_admin())throw new RuntimeException('機能の追加依頼は管理者のみ実行できます。');
        if(!$ctl->authorize_management_access('db_exe','page'))throw new RuntimeException('このノートの閲覧権限がありません。');
        $id=$ctl->POST('db_id');if(!is_scalar($id)||!ctype_digit((string)$id))throw new RuntimeException('対象ノートを確認してください。');
        $note=$ctl->db('db','db')->get((int)$id);if(!$note||!ExcelGrid::isSpreadsheet($note)||!empty($note['parent_tb_id']))throw new RuntimeException('対象のExcel風ノートを確認してください。');
        $class=$note['tb_name'].'_visibility_filter';$file=$ctl->dirs->appdir_user.'/'.$class.'/'.$class.'.php';if(is_file($file)){require_once $file;$filter=new $class();if(method_exists($filter,'can_access')&&!$filter->can_access($ctl,'page',$note['tb_name'],$note))throw new RuntimeException('このノートの閲覧権限がありません。');}
        $cfg=ExcelGrid::config($ctl,$note['tb_name']);$fields=[];foreach(ExcelGrid::orderedNames($cfg) as $key){$c=ExcelGrid::column($key,$cfg['columns'][$key]??[]);if(!$c['generated'])continue;$fields[]=['key'=>$key,'title'=>$c['title'],'type'=>$c['type'],'length'=>$c['length'],'mode'=>$c['mode'],'options'=>$c['options']??[]];}
        return ['id'=>(int)$note['id'],'table'=>$note['tb_name'],'title'=>$note['menu_name'],'fields'=>$fields];
    }
    private function requirements(Controller $ctl,array $feature):array {
        $missing=NoteFeatureCatalog::missing($feature,$ctl->db('setting','setting')->get(1)??[]);
        if($feature['id']==='mcp'){$servers=$ctl->db('mcp_server_config','mcp_manage')->getall();$ready=false;foreach($servers as $server)if((int)($server['enabled']??0)===1)$ready=true;if(!$ready)$missing[]='MCPサーバーの有効化・OAuth設定';}
        return $missing;
    }
    private function fingerprint(array $note):string{return hash('sha256',json_encode($note,JSON_UNESCAPED_UNICODE));}
    private function pending(Controller $ctl,array $note):array {
        $p=$ctl->get_session('note_feature_request');$token=$ctl->POST('token');if(!is_array($p)||!is_string($token)||!hash_equals($p['token'],$token)||$p['expires']<time()||$p['note_id']!==$note['id'])throw new RuntimeException('依頼画面を開き直してください。');return $p;
    }
    function page(Controller $ctl):void {
        try{$note=$this->context($ctl);$token=bin2hex(random_bytes(24));$ctl->set_session('note_feature_request',['token'=>$token,'request_key'=>bin2hex(random_bytes(24)),'expires'=>time()+3600,'note_id'=>$note['id'],'fingerprint'=>$this->fingerprint($note)]);$ctl->assign('note',$note);$ctl->assign('token',$token);$ctl->assign('features',NoteFeatureCatalog::all());$ctl->show_multi_dialog('note-feature-picker','picker.tpl','機能を追加する',680);}
        catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function prepare(Controller $ctl):void {
        try{$note=$this->context($ctl);$p=$this->pending($ctl,$note);$feature=NoteFeatureCatalog::get((string)$ctl->POST('feature_id'));$ctl->assign('note',$note);$ctl->assign('token',$p['token']);$ctl->assign('feature',$feature);$ctl->assign('missing',$this->requirements($ctl,$feature));$configClass=$feature['id']==='mcp'?'mcp_manage':'setting';$ctl->assign('config_class',$configClass);$ctl->assign('can_configure',$ctl->authorize_management_access($configClass,'page'));$ctl->assign('default_request',NoteFeatureCatalog::draft($feature,$note['title'],[]));$ctl->show_multi_dialog('note-feature-editor','editor.tpl',$feature['title'].'を依頼する',760);}
        catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    function configure(Controller $ctl):void {
        try{$note=$this->context($ctl);$this->pending($ctl,$note);$feature=NoteFeatureCatalog::get((string)$ctl->POST('feature_id'));$target=$feature['id']==='mcp'?'mcp_manage':'setting';if(!$ctl->authorize_management_access($target,'page'))throw new RuntimeException('管理者に設定を依頼してください。');$ctl->close_multi_dialog('note-feature-editor');$ctl->close_multi_dialog('note-feature-picker');$ctl->invoke('page',[],$target);}
        catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    protected function sendRequest(Controller $ctl,array $payload):array {return (new NoteFeatureRequestClient($ctl))->submit($payload);}
    function submit(Controller $ctl):void {
        try{$note=$this->context($ctl);$p=$this->pending($ctl,$note);$feature=NoteFeatureCatalog::get((string)$ctl->POST('feature_id'));if($p['fingerprint']!==$this->fingerprint($note))throw new RuntimeException('ノートの項目が変わりました。依頼画面を開き直してください。');$missing=$this->requirements($ctl,$feature);if($missing)throw new RuntimeException('先にシステム設定を完了してください：'.implode('、',$missing));
            try{$options=NoteFeatureCatalog::options($feature,['product_name'=>$ctl->POST('product_name'),'price_tax_included'=>$ctl->POST('price_tax_included')]);}catch(InvalidArgumentException $e){$ctl->res_error_message('options',$e->getMessage());return;}
            $text=$ctl->POST('request_text');if(!is_string($text)||trim($text)===''||strlen($text)>6000){$ctl->res_error_message('request_text','依頼内容を6,000バイト以内で入力してください。');return;}
            if($feature['id']==='other'&&trim($text)===trim(NoteFeatureCatalog::draft($feature,$note['title'],[]))){$ctl->res_error_message('request_text','追加したい機能を入力してください。');return;}
            $app=(string)$ctl->get_appcode();if(!str_starts_with($app,'app-'))$app='app-'.$app;if(!preg_match('/^app-[A-Za-z0-9][A-Za-z0-9_-]*$/D',$app))throw new RuntimeException('対象プロジェクトを確認してください。');
            $payload=['schema_version'=>1,'request_key'=>$p['request_key'],'appcode'=>$app,'feature_id'=>$feature['id'],'note'=>$note,'options'=>$options,'request_text'=>trim($text),'code_change_allowed'=>true,'production_release_allowed'=>true,'requested_by'=>['user_id'=>(int)$ctl->get_login_user_id(),'login_id'=>(string)$ctl->get_login_id(),'is_app_admin'=>true]];
            $digest=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE));if(isset($p['digest'])&&!hash_equals($p['digest'],$digest))throw new RuntimeException('受付状況を確認中の依頼は内容を変更できません。同じ内容で再試行してください。');$p['digest']=$digest;$ctl->set_session('note_feature_request',$p);
            $result=$p['result']??$this->sendRequest($ctl,$payload);$p['result']=$result;$ctl->set_session('note_feature_request',$p);$ctl->assign('share_url',$result['support_url']);$ctl->assign('share_url_help','AIに実装を依頼しました。進捗は下記からご覧ください。');$ctl->assign('share_url_open_label','プロジェクトサポートで進捗を見る');$ctl->show_multi_dialog('note-feature-editor','result.tpl','実装を依頼しました',680);
        }catch(Throwable $e){$ctl->res_error_message('request',$e->getMessage());}
    }
}
