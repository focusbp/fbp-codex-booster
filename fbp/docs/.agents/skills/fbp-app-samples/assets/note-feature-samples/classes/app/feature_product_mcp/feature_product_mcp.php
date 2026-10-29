<?php
require_once __DIR__.'/../feature_product_schema/feature_product_schema.php';
require_once __DIR__.'/../feature_excel_ocr/feature_excel_ocr.php';
/** Sample-note CRUD only. Public intakes/orders are never exposed. */
class feature_product_mcp {
    static function schema(Controller $ctl,string $action):array {
        $properties=[];$required=[];
        if($action!=='add'){$properties['id']=McpInputValidator::integerSchema('対象行ID',['minimum'=>1]);$required[]='id';}
        if($action!=='delete'){$fields=[];foreach(feature_product_schema::fields($ctl,true) as $key=>$f)$fields[$key]=['description'=>$f['parameter_title'],'type'=>['string','number','integer','boolean']];$properties['values']=['type'=>'object','properties'=>$fields,'additionalProperties'=>false,'minProperties'=>1];$required[]='values';}
        $properties['confirm']=['type'=>'boolean','description'=>'変更内容を確認した場合にtrue'];$required[]='confirm';
        return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
    }
    static function execute(Controller $ctl,McpFunctionRequest $request,string $action):McpActionResult {
        if($request->subjectType()!=='fbp_user'||$request->subjectId()<=0)throw new RuntimeException('管理者のOAuth認証が必要です。');
        $user=$ctl->db('user','user')->get($request->subjectId());if(!$user||(int)$user['status']!==0||(int)$user['user_type']!==0)throw new RuntimeException('このサンプルの変更操作は管理者専用です。');
        if($request->get('confirm')!==true)throw new InvalidArgumentException('変更内容を確認してください。');
        $ctl->db('settings','excel_grid');$ctl->db('states','excel_grid');$db=$ctl->db(feature_product_schema::TABLE);
        $cfg=ExcelGrid::config($ctl,feature_product_schema::TABLE);$id=$action==='add'?0:McpInputValidator::integer($request,'id',['minimum'=>1]);$before=$id?$db->get($id):null;
        if($id&&!$before)throw new InvalidArgumentException('対象行が見つかりません。');
        if($action==='delete'){$db->delete($id);return McpFunctionResult::success('削除しました。',['id'=>$id]);}
        $values=$request->get('values');if(!is_array($values)||!$values||array_is_list($values))throw new InvalidArgumentException('入力項目を指定してください。');
        $fields=feature_product_schema::fields($ctl,true);$row=[];
        foreach($values as $key=>$value){if(!isset($fields[$key]))throw new InvalidArgumentException('現在の入力項目以外は指定できません。');$f=$fields[$key];$row[$key]=feature_excel_ocr::normalize(ExcelGrid::column($key,['type'=>$f['type'],'length'=>$f['length'],'options'=>$f['options']??[]]),$value);}
        if($cfg)ExcelGrid::guardInput($ctl,feature_product_schema::TABLE,$row);else{$ctl->validate(feature_product_schema::TABLE,array_keys($fields),array_replace($before??[],$row),false);if($ctl->count_res_error_message())throw new InvalidArgumentException('ノートの入力条件を確認してください。');}
        if($action==='add'){if($cfg){$max=0;foreach($db->getall() as $r)$max=max($max,(int)($r['excel_sort']??0));$row['excel_sort']=$max+1;}$id=$db->insert($row);}else{$row['id']=$id;$db->update($row);}
        $errors=feature_product_schema::recalculate($ctl,$db->get($id),$action==='add'?'add':'edit');
        return McpFunctionResult::success('保存しました。',['id'=>$id,'calculation_errors'=>$errors]);
    }
}
