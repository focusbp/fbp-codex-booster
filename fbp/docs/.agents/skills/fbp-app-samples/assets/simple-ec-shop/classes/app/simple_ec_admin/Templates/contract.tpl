<form id="sec_contract_form" onsubmit="return false">
<p>開始日：{if $contract.regist_date}{$contract.regist_date|date_format:'%Y/%m/%d'}{else}未設定{/if}{if $contract.end_date} ／ 終了日：{$contract.end_date|date_format:'%Y/%m/%d'}{/if}</p>
<input type="hidden" name="id" value="{$contract_token|escape}">
{fields_form_direct db="sec_contracts" fields=['name','mail','item_name','getugaku','status'] data=$contract item_margin_top="12px"}
<div style="display:flex;justify-content:flex-end;margin-top:20px"><button class="ajax-link" type="button" data-class="simple_ec_admin" data-function="contract_save" data-form="sec_contract_form">保存</button></div>
</form>
