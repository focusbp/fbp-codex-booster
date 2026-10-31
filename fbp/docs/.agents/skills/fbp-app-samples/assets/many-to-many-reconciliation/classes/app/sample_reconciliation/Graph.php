<?php
/** Pure graph/calculation core. Money is integer yen in this JPY sample. */
class SampleReconciliationGraph {
    public static function money($value): int {
        $s=(string)$value;
        if(!preg_match('/^(0|[1-9][0-9]{0,11})$/D',$s))throw new InvalidArgumentException('金額は0〜999999999999の整数で入力してください。');
        return (int)$s;
    }
    public static function edges(array $links): array {
        $edges=[];
        foreach($links as $link)if((int)($link['active']??0)===1)$edges[(int)$link['recognition_id']][(int)$link['receipt_id']]=true;
        return $edges;
    }
    public static function component(array $edges,string $side,int $id):array {
        $left=$side==='left'?[$id=>true]:[];$right=$side==='right'?[$id=>true]:[];
        do {
            $count=count($left)+count($right);
            foreach($edges as $lid=>$rids)if(isset($left[$lid])||array_intersect_key($right,$rids)){$left[$lid]=true;$right+=$rids;}
        }while($count!==count($left)+count($right));
        $l=array_keys($left);$r=array_keys($right);sort($l,SORT_NUMERIC);sort($r,SORT_NUMERIC);
        return ['left'=>$l,'right'=>$r];
    }
    public static function totals(array $left,array $right,array $component):array {
        $ls=0;$rs=0;
        foreach($component['left'] as $id){if(!isset($left[$id]))throw new RuntimeException('計上明細への参照が切れています。');$ls+=self::money($left[$id]['amount']);}
        foreach($component['right'] as $id){if(!isset($right[$id]))throw new RuntimeException('入金明細への参照が切れています。');$rs+=self::money($right[$id]['amount']);}
        $hasLinks=(bool)$component['left']&&(bool)$component['right'];
        return ['group_recognition'=>$ls,'group_receipt'=>$rs,'group_difference'=>$rs-$ls,
            'relation_state'=>$hasLinks?'紐付けあり':'未紐付け',
            'balance_state'=>!$hasLinks?'未紐付け':($rs===$ls?'一致':($rs<$ls?'不足':'超過'))];
    }
    public static function validatePair(array $left,array $right):void {
        self::money($left['amount']);self::money($right['amount']);
        if((int)($right['withdrawal_amount']??0)>0)throw new InvalidArgumentException('出金の明細は入金突合できません。');
        if(trim((string)($left['scope_key']??''))===''||$left['scope_key']!==$right['scope_key'])throw new InvalidArgumentException('管理範囲が異なる明細は追加できません。');
        if(($left['currency']??'')!=='JPY'||($right['currency']??'')!=='JPY')throw new InvalidArgumentException('このサンプルはJPYのみ対応します。');
        if((int)($left['eligibility']??0)!==0||(int)($right['eligibility']??0)!==0)throw new InvalidArgumentException('保留・対象外の明細は追加できません。');
        // Deliberately no amount-equality condition.
    }
    public static function fingerprint(array $left,array $right,array $links):string {
        $all=[];
        foreach(['left'=>$left,'right'=>$right] as $side=>$rows){ksort($rows,SORT_NUMERIC);foreach($rows as $r){$all[$side][] = array_intersect_key($r,array_flip(['id','scope_key','currency','amount','eligibility','occurred_on','counterparty','description','withdrawal_amount','balance_amount']));}}
        usort($links,fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);
        foreach($links as $r)$all['links'][]=array_intersect_key($r,array_flip(['id','recognition_id','receipt_id','active','revision']));
        return hash('sha256',json_encode($all,JSON_THROW_ON_ERROR));
    }
}
