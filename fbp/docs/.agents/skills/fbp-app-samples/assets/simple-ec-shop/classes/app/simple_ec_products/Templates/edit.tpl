<style>{literal}
#sec-product-form .sec-product-section {width:auto;max-width:100%;min-width:0;box-sizing:border-box;margin-top:20px;padding:16px;border:1px solid #ddd;border-radius:10px}
#sec-product-form .sec-paid,#sec-product-form .sec-variants {border:0;border-radius:0;padding:0}
body.fbp-standard-responsive .multi_dialog:has(#sec-product-form) {max-width:calc(100vw - 24px);left:50% !important;transform:translateX(-50%);box-sizing:border-box}
#sec-product-form .sec-conditional {display:none}
#sec-product-form:has([name="type"] option[value="0"]:checked) .sec-paid,
#sec-product-form:has([name="type"] option[value="1"]:checked) .sec-paid,
#sec-product-form:has([name="type"] option[value="4"]:checked) .sec-paid,
#sec-product-form:has([name="type"] option[value="0"]:checked) .sec-variants,
#sec-product-form:has([name="type"] option[value="1"]:checked) .sec-variants,
#sec-product-form:has([name="type"] option[value="1"]:checked) .sec-event,
#sec-product-form:has([name="type"] option[value="4"]:checked) .sec-recurring {display:block}
#sec-product-form .button_row {display:flex;justify-content:flex-end;margin-top:20px}
#sec-product-form .button_row button {float:none}
#sec-product-form .sec-variant-table {width:100%;table-layout:fixed;border-collapse:collapse;margin-top:18px}
#sec-product-form .sec-variant-table th,#sec-product-form .sec-variant-table td {border:1px solid #ddd;padding:12px;vertical-align:top;text-align:left;min-width:0}
#sec-product-form .sec-variant-table thead th {background:#f3f6f9;font-weight:600}
#sec-product-form .sec-variant-table tbody th {background:#f8fafc;white-space:nowrap;width:70px}
#sec-product-form .sec-variant-table .sec-variant-number {width:70px}
#sec-product-form .sec-variant-table .sec-variant-price,#sec-product-form .sec-variant-table .sec-variant-stock {width:22%}
#sec-product-form .sec-variant-table .row_value {display:block;min-width:0}
#sec-product-form .sec-variant-table .field_edit h6 {position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
#sec-product-form .sec-variant-table input[type=text] {width:100%;max-width:100%;min-width:0;box-sizing:border-box}
@media(max-width:700px){
 body.fbp-standard-responsive #sec-product-form .sec-variant-table,
 body.fbp-standard-responsive #sec-product-form .sec-variant-table tbody {display:block;width:100%}
 body.fbp-standard-responsive #sec-product-form .sec-variant-table colgroup,
 body.fbp-standard-responsive #sec-product-form .sec-variant-table thead {display:none}
 body.fbp-standard-responsive #sec-product-form .sec-variant-table tr {display:block;border:1px solid #ddd;border-radius:8px;margin-top:12px;overflow:hidden}
 body.fbp-standard-responsive #sec-product-form .sec-variant-table th,
 body.fbp-standard-responsive #sec-product-form .sec-variant-table td {display:block;width:auto;border:0;padding:10px 12px}
 body.fbp-standard-responsive #sec-product-form .sec-variant-table tbody th {background:#f3f6f9}
 body.fbp-standard-responsive #sec-product-form .sec-variant-table .field_edit h6 {position:static;width:auto;height:auto;overflow:visible;clip-path:none;margin-top:0}
}
{/literal}</style>
<form id="sec-product-form" onsubmit="return false">
<p>{$parent_shop.shopname|escape}</p>
<input type="hidden" name="parent_id" value="{$parent_shop.id|escape}">
<input type="hidden" name="owner_account" value="{$parent_shop.id|escape}">
<input type="hidden" name="id" value="{$product_token|escape}">
{fields_form_direct db="sec_products" fields=$groups.common data=$row item_margin_top="12px"}
<section class="sec-product-section sec-conditional sec-paid" aria-label="税率">{fields_form_direct db="sec_products" fields=$groups.paid data=$row item_margin_top="12px"}</section>
<section class="sec-product-section sec-conditional sec-variants">
<h3>種類・価格・在庫／参加枠</h3>
{fields_form_direct db="sec_products" fields="unit,purchase_limit,zaiko_check" data=$row item_margin_top="12px"}
<table class="sec-variant-table" aria-label="種類別のボタン名・価格・在庫">
<colgroup><col class="sec-variant-number"><col><col class="sec-variant-price"><col class="sec-variant-stock"></colgroup>
<thead><tr><th scope="col">種類</th><th scope="col">ボタン表示名</th><th scope="col">価格（税別）</th><th scope="col">在庫／参加枠</th></tr></thead>
<tbody>
{for $i=1 to 5}
{$code="code`$i`"}{$price="price`$i`"}{$stock="zaiko`$i`"}
<tr data-variant="{$i}">
<th scope="row">種類{$i}</th>
<td>{fields_form_direct db="sec_products" fields=$code data=$row item_margin_top="0"}</td>
<td>{fields_form_direct db="sec_products" fields=$price data=$row item_margin_top="0"}</td>
<td>{fields_form_direct db="sec_products" fields=$stock data=$row item_margin_top="0"}</td>
</tr>
{/for}
</tbody>
</table>
</section>
<section class="sec-product-section sec-conditional sec-event"><h3>開催日時</h3>{fields_form_direct db="sec_products" fields=$groups.event data=$row item_margin_top="12px"}</section>
<section class="sec-product-section sec-conditional sec-recurring"><h3>定期購入</h3>{fields_form_direct db="sec_products" fields=$groups.recurring data=$row item_margin_top="12px"}</section>
<div class="button_row"><button type="button" class="ajax-link" data-class="simple_ec_products" data-function="save" data-form="sec-product-form">保存</button></div>
</form>
<script>{literal}
(function(){
 const form=document.getElementById('sec-product-form');
 const payments={/literal}{$shop_payments_json nofilter}{literal};
 const shop=form.querySelector('[name="owner_account"]');
 const type=form.querySelector('[name="type"]');
 const category=form.querySelector('[name="category_id"]');
 const categoryIds={/literal}{$category_ids_json nofilter}{literal};
 for(const option of Array.from(category.options))if(option.value!=='0'&&!categoryIds.includes(option.value))option.remove();
 if(!Array.from(category.options).some(o=>o.value==='0'))category.add(new Option('カテゴリーなし','0'),0);
 category.value=String({/literal}{$selected_category}{literal});$(category).trigger('change');
 function updateTypes(){
   const paid=payments[shop.value]===true;
   for(const option of type.options){
     const allowed=paid || option.value==='2' || option.value==='3';
     option.disabled=!allowed;option.hidden=!allowed;
   }
   if(type.selectedOptions.length && type.selectedOptions[0].disabled){const first=Array.from(type.options).find(o=>!o.disabled);type.value=first?first.value:'';$(type).trigger('change');}
   $(type).trigger('change');
 }
 $(shop).on('change.rfPayments',updateTypes);updateTypes();
})();
{/literal}</script>
