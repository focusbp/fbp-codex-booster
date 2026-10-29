<form class="excel-conversion-form" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$db_id}">
<input type="hidden" name="conversion_key" value="{$conversion.key|escape}">
<input type="hidden" name="step" value="{$conversion.step}">
{if $conversion.step < 4}
<p>{$conversion.step+1} / 4　<strong>{$conversion_screens[$conversion_screen]|escape}</strong>画面に表示する項目を選択してください。</p>
<div class="excel-conversion-choices">
{foreach $conversion_fields as $name=>$field}
<label><input type="checkbox" name="fields[]" value="{$name|escape}" {if in_array($name,$conversion.screens[$conversion_screen])}checked{/if}> {$field.parameter_title|escape}{if $field.excel_mode != 'input'}（自動計算）{/if}</label>
{/foreach}
</div>
{if $conversion_screen == 'list'}<p>一覧は1項目以上選択してください。</p>{else}<p>項目を選択しない場合、この操作のボタンは表示されません。</p>{/if}
{else}
<p>次の設定でデータベース形式に変換します。登録済みのデータは引き継がれます。</p>
{foreach $conversion_screens as $screen=>$label}
<p><strong>{$label|escape}</strong>：{foreach $conversion.screens[$screen] as $name}{$conversion_fields[$name].parameter_title|escape}{if !$name@last}、{/if}{foreachelse}表示しない{/foreach}</p>
{/foreach}
<p>Excel風に戻す場合は、プロジェクトサポートからその旨の指示をしてください。</p>
{/if}
<p class="error_conversion_error error_message" role="alert"></p>
<div class="button_row">
{if $conversion.step > 0 && !$conversion.pending}<button type="button" class="ajax-link" data-class="db_exe" data-function="excel_convert_step" data-direction="back">戻る</button>{/if}
{if $conversion.step < 4}<button type="button" class="ajax-link" data-class="db_exe" data-function="excel_convert_step" data-direction="next">次へ</button>
{else}<button type="button" class="ajax-link" data-class="db_exe" data-function="excel_convert_apply">OK</button>{/if}
</div>
</form>
<style>{literal}
.excel-conversion-form{padding:8px;line-height:1.7}.excel-conversion-choices{display:grid;gap:10px;max-height:45vh;overflow:auto;padding:10px 0}.excel-conversion-choices label{display:flex;align-items:center;gap:8px}.excel-conversion-choices input{width:auto;margin:0}.excel-conversion-form .button_row{display:flex;justify-content:flex-end;gap:10px}.excel-conversion-form .error_message:empty{display:none}
{/literal}</style>
