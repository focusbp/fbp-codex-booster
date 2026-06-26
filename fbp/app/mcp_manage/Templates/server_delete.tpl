<div>
	<p>{t key="mcp_manage.delete_server_confirm"}</p>
	<table class="custom_events_table">
		<tbody>
			<tr><td style="width:30%;">{t key="mcp_manage.server_key"}</td><td>{$server.server_key|escape}</td></tr>
			<tr><td>{t key="common.title"}</td><td>{$server.title|escape}</td></tr>
			<tr><td>Tool</td><td>{$server.tool_count|default:0}</td></tr>
			<tr><td>OAuth</td><td>{$server.token_count|default:0}</td></tr>
			<tr><td>{t key="mcp_manage.logs"}</td><td>{$server.log_count|default:0}</td></tr>
		</tbody>
	</table>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		<button class="ajax-link" data-class="{$class}" data-function="delete_server_exe" data-id="{$server.id}">{t key="common.delete"}</button>
	</div>
</div>
