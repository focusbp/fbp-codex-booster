<?php
require_once __DIR__.'/Store.php';
class sample_reconciliation implements CodegenActionInterface {
    private const DIALOG='sample-reconciliation';
    private const SESSION='sample_reconciliation_tokens';
    private function allowed(Controller $ctl):bool {
        if($ctl->get_session('login')&&$ctl->is_app_admin())return true;
        $ctl->show_notification_text('この操作を行う権限がありません。');return false;
    }
    function run(Controller $ctl):void {$this->left($ctl);}
    function left(Controller $ctl):void {$this->show($ctl,'left','add');}
    function right(Controller $ctl):void {$this->show($ctl,'right','add');}
    function unlink_left(Controller $ctl):void {$this->show($ctl,'left','remove');}
    function unlink_right(Controller $ctl):void {$this->show($ctl,'right','remove');}
    function detail_left(Controller $ctl):void {$this->show($ctl,'left','detail');}
    function detail_right(Controller $ctl):void {$this->show($ctl,'right','detail');}
    private function requestId(Controller $ctl):int {
        $raw=$ctl->POST('id');if(!is_string($raw))return 0;
        $id=$ctl->decrypt($raw);return ctype_digit((string)$id)?(int)$id:0;
    }
    private function view(array $row):array {
        $row['date_text']=empty($row['occurred_on'])?'':date('Y/m/d',(int)$row['occurred_on']);
        $row['amount_text']=number_format(SampleReconciliationGraph::money($row['amount']));return $row;
    }
    private function show(Controller $ctl,string $side,string $mode):void {
        if(!$this->allowed($ctl))return;
        try{
            $store=new SampleReconciliationStore($ctl);$store->recover();$store->refresh();$s=$store->snapshot();$id=$this->requestId($ctl);
            if(!isset($s[$side][$id]))throw new InvalidArgumentException('対象明細を取得できません。');
            $origin=$s[$side][$id];$opposite=$side==='left'?'right':'left';$edges=SampleReconciliationGraph::edges($s['links']);
            $component=SampleReconciliationGraph::component($edges,$side,$id);
            $totals=SampleReconciliationGraph::totals($s['left'],$s['right'],$component);
            $direct=[];$current=[];
            foreach($s['links'] as $link)if((int)$link['active']===1&&(int)$link[$side==='left'?'recognition_id':'receipt_id']===$id){
                $target=(int)$link[$side==='left'?'receipt_id':'recognition_id'];$direct[$target]=true;
                $current[]=['link_id'=>(int)$link['id'],'item'=>$this->view($s[$opposite][$target])];
            }
            $post=$ctl->POST();$keyword=trim((string)($post['keyword']??''));$status=(string)($post['relation_filter']??'');
            $start=$this->dateSerial($post['start_on']??'');$end=$this->dateSerial($post['end_on']??'');
            $candidates=[];
            foreach($s[$opposite] as $target=>$row){
                if($row['scope_key']!==$origin['scope_key']||$row['currency']!==$origin['currency']||(int)$row['eligibility']!==0)continue;
                $v=$this->view($row);$serial=$this->dateSerial($row['occurred_on']);
                if(($start&&$serial<$start)||($end&&$serial>$end))continue;
                if($keyword!==''&&mb_stripos(implode(' ',[$row['counterparty'],$row['description'],$row['amount'],$v['amount_text']]),$keyword)===false)continue;
                if(($status==='linked'&&(int)$row['link_count']===0)||($status==='unlinked'&&(int)$row['link_count']>0))continue;
                $v['already_linked']=isset($direct[$target]);
                $c=SampleReconciliationGraph::component($edges,$opposite,(int)$target);$v['component_json']=json_encode($c,JSON_THROW_ON_ERROR);
                $candidates[]=$v;
            }
            usort($candidates,fn($a,$b)=>((int)$b['occurred_on']<=>(int)$a['occurred_on'])?:((int)$b['id']<=>(int)$a['id']));
            $count=count($candidates);$page=max(1,min(max(1,(int)ceil($count/100)),(int)($post['page']??1)));$candidates=array_slice($candidates,($page-1)*100,100);
            $tokens=$ctl->get_session(self::SESSION);if(!is_array($tokens))$tokens=[];
            foreach($tokens as $k=>$v)if($v['expires']<time())unset($tokens[$k]);
            if(count($tokens)>=30)$tokens=array_slice($tokens,-29,null,true);
            $token=bin2hex(random_bytes(24));$tokens[$token]=['side'=>$side,'id'=>$id,'mode'=>$mode,'expected'=>$store->fingerprint(),'expires'=>time()+1800,'done'=>false,'request'=>null];
            $ctl->set_session(self::SESSION,$tokens);
            $amounts=[];foreach(['left','right'] as $cs)foreach($s[$cs] as $rid=>$r)if($r['scope_key']===$origin['scope_key']&&$r['currency']===$origin['currency'])$amounts[$cs][(string)$rid]=SampleReconciliationGraph::money($r['amount']);
            $details=[];foreach(['left','right'] as $cs){$details[$cs]=[];foreach($component[$cs] as $rid)$details[$cs][]=$this->view($s[$cs][$rid]);}
            $events=[];$componentLinks=[];foreach($s['links'] as $l)if(in_array((int)$l['recognition_id'],$component['left'],true)||in_array((int)$l['receipt_id'],$component['right'],true))$componentLinks[(int)$l['id']]=true;
            foreach($ctl->db(SampleReconciliationStore::EVENTS)->getall('id',SORT_DESC) as $e)if(isset($componentLinks[(int)$e['link_id']]))$events[]=$e;
            $ctl->assign('origin',$this->view($origin));$ctl->assign('side_label',$side==='left'?'計上':'入金');$ctl->assign('opposite_label',$side==='left'?'入金':'計上');
            $ctl->assign('form',['id'=>$ctl->encrypt((string)$id),'keyword'=>$keyword,'start_on'=>$post['start_on']??'','end_on'=>$post['end_on']??'','relation_filter'=>$status]);
            $ctl->assign('mode',$mode);$ctl->assign('token',$token);$ctl->assign('totals',$totals);$ctl->assign('current',$current);$ctl->assign('candidates',$candidates);$ctl->assign('candidate_count',$count);$ctl->assign('page_no',$page);$ctl->assign('page_count',max(1,(int)ceil($count/100)));
            $ctl->assign('run_function',($mode==='remove'?'unlink_':($mode==='detail'?'detail_':'' )).$side);
            $ctl->assign('amounts_json',json_encode($amounts,JSON_THROW_ON_ERROR));$ctl->assign('component_json',json_encode($component,JSON_THROW_ON_ERROR));
            $ctl->assign('details',$details);$ctl->assign('events',array_slice($events,0,100));$ctl->assign('event_count',count($events));
            $ctl->show_multi_dialog(self::DIALOG,'dialog.tpl',$mode==='add'?'複数突合':($mode==='remove'?'突合解除':'グループ詳細'),1100);
        }catch(Throwable $e){$ctl->show_notification_text($e->getMessage());}
    }
    private function dateSerial($v):int {
        if((string)$v==='')return 0;
        if(ctype_digit((string)$v))return (int)date('Ymd',(int)$v);
        $t=strtotime((string)$v);return $t===false?0:(int)date('Ymd',$t);
    }
    function save(Controller $ctl):void {
        if(!$this->allowed($ctl))return;
        $token=$ctl->POST('token');$tokens=$ctl->get_session(self::SESSION);$pending=is_string($token)&&is_array($tokens)?($tokens[$token]??null):null;
        if(!$pending||$pending['expires']<time()||!in_array($pending['mode'],['add','remove'],true)){$ctl->res_error_message('token','画面を開き直してください。');return;}
        $single=$ctl->POST('target_id');$targets=$single!==null?[$single]:($ctl->POST('target_ids')??[]);
        if(!is_array($targets)){$ctl->res_error_message('target_ids','明細を選択してください。');return;}
        foreach($targets as $v)if(!is_scalar($v)||!ctype_digit((string)$v)||(int)$v<=0){$ctl->res_error_message('target_ids','選択値が不正です。');return;}
        $rawLink=$ctl->POST('link_id')??'0';
        if(!is_scalar($rawLink)||!ctype_digit((string)$rawLink)){$ctl->res_error_message('link_id','選択値が不正です。');return;}
        $linkId=(int)$rawLink;
        $request=hash('sha256',json_encode([$targets,$linkId]));
        if($pending['done']){
            if(!hash_equals((string)$pending['request'],$request)){$ctl->res_error_message('token','実行済みの操作です。');return;}
            $ctl->close_multi_dialog(self::DIALOG);$ctl->reload_work_area();return;
        }
        try{
            $store=new SampleReconciliationStore($ctl);
            if($pending['mode']==='add')$store->add($pending['side'],$pending['id'],$targets,(int)$ctl->get_login_user_id(),$token,$pending['expected']);
            else $store->remove($pending['side'],$pending['id'],$linkId,(int)$ctl->get_login_user_id(),$token,$pending['expected']);
        }catch(Throwable $e){$ctl->res_error_message('token',$e->getMessage());return;}
        $tokens[$token]['done']=true;$tokens[$token]['request']=$request;$ctl->set_session(self::SESSION,$tokens);
        $ctl->close_multi_dialog(self::DIALOG);$ctl->reload_work_area();
        if($pending['mode']==='remove')$ctl->invoke('unlink_'.$pending['side'],['id'=>$ctl->encrypt((string)$pending['id'])]);
        $ctl->show_notification_text($pending['mode']==='add'?'紐付けを追加しました。':'選んだ紐付けを解除しました。');
    }
}
