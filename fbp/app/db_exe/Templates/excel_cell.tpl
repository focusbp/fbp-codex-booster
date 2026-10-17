<form class="excel-editor-form" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$db_id}"><input type="hidden" name="excel_token" value="{$excel_token}">
{if $excel_readonly}<p>自動計算の列です。</p>{else}{fields_form_direct field_group=[$excel_field] data=$row item_margin_top="0px"}<p class="error_{$excel_col} error_message"></p>{/if}
<p class="error_excel_error error_message" role="alert">{$excel_error_text|escape}</p>
<div class="button_row">{if $excel_readonly && $excel_error_text}<button class="ajax-link" data-class="db_exe" data-function="excel_retry">再計算</button>{/if}{if !$excel_readonly}<button class="ajax-link excel-confirm" data-class="db_exe" data-function="excel_save">確定</button>{/if}</div>
</form>
{include file="excel_editor_style.tpl"}
