<div class="mcp-manage">
	<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:10px;">
		<div>
			<h3 style="margin:0 0 6px;font-size:16px;">MCP Server</h3>
			<p style="margin:0;color:#4b5563;font-size:12px;line-height:1.6;">{t key="mcp_manage.description"}</p>
		</div>
		<div style="display:flex;gap:8px;align-items:center;">
			<button class="ajax-link" data-class="{$class}" data-function="add_server">{t key="mcp_manage.add_server"}</button>
		</div>
	</div>

	<table class="moredata" style="width:100%;margin-top:10px;">
		<thead>
			<tr class="table-head">
				<th style="width:18%;">{t key="mcp_manage.server_key"}</th>
				<th style="width:18%;">{t key="common.title"}</th>
				<th style="width:10%;">{t key="common.status"}</th>
				<th style="width:12%;">{t key="mcp_manage.auth_mode"}</th>
				<th style="width:18%;">{t key="mcp_manage.subject_type"}</th>
				<th style="width:8%;">Tool</th>
				<th style="width:16%;"></th>
			</tr>
		</thead>
		<tbody>
			{foreach $servers as $server}
				<tr>
					<td>
						<div style="font-weight:600;">{$server.server_key|escape}</div>
						{if $server.description}
							<div style="font-size:11px;color:#6b7280;line-height:1.4;">{$server.description|escape|nl2br nofilter}</div>
						{/if}
					</td>
					<td>{$server.title|escape}</td>
					<td>{if $server.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}</td>
					<td>{$server.auth_mode|escape}</td>
					<td>
						{$server.subject_type|default:'fbp_user'|escape}
						{if $server.subject_provider_class}
							<br><span style="font-size:11px;color:#6b7280;">{$server.subject_provider_class|escape}</span>
						{/if}
					</td>
					<td>{$server.tool_count|default:0}</td>
					<td>
						<button class="ajax-link listbutton" data-class="{$class}" data-function="delete_server" data-id="{$server.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>
						<button class="ajax-link listbutton" data-class="{$class}" data-function="edit_server" data-id="{$server.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-pencil"></span></button>
						<button class="ajax-link" data-class="{$class}" data-function="tools" data-server_id="{$server.id}">{t key="mcp_manage.tools_and_urls"}</button>
					</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
</div>
