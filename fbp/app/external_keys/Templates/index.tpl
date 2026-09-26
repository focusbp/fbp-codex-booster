<div class="external-keys-panel">
    <p>{t key="external_keys.help"}</p>
    <p>{t key="external_keys.release_help"}</p>
    <form id="external_keys_search" onsubmit="return false;">
        <label for="external_keys_search_text">{t key="external_keys.search"}</label>
        <input id="external_keys_search_text" type="text" name="search" value="{$search|escape:'html'}">
        <span class="error_message error_search"></span>
        <button type="button" class="ajax-link" data-class="external_keys" data-function="page" data-form="external_keys_search">{t key="external_keys.search"}</button>
        <button type="button" class="ajax-link" data-class="external_keys" data-function="add">{t key="external_keys.add"}</button>
    </form>
    <table class="moredata" style="width:100%;table-layout:fixed;margin-top:12px;">
        <thead><tr><th>{t key="external_keys.title"}</th><th>{t key="external_keys.key"}</th><th>{t key="external_keys.status"}</th><th>{t key="external_keys.actions"}</th></tr></thead>
        <tbody>
        {foreach $items as $item}
            <tr>
                <td style="overflow-wrap:anywhere;">{$item.title|escape:'html'}</td>
                <td style="overflow-wrap:anywhere;">{$item.key|escape:'html'}</td>
                <td>{t key="external_keys.configured"}</td>
                <td>
                    <button type="button" class="ajax-link" data-class="external_keys" data-function="edit" data-id="{$item.id}">{t key="external_keys.edit"}</button>
                    <button type="button" class="ajax-link" data-class="external_keys" data-function="delete" data-id="{$item.id}">{t key="external_keys.delete"}</button>
                </td>
            </tr>
        {foreachelse}
            <tr><td colspan="4">{t key="external_keys.empty"}</td></tr>
        {/foreach}
        </tbody>
    </table>
</div>
