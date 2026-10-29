<p>現在の入力項目に合わせた読み取り結果です。各行の空欄・誤読を修正してから保存してください。</p>
<form id="excel_ocr_review" onsubmit="return false;">
<input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token"></p>
{foreach $rows as $index=>$row}
<fieldset><legend>{$index+1}件目</legend>
{foreach $fields as $name=>$f}
{if $multiple}{assign var=input_name value="r`$index`_`$name`"}{else}{assign var=input_name value=$name}{/if}
{fields_form_original name=$input_name type=$f.type value=$row[$name] title=$f.parameter_title options_arr=$f.options}<p class="error_message error_{$input_name|escape}"></p>{if $multiple}<p class="error_message error_{$name|escape}"></p>{/if}
{/foreach}</fieldset>
{/foreach}<p class="error_message error_result"></p>
<div class="button_row"><button type="button" class="ajax-link" data-class="{$ocr_class|escape}" data-function="save" data-form="excel_ocr_review">確認して{$rows|count}行追加</button></div></form>
