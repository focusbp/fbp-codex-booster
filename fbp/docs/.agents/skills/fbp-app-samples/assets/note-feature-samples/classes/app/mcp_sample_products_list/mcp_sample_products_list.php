<?php
/** Read-only sample dataset, no private intake/order information is exposed. */
require_once __DIR__.'/../feature_product_schema/feature_product_schema.php';
class mcp_sample_products_list implements McpFunctionInterface {
    public function getInputSchema(Controller $ctl,array $function):array {
        return ['type'=>'object','properties'=>['query'=>McpInputValidator::stringSchema('全入力項目から部分一致検索',['maxLength'=>200]),'limit'=>McpInputValidator::integerSchema('取得件数',['minimum'=>1,'maximum'=>100])],'additionalProperties'=>false];
    }
    public function getOutputSchema(Controller $ctl,array $function):array {return ['type'=>'object','additionalProperties'=>true];}
    public function execute(Controller $ctl,McpFunctionRequest $request):McpActionResult {
        $limit=McpInputValidator::integer($request,'limit',['default'=>20,'minimum'=>1,'maximum'=>100]);
        $fields=feature_product_schema::fields($ctl);$query=McpInputValidator::string($request,'query',['default'=>'','maxLength'=>200]);$rows=feature_product_schema::rows($ctl);if($query!=='')$rows=array_values(array_filter($rows,function($row)use($ctl,$fields,$query){foreach($fields as $key=>$field)if(mb_stripos(feature_product_schema::text($ctl,$field,$row[$key]??''),$query)!==false)return true;return false;}));$rows=array_slice($rows,0,$limit);
        $items=[];foreach($rows as $row){$item=['id'=>(int)$row['id']];foreach($fields as $key=>$field)$item[$key]=$row[$key]??'';$items[]=$item;}$columns=[];foreach($fields as $key=>$field)$columns[]=['key'=>$key,'title'=>$field['parameter_title'],'type'=>$field['type']];
        return McpFunctionResult::success('商品サンプルを取得しました。',['columns'=>$columns,'items'=>$items,'count'=>count($items)]);
    }
}
