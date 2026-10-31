<?php
require_once __DIR__.'/Parser.php';
require_once __DIR__.'/../sample_reconciliation/Store.php';
class sample_statement_csv implements CodegenActionInterface {
    private const SESSION='sample_statement_csv_preview';
    private function allowed(Controller $ctl):bool {if($ctl->get_session('login')&&$ctl->is_app_admin())return true;$ctl->show_notification_text('この操作を行う権限がありません。');return false;}
    function run(Controller $ctl):void {if(!$this->allowed($ctl))return;$ctl->show_multi_dialog('sample-statement-csv','upload.tpl','通帳CSV取り込み',800);}
    function upload_exe(Controller $ctl):void {
        if(!$this->allowed($ctl))return;
        try{
            $f=$_FILES['file']??[];$path=$f['tmp_name']??'';
            if(($f['error']??1)!==UPLOAD_ERR_OK||!is_string($path)||!is_file($path)||filesize($path)>2*1024*1024||(!(defined('CLI_APP_CALL')&&CLI_APP_CALL)&&!is_uploaded_file($path)))throw new InvalidArgumentException('2MB以下のCSVを選択してください。');
            $scope=$ctl->POST('scope_key');$account=$ctl->POST('account_key');
            if(!is_string($scope)||!is_string($account))throw new InvalidArgumentException('管理範囲と口座識別子を入力してください。');
            $incoming=SampleStatementCsvParser::parse(file_get_contents($path),trim($scope),trim($account),(string)$ctl->POST('exclude_interest')==='1');
            $store=new SampleReconciliationStore($ctl);$existing=$store->snapshot()['right'];$plan=SampleStatementCsvParser::plan($incoming,$existing);
            $token=bin2hex(random_bytes(24));$filename=mb_substr(basename((string)($f['name']??'statement.csv')),0,80);
            $ctl->set_session(self::SESSION,['token'=>$token,'rows'=>$incoming,'filename'=>$filename,'expires'=>time()+1800]);
            $ctl->assign('preview',$plan);$ctl->assign('token',$token);$ctl->assign('filename',$filename);
        }catch(Throwable $e){$ctl->res_error_message('file',$e->getMessage());return;}
        $ctl->reload_area('#sample_statement_csv_preview','preview.tpl');
    }
    function save(Controller $ctl):void {
        if(!$this->allowed($ctl))return;
        $p=$ctl->get_session(self::SESSION);$token=$ctl->POST('token');
        if(!is_array($p)||!is_string($token)||!hash_equals($p['token'],$token)||$p['expires']<time()){$ctl->res_error_message('token','CSVを再度確認してください。');return;}
        try{
            $store=new SampleReconciliationStore($ctl);$store->recover();
            $plan=SampleStatementCsvParser::plan($p['rows'],$store->snapshot()['right']);
            if($plan['conflict_count'])throw new InvalidArgumentException('既存データと重なる明細があります。取引IDまたは銀行別変換ルールを確認してください。');
            $db=$ctl->db(SampleReconciliationStore::RIGHT);$inserted=0;
            foreach($plan['rows'] as $row)if($row['import_status']==='追加'){
                unset($row['import_status']);$row['source_file']=$p['filename'];$db->insert($row);$inserted++;
            }
            $store->refresh();
        }catch(Throwable $e){$ctl->res_error_message('token',$e->getMessage());return;}
        // Keep the same preview grant: safe retry skips the same source keys, even after a partial insert.
        $ctl->close_multi_dialog('sample-statement-csv');$ctl->reload_work_area();$ctl->show_notification_text($inserted.'件を取り込みました。取込済み'.$plan['duplicate_count'].'件は保持しました。');
    }
}
