<?php
require_once __DIR__.'/../../lib/IntegrationSettings.php';

/** Signed server-to-server endpoint; CLI worker uses the same implementation. */
class integration_settings {
    function __construct(Controller $ctl){$ctl->set_check_login(false);}
    function execute(Controller $ctl){
        if(PHP_SAPI!=='cli'&&!$ctl->verify_api_request())exit;
        try{
            $p=PHP_SAPI==='cli'?$ctl->POST():json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);
            $p=array_intersect_key($p,array_flip(['operation','service','catalog','environment','request_id','revision','catalog_revision','changes']));
            unset($_POST['changes']);
            $op=$p['operation']??'';
            if($op==='catalog')$out=['catalog'=>IntegrationSettings::localCatalog($ctl)];
            elseif($op==='status')$out=IntegrationSettings::snapshot($ctl,(string)($p['service']??''),$p['catalog']??[]);
            elseif($op==='apply')$out=IntegrationSettings::apply($ctl,$p);
            else throw new DomainException('操作が不正です。');
            $out=['ok'=>true]+$out;
        }catch(DomainException $e){$out=['ok'=>false,'retryable'=>false,'message'=>$e->getMessage()];}
        catch(Throwable $e){$out=['ok'=>false,'retryable'=>true,'message'=>'設定を反映できませんでした。'];}
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($out,JSON_UNESCAPED_UNICODE);exit;
    }
}
