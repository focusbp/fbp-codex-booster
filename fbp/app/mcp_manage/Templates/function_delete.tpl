<form id="mcp_function_delete_form_{$data.id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$data.id|escape}">
	<p><strong>{$data.function_name|escape}</strong></p>
	<p>{t key="mcp_manage.delete_function_confirm"}</p>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;"><button class="ajax-link" data-class="{$class}" data-function="delete_function_exe" data-form="mcp_function_delete_form_{$data.id}">{t key="common.delete"}</button></div>
</form>
