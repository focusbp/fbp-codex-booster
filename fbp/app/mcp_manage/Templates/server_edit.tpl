<form id="mcp_server_edit_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$server.id|escape}">
	<table class="custom_events_table">
		<tbody>
			<tr><td style="width:30%;">{t key="common.status"}</td><td>{html_options name="enabled" options=$enabled_opt selected=$server.enabled}</td></tr>
			<tr><td>{t key="mcp_manage.server_key"}</td><td><input type="text" name="server_key" value="{$server.server_key|escape}"><p class="error_message error_server_key"></p></td></tr>
			<tr><td>{t key="common.title"}</td><td><input type="text" name="title" value="{$server.title|escape}"><p class="error_message error_title"></p></td></tr>
			<tr><td>{t key="common.description"}</td><td><textarea name="description" style="height:80px;">{$server.description|escape}</textarea></td></tr>
			<tr><td>{t key="mcp_manage.auth_mode"}</td><td>{html_options name="auth_mode" options=$auth_mode_opt selected=$server.auth_mode}<p class="error_message error_auth_mode"></p></td></tr>
			<tr><td>{t key="mcp_manage.default_scope"}</td><td><input type="text" name="default_scope" value="{$server.default_scope|escape}"></td></tr>
		</tbody>
	</table>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		<button class="ajax-link" data-class="{$class}" data-function="edit_server_exe" data-form="mcp_server_edit_form">{t key="common.save"}</button>
	</div>
</form>
