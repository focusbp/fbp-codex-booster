<?php
/** Stable semantic IDs; labels and the enabled subset come from one option set. */
final class SimpleEcTypes {
    public static function labels(Controller $ctl): array {
        $labels=$ctl->get_constant_array('sec_product_types',false);$out=[];
        foreach($labels as $key=>$label){
            if(!preg_match('/^[0-4]$/D',(string)$key) || !is_string($label) || trim($label)==='')throw new RuntimeException('受付種別の選択肢を確認してください。');
            $out[(int)$key]=$label;
        }
        if(!$out)throw new RuntimeException('受付種別を1つ以上登録してください。');
        return $out;
    }
    public static function allows(Controller $ctl,int $type): bool {return array_key_exists($type,self::labels($ctl));}
    public static function requireType(Controller $ctl,int $type): void {if(!self::allows($ctl,$type))throw new RuntimeException('この受付種別は利用していません。');}
}
