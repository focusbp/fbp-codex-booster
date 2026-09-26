<form id="external_key_delete_form" onsubmit="return false;">
    <input type="hidden" name="id" value="{$item.id}">
    <p class="error_message error_id"></p>
    <p>{t key="external_keys.delete_confirm"}</p>
    <p style="overflow-wrap:anywhere;">{$item.title|escape:'html'} ({$item.key|escape:'html'})</p>
    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
        <button type="button" class="ajax-link" data-class="external_keys" data-function="delete_exe" data-form="external_key_delete_form">{t key="external_keys.delete"}</button>
    </div>
</form>
