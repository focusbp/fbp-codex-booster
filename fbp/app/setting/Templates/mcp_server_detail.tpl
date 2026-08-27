<table class="setting_detail_table setting_readonly_table">
	<tr>
		<th>{t key="common.title"}</th>
		<td><div class="setting_readonly_value">{$mcp_server.title|escape}</div></td>
	</tr>
	<tr>
		<th>{t key="common.status"}</th>
		<td><div class="setting_readonly_value">{$mcp_server.status|escape}</div></td>
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
	<tr>
		<th>{t key="setting.mcp_oauth_urls"}</th>
		<td>
			<div class="setting_readonly_value setting_url_value">
				authorization: {$mcp_server.authorization_url|escape}<br>
				token: {$mcp_server.token_url|escape}<br>
				resource metadata: {$mcp_server.resource_metadata_url|escape}
			</div>
		</td>
	</tr>
</table>
