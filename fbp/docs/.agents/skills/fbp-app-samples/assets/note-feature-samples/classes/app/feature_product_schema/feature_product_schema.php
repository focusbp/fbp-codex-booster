<?php
require_once dirname(__DIR__,3).'/fbp/lib/ExcelGrid.php';
/** One source of field definitions/order; business roles stay attached to field identities. */
class feature_product_schema {
    const TABLE='feature_sample_products';
    public static function fields(Controller $ctl,bool $inputOnly=false):array {
        $cfg=ExcelGrid::config($ctl,self::TABLE);$fields=[];
        if($cfg){foreach(ExcelGrid::orderedNames($cfg) as $key){$c=ExcelGrid::column($key,$cfg['columns'][$key]??[]);if(!$c['generated']||($inputOnly&&$c['mode']!=='input'))continue;$fields[$key]=ExcelGrid::field($key,$c);}}
        else foreach($ctl->get_field_list(self::TABLE,'add') as $f){if(!isset(ExcelGrid::TYPES[$f['type']]))continue;$f['excel_mode']='input';$f['options']=$f['type']==='dropdown'?$ctl->get_constant_array($f['constant_array_name']??'',false):[];$fields[$f['parameter_name']]=$f;}
        return $fields;
    }
    public static function roles(Controller $ctl):array {
        return ExcelGrid::config($ctl,self::TABLE)?['code'=>'a','name'=>'b','price'=>'c','memo'=>'d']:['code'=>'code','name'=>'name','price'=>'price','memo'=>'memo'];
    }
    public static function product(Controller $ctl,array $row):array {
        $out=['id'=>$row['id']??0];foreach(self::roles($ctl) as $role=>$key)$out[$role]=$row[$key]??'';return $out;
    }
    public static function rows(Controller $ctl):array {
        $excel=(bool)ExcelGrid::config($ctl,self::TABLE);return array_values($ctl->db(self::TABLE)->getall($excel?'excel_sort':'id',SORT_ASC));
    }
    public static function fingerprint(Controller $ctl):string{return hash('sha256',json_encode(self::fields($ctl),JSON_UNESCAPED_UNICODE));}
    public static function text(Controller $ctl,array $field,$value):string {
        if($field['type']==='dropdown')return (string)($field['options'][(string)$value]??$value);
        return $ctl->create_ValueFormatter()->format_by_type($field['type'],$value);
    }
    /** Checkout values belong to this note; the key makes interrupted retries idempotent. */
    public static function saveSubmission(Controller $ctl,string $key,array $values):array {
        $db=$ctl->db(self::TABLE);$existing=$db->filter('checkout_key',$key,true);
        if($existing)return ['id'=>(int)$existing[0]['id'],'errors'=>ExcelGrid::errors($ctl,self::TABLE,(int)$existing[0]['id'])];
        $fields=self::fields($ctl,true);$row=[];
        foreach($values as $name=>$value){if(!isset($fields[$name]))throw new InvalidArgumentException('項目設定が変わりました。入力し直してください。');$row[$name]=$value;}
        if(ExcelGrid::config($ctl,self::TABLE)){ExcelGrid::guardInput($ctl,self::TABLE,$row);$max=0;foreach($db->getall() as $r)$max=max($max,(int)($r['excel_sort']??0));$row['excel_sort']=$max+1;}
        else{$ctl->validate(self::TABLE,array_keys($row),$row,false);if($ctl->count_res_error_message())throw new InvalidArgumentException('入力内容を確認してください。');}
        $row['checkout_key']=$key;$id=$db->insert($row);$errors=self::recalculate($ctl,$db->get($id),'add');return ['id'=>(int)$id,'errors'=>$errors];
    }
    public static function recalculate(Controller $ctl,array $row,string $from):array {
        $note=$ctl->get_db_setting(self::TABLE);
        return ExcelGrid::recalculate($ctl,self::TABLE,$row,function(array $after)use($ctl,$note,$from){
            $class=trim((string)($note['post_action_class']??''));if($class==='')return;
            if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$class))throw new RuntimeException('関数設定を確認してください。');
            if(!class_exists($class,false))require_once $ctl->dirs->get_class_dir($class).'/'.$class.'.php';
            $r=new ReflectionClass($class);$ctor=$r->getConstructor();$hook=$ctor&&$ctor->getNumberOfParameters()>0?new $class($ctl):new $class();
            $post=['id'=>$ctl->encrypt($after['id']),'_post_action_table'=>self::TABLE,'_post_action_from'=>$from,'_post_action_after'=>json_encode($after,JSON_UNESCAPED_UNICODE),'_post_action_excel_errors'=>json_encode(ExcelGrid::errors($ctl,self::TABLE,(int)$after['id']),JSON_UNESCAPED_UNICODE)];
            $previous=$_POST;$count=$ctl->count_res_error_message();try{$_POST=$post;$hook->run($ctl);}finally{$_POST=$previous;}
            if($ctl->count_res_error_message()>$count)throw new RuntimeException('関数の計算に失敗しました。');
        });
    }
}
