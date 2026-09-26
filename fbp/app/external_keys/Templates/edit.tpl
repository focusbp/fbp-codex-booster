{* Dedicated secret form: never populate the stored value, even as a masked input value. *}
<form id="external_key_edit_form" autocomplete="off" onsubmit="return false;">
    <input type="hidden" name="id" value="{$item.id}">
    <p class="error_message error_id"></p>
    <div>
        <label for="external_key_title">{t key="external_keys.title"}</label>
        <input id="external_key_title" type="text" name="title" maxlength="255" value="{$item.title|escape:'html'}">
        <p class="error_message error_title"></p>
    </div>
    <div>
        <label for="external_key_name">{t key="external_keys.key"}</label>
        <input id="external_key_name" type="text" name="key" maxlength="255" value="{$item.key|escape:'html'}" spellcheck="false">
        <p>{t key="external_keys.key_help"}</p>
        <p class="error_message error_key"></p>
    </div>
    <div>
        <label for="external_key_secret">{t key="external_keys.value"}</label>
        <input id="external_key_secret" type="password" name="external_key_secret" value="" maxlength="8192" autocomplete="new-password">
        <p>{if $item.id > 0}{t key="external_keys.keep_help"}{else}{t key="external_keys.value_help"}{/if}</p>
        <p class="error_message error_external_key_secret"></p>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
        <button type="button" class="ajax-link" data-class="external_keys" data-function="save" data-form="external_key_edit_form">{t key="external_keys.save"}</button>
    </div>
</form>
