<form id="sample_statement_csv_form" onsubmit="return false;">
    <p>UTF-8／Shift_JIS、2MB・5000行まで。先頭行は「入出金日,摘要,入金額,出金額,残高,取引ID」。取引ID列は省略できます。</p>
    <p>出金は記録を残して対象外にします。口座識別子は同じ口座の再取込でも同じ値を指定してください。</p>
    {fields_form_original name="scope_key" type="text" title="管理範囲" value="sample"}<p class="error_message error_scope_key"></p>
    {fields_form_original name="account_key" type="text" title="口座識別子（例：bank-main）" value=""}<p class="error_message error_account_key"></p>
    <label><input type="checkbox" name="exclude_interest" value="1" checked> 摘要が「利息」の行を対象外にする</label><p class="error_message error_exclude_interest"></p>
    {fields_form_original name="file" type="file" title="通帳CSV" value=""}<p class="error_message error_file" role="alert"></p>
    <div class="button_row"><button type="button" class="ajax-link" data-class="sample_statement_csv" data-form="sample_statement_csv_form" invoke-function="upload_exe">取込内容を確認</button></div>
</form>
<div id="sample_statement_csv_preview"></div>
