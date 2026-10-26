<form id="sec-shop-form" onsubmit="return false">
<input type="hidden" name="id" value="{$shop_token|escape}">
{fields_form_direct db="sec_shops" fields=$shop_field_names data=$row item_margin_top="12px"}
<button type="button" class="ajax-link" data-class="simple_ec_shops" data-function="save" data-form="sec-shop-form">保存</button>
</form>
