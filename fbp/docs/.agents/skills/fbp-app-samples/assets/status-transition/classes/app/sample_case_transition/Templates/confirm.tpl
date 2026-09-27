<form onsubmit="return false;">
    <input type="hidden" name="token" value="{$token|escape}">
    <p class="error_message error_token" role="alert"></p>
    <p>{$case_title|escape}</p><p>{$current_label|escape} → <strong>{$next_label|escape}</strong></p>
    <div class="row_style">
        {fields_form_original name="note" type="textarea" value="" title="変更理由"}
        <p class="error_message error_note" role="alert"></p>
    </div>
    <div class="button_row"><button type="button" class="ajax-link" data-class="sample_case_transition" invoke-function="save">変更する</button></div>
</form>
