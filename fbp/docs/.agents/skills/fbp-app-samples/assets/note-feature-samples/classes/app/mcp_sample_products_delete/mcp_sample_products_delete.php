<?php
require_once __DIR__.'/../feature_product_mcp/feature_product_mcp.php';
class mcp_sample_products_delete implements McpFunctionInterface {
    function getInputSchema(Controller $ctl,array $function):array {return feature_product_mcp::schema($ctl,'delete');}
    function getOutputSchema(Controller $ctl,array $function):array {return ['type'=>'object','additionalProperties'=>true];}
    function execute(Controller $ctl,McpFunctionRequest $request):McpActionResult {return feature_product_mcp::execute($ctl,$request,'delete');}
}
