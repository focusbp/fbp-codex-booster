<div class="mcp-manage">
	<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:12px;">
		<div>
			<h3 style="margin:0 0 6px;font-size:16px;">MCP Server</h3>
			<p style="margin:0;color:#4b5563;font-size:12px;line-height:1.6;">{t key="mcp_manage.description"}</p>
		</div>
		<div style="display:flex;gap:8px;">
			<button class="ajax-link" data-class="{$class}" data-function="logs">{t key="mcp_manage.logs"}</button>
			<button class="ajax-link" data-class="{$class}" data-function="oauth_tokens">OAuth Tokens</button>
			<button class="ajax-link" data-class="{$class}" data-function="edit_server" data-id="{$server.id}">{t key="common.edit"}</button>
		</div>
	</div>

	<table class="custom_events_table" style="margin-bottom:18px;">
		<tbody>
			<tr><td style="width:180px;">{t key="common.status"}</td><td>{if $server.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}</td></tr>
			<tr><td>{t key="common.title"}</td><td>{$server.title|escape}</td></tr>
			<tr><td>{t key="mcp_manage.auth_mode"}</td><td>{$server.auth_mode|escape}</td></tr>
			<tr><td>{t key="mcp_manage.endpoint_url"}</td><td style="font-size:11px;word-break:break-all;">{$mcp_endpoint_url|escape}</td></tr>
			<tr><td>{t key="mcp_manage.oauth_urls"}</td><td style="font-size:11px;line-height:1.7;word-break:break-all;">authorization: {$mcp_authorize_url|escape}<br>token: {$mcp_token_url|escape}<br>resource metadata: {$mcp_resource_metadata_url|escape}</td></tr>
		</tbody>
	</table>

	<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
		<div>
			<h3 style="margin:0 0 4px;font-size:15px;">{t key="mcp_manage.functions"}</h3>
			<p style="margin:0;color:#6b7280;font-size:11px;">{t key="mcp_manage.functions_help"}</p>
		</div>
		<button class="ajax-link" data-class="{$class}" data-function="add_function">{t key="mcp_manage.add_function"}</button>
	</div>

	<table class="moredata" style="width:100%;">
		<thead><tr class="table-head">
			<th style="width:4%;"></th><th style="width:20%;">Function</th><th style="width:20%;">PHP Class</th><th style="width:12%;">Scope</th><th style="width:12%;">{t key="common.status"}</th><th>{t key="common.description"}</th><th style="width:10%;"></th>
		</tr></thead>
		<tbody class="mcp-function-sort">
		{foreach $items as $item}
			<tr id="{$item.id}" style="background:#fff;">
				<td><span class="ui-icon ui-icon-arrowthick-2-n-s mcp-function-handle"></span></td>
				<td><div style="font-weight:600;">{$item.function_name|escape}</div><div style="font-size:11px;color:#6b7280;">{$item.title|escape}</div></td>
				<td><code>{$item.class_name|escape}</code></td>
				<td>{$item.required_scope|escape}</td>
				<td>{if $item.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}<div style="font-size:11px;color:{if $item.ready_status == 'ready'}#047857{else}#b45309{/if};">{$item.ready_status|escape}</div></td>
				<td style="font-size:12px;line-height:1.5;">{$item.description|escape|nl2br nofilter}</td>
				<td><button class="ajax-link listbutton" data-class="{$class}" data-function="delete_function" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button><button class="ajax-link listbutton" data-class="{$class}" data-function="edit_function" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button></td>
			</tr>
		{/foreach}
		</tbody>
	</table>
</div>
<script>
{literal}
$(function () {
	$(".mcp-function-sort").sortable({handle: ".mcp-function-handle", axis: "y", update: function () {
		var fd = new FormData();
		fd.append('class', 'mcp_manage'); fd.append('function', 'sort_functions'); fd.append('log', $(this).sortable('toArray'));
		appcon('app.php', fd);
	}});
});
{/literal}
</script>
