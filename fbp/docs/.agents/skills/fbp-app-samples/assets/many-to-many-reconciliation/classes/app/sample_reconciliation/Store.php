<?php
require_once __DIR__.'/Graph.php';
/** FFM adapter. Open all dependencies before reading any decision state. */
class SampleReconciliationStore {
    public const LEFT='sample_recognition_items';
    public const RIGHT='sample_receipt_items';
    public const LINKS='sample_reconciliation_links';
    public const EVENTS='sample_reconciliation_events';
    private array $db=[];
    public function __construct(Controller $ctl){foreach([self::LEFT,self::RIGHT,self::LINKS,self::EVENTS] as $table)$this->db[$table]=$ctl->db($table);}
    public function snapshot():array {
        return ['left'=>array_column($this->db[self::LEFT]->getall('id',SORT_ASC),null,'id'),
            'right'=>array_column($this->db[self::RIGHT]->getall('id',SORT_ASC),null,'id'),
            'links'=>$this->db[self::LINKS]->getall('id',SORT_ASC)];
    }
    public function fingerprint():string {$s=$this->snapshot();return SampleReconciliationGraph::fingerprint($s['left'],$s['right'],$s['links']);}
    /** Recover a journaled relationship change before accepting another change. */
    public function recover():void {
        $events=[];foreach($this->db[self::EVENTS]->getall('id',SORT_ASC) as $row)$events[$row['event_key']]=true;
        foreach($this->db[self::LINKS]->getall('id',SORT_ASC) as $link){
            if(trim((string)($link['pending_event']??''))==='')continue;
            $event=json_decode($link['pending_event'],true,512,JSON_THROW_ON_ERROR);
            $event['link_id']=(int)$link['id'];$event['event_key']=$link['id'].':'.$link['revision'];
            if(!isset($events[$event['event_key']])){$this->db[self::EVENTS]->insert($event);$events[$event['event_key']]=true;}
            $link['pending_event']='';$this->db[self::LINKS]->update($link);
        }
    }
    private function change(?array $link,int $lid,int $rid,bool $active,int $actor,string $operation,array $left,array $right):void {
        if($link!==null&&(int)$link['active']===(int)$active)return;
        $event=['action'=>$active?'add':'remove','actor_id'=>$actor,'occurred_at'=>time(),'operation_id'=>$operation,
            'recognition_id'=>$lid,'receipt_id'=>$rid,'recognition_amount'=>$left['amount'],'receipt_amount'=>$right['amount'],'method'=>'manual'];
        $row=array_replace($link??[],['recognition_id'=>$lid,'receipt_id'=>$rid,'scope_key'=>$left['scope_key'],'active'=>(int)$active,
            'revision'=>(int)($link['revision']??0)+1,'updated_at'=>time(),'updated_by'=>$actor,'pending_event'=>json_encode($event,JSON_THROW_ON_ERROR)]);
        if($link===null){$row['created_at']=time();$row['created_by']=$actor;$this->db[self::LINKS]->insert($row);}
        else $this->db[self::LINKS]->update($row);
        // A pending event lives in the SAME relationship row as its state. Retrying repairs history.
        $this->recover();
    }
    public function add(string $side,int $origin,array $targets,int $actor,string $operation,string $expected):void {
        $this->recover();$s=$this->snapshot();
        if(!hash_equals($expected,SampleReconciliationGraph::fingerprint($s['left'],$s['right'],$s['links'])))throw new InvalidArgumentException('データが更新されています。突合画面を開き直してください。');
        if(!in_array($side,['left','right'],true)||!isset($s[$side][$origin])||!$targets)throw new InvalidArgumentException('追加する明細を選択してください。');
        $targets=array_values(array_unique(array_map('intval',$targets)));if(count($targets)>100)throw new InvalidArgumentException('一度に追加できるのは100件までです。');
        $pairs=[];
        foreach($targets as $id){$lid=$side==='left'?$origin:$id;$rid=$side==='right'?$origin:$id;
            if(!isset($s['left'][$lid],$s['right'][$rid]))throw new InvalidArgumentException('対象明細を取得できません。');
            SampleReconciliationGraph::validatePair($s['left'][$lid],$s['right'][$rid]);$pairs[]=[$lid,$rid];}
        $byPair=[];foreach($s['links'] as $row){$key=$row['recognition_id'].':'.$row['receipt_id'];if(isset($byPair[$key]))throw new RuntimeException('関係が重複しています。');$byPair[$key]=$row;}
        foreach($pairs as [$lid,$rid])$this->change($byPair[$lid.':'.$rid]??null,$lid,$rid,true,$actor,$operation,$s['left'][$lid],$s['right'][$rid]);
        $this->refresh();
    }
    public function remove(string $side,int $origin,int $linkId,int $actor,string $operation,string $expected):void {
        $this->recover();$s=$this->snapshot();
        if(!hash_equals($expected,SampleReconciliationGraph::fingerprint($s['left'],$s['right'],$s['links'])))throw new InvalidArgumentException('データが更新されています。解除画面を開き直してください。');
        $link=$this->db[self::LINKS]->get($linkId);
        if(!in_array($side,['left','right'],true)||!$link||(int)$link[$side==='left'?'recognition_id':'receipt_id']!==$origin)throw new InvalidArgumentException('この明細の直接の紐付けを選択してください。');
        $lid=(int)$link['recognition_id'];$rid=(int)$link['receipt_id'];
        if(!isset($s['left'][$lid],$s['right'][$rid]))throw new InvalidArgumentException('対象明細を取得できません。');
        $this->change($link,$lid,$rid,false,$actor,$operation,$s['left'][$lid],$s['right'][$rid]);$this->refresh();
    }
    /** Derived list columns only. Never rewrites source amounts or relationships. */
    public function refresh():void {
        $s=$this->snapshot();$edges=SampleReconciliationGraph::edges($s['links']);$done=[];
        foreach(['left'=>self::LEFT,'right'=>self::RIGHT] as $side=>$table)foreach($s[$side] as $id=>$row){
            $key=$side.':'.$id;
            if(!isset($done[$key])){
                $c=SampleReconciliationGraph::component($edges,$side,(int)$id);$t=SampleReconciliationGraph::totals($s['left'],$s['right'],$c);
                foreach(['left','right'] as $cs)foreach($c[$cs] as $cid)$done[$cs.':'.$cid]=$t;
            }
            $t=$done[$key];$count=0;foreach($s['links'] as $link)if((int)$link['active']===1&&(int)$link[$side==='left'?'recognition_id':'receipt_id']===$id)$count++;
            $t['link_count']=$count;$updated=array_replace($row,$t);if($updated!=$row)$this->db[$table]->update($updated);
        }
    }
}
