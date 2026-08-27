<form id="{$function_form_id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$data.id|default:0|escape}">
	<table class="custom_events_table"><tbody>
		<tr><td style="width:30%;">{t key="common.status"}</td><td>{html_options name="enabled" options=$enabled_opt selected=$data.enabled}</td></tr>
		<tr><td>Function</td><td><input type="text" name="function_name" value="{$data.function_name|escape}" placeholder="task_list"><p class="error_message error_function_name"></p><div style="font-size:11px;color:#6b7280;">PHP class: <code>{if $data.class_name}{$data.class_name|escape}{else}mcp_&lt;function_name&gt;{/if}</code></div></td></tr>
		<tr><td>{t key="common.title"}</td><td><input type="text" name="title" value="{$data.title|escape}"><p class="error_message error_title"></p></td></tr>
		<tr><td>{t key="common.description"}</td><td><textarea name="description" style="height:90px;">{$data.description|escape}</textarea></td></tr>
		<tr><td>{t key="mcp_manage.required_scope"}</td><td><input type="text" name="required_scope" value="{$data.required_scope|escape}" placeholder="mcp.read"></td></tr>
		<tr><td>{t key="mcp_manage.read_only"}</td><td>{html_options name="read_only" options=$yes_no_opt selected=$data.read_only}</td></tr>
		<tr><td>{t key="mcp_manage.requires_confirmation"}</td><td>{html_options name="requires_confirmation" options=$yes_no_opt selected=$data.requires_confirmation}</td></tr>
		<tr><td>{t key="mcp_manage.destructive"}</td><td>{html_options name="destructive" options=$yes_no_opt selected=$data.destructive}</td></tr>
		<tr><td>{t key="mcp_manage.handler_config"}</td><td><textarea name="handler_config" style="height:90px;" placeholder="{}">{$data.handler_config|escape}</textarea><p class="error_message error_handler_config"></p></td></tr>
	</tbody></table>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;"><button class="ajax-link" data-class="{$class}" data-function="save_function" data-form="{$function_form_id}">{t key="common.save"}</button></div>
</form>
