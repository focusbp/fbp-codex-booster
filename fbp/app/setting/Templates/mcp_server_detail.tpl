<form id="setting_mcp_server_status_form" onsubmit="return false;">
<table class="setting_detail_table setting_readonly_table">
	<tr>
		<th>{t key="common.title"}</th>
		<td><div class="setting_readonly_value">{$mcp_server.title|escape}</div></td>
	</tr>
	<tr>
		<th>{t key="common.status"}</th>
		<td>
			<select name="enabled">
				<option value="1"{if $mcp_server.enabled == 1} selected{/if}>{t key="common.enabled"}</option>
				<option value="0"{if $mcp_server.enabled == 0} selected{/if}>{t key="common.disabled"}</option>
			</select>
			<p class="error_message error_enabled"></p>
		</td>
	</tr>
	<tr>
		<th>{t key="mcp_manage.auth_mode"}</th>
		<td><div class="setting_readonly_value">{$mcp_server.auth_mode|escape}</div></td>
	</tr>
	<tr>
		<th>{t key="mcp_manage.subject_type"}</th>
		<td>
			<div class="setting_readonly_value">{$mcp_server.subject_type|default:'fbp_user'|escape}</div>
			{if $mcp_server.subject_provider_class|default:'' != ''}
				<div style="font-size:11px;color:#6b7280;">{$mcp_server.subject_provider_class|escape}</div>
			{/if}
		</td>
	</tr>
	<tr>
		<th>{t key="setting.mcp_endpoint_url"}</th>
		<td><div class="setting_readonly_value setting_url_value">{$mcp_server.endpoint_url|escape}</div></td>
	</tr>
</table>
<div class="button_row" style="display:flex;justify-content:flex-end;margin-top:12px;">
	<button type="button" class="ajax-link" data-class="setting" data-function="mcp_server_status_save" data-form="setting_mcp_server_status_form">{t key="setting.mcp_status_save"}</button>
</div>
</form>
