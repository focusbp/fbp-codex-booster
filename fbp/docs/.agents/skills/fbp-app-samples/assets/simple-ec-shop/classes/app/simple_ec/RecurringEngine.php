<?php
require_once __DIR__.'/Store.php';
/** Scheduler-independent state machine. Provider injection permits offline verification. */
final class SimpleEcRecurringEngine {
    public static function process(array $db,callable $provider,string $month,bool $sandboxOnly=false,?int $onlyId=null,?callable $notify=null,?callable $errorContent=null,?string $notBeforeMonth=null,?callable $eligible=null): array {
        $report=['succeeded'=>0,'failed'=>0,'held'=>0,'recovered'=>0];
        if($notBeforeMonth!==null && $month<$notBeforeMonth)return $report;
        $due=SimpleEcStore::due($db['contracts']->getall(),$month);
        foreach($due as $contract){
            $preview=str_starts_with((string)$contract['record_key'],'preview:');if($sandboxOnly?!$preview:$preview)continue;
            if($eligible && !$eligible($contract))continue;
            if($onlyId!==null&&(int)$contract['id']!==$onlyId)continue;
            $key=SimpleEcStore::paymentKey((string)$contract['id'],$month);$rows=$db['payments']->select('idempotency_key',$key);$p=$rows[0]??[];
            if(($p['state']??'')==='succeeded'){self::applySuccess($db,$contract,$p,$month);$report['recovered']++;continue;}
            if($p){$report['held']++;continue;}
            $p=['contract_id'=>$contract['id'],'name'=>$contract['name']??'','mail'=>$contract['mail']??'','status'=>1,'environment'=>$sandboxOnly?'sandbox':'production','login_id'=>$contract['login_id'],'period'=>$month,'amount'=>(int)$contract['getugaku'],'state'=>'unknown','result'=>'結果確認中','idempotency_key'=>$key,'source'=>$sandboxOnly?'Sandboxサブスクリプション':'サブスクリプション','created_at'=>time()];
            $db['payments']->insert($p);
            try{
                $result=$provider($contract,$key);
                if(!is_array($result)||empty($result['id'])||($result['status']??'')!=='COMPLETED'||(int)($result['amount_money']['amount']??-1)!==(int)$contract['getugaku']||($result['amount_money']['currency']??'')!=='JPY')throw new RuntimeException('決済結果の照合が必要です。');
                $p['status']=0;$p['state']='succeeded';$p['result']=$sandboxOnly?'Sandbox成功':'決済成功';$p['payment_id']=$result['id'];$p['error']='';$db['payments']->update($p);self::applySuccess($db,$contract,$p,$month);$report['succeeded']++;
            }catch(Throwable $e){
                $p['error']=mb_substr($e->getMessage(),0,500);$db['payments']->update($p);
                $contract['status']=2;$contract['error']=$p['error'];$db['contracts']->update($contract);$report['held']++;
                $event='recurring-error:'.$key;
                $content=$errorContent?$errorContent($contract):['subject'=>'定期決済の確認が必要です','body'=>'決済結果を照合するまで再課金しません。'];
                SimpleEcStore::queue($db,$event,$contract['login_id'],$contract['mail'],$content['subject'],$content['body'],$sandboxOnly?'テスト送信待ち':'送信待ち');
                if($notify){try{$notify($event);}catch(Throwable $mailError){/* Payment result is independent of SMTP. */}}
            }
        }
        return $report;
    }
    private static function applySuccess(array $db,array $c,array $p,string $month): void {
        $last=$c['date_last_kessai']??0;$ts=is_numeric($last)?(int)$last:strtotime($last);
        if(!$ts || date('Y-m',$ts)<$month)$c['date_last_kessai']=strtotime($month.'-01');
        $c['error']='';
        $db['contracts']->update($c);
    }
}
