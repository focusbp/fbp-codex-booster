<div class="mcp-server-tools">
	<form id="mcp_server_context_form_{$server.id}" onsubmit="return false;">
		<input type="hidden" name="server_id" value="{$server.id|escape}">
	</form>

	<table class="custom_events_table" style="margin-bottom:12px;">
		<tbody>
			<tr>
				<td style="width:180px;">{t key="mcp_manage.server_key"}</td>
				<td>{$server.server_key|escape}</td>
			</tr>
			<tr>
				<td>{t key="common.status"}</td>
				<td>{if $server.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}</td>
			</tr>
			<tr>
				<td>{t key="common.title"}</td>
				<td>{$server.title|escape}</td>
			</tr>
			<tr>
				<td>{t key="mcp_manage.auth_mode"}</td>
				<td>{$server.auth_mode|escape}</td>
			</tr>
			<tr>
				<td>{t key="mcp_manage.subject_type"}</td>
				<td>
					{$server.subject_type|default:'fbp_user'|escape}
					{if $server.subject_provider_class}
						<br><span style="font-size:11px;color:#6b7280;">{$server.subject_provider_class|escape}</span>
					{/if}
				</td>
			</tr>
			<tr>
				<td>{t key="mcp_manage.endpoint_url"}</td>
				<td style="font-size:11px;word-break:break-all;">{$mcp_endpoint_url|escape}</td>
			</tr>
			<tr>
				<td>{t key="mcp_manage.oauth_urls"}</td>
				<td style="font-size:11px;line-height:1.7;word-break:break-all;">
					authorization: {$mcp_authorize_url|escape}<br>
					token: {$mcp_token_url|escape}<br>
					resource metadata: {$mcp_resource_metadata_url|escape}
				</td>
			</tr>
		</tbody>
	</table>

	<div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
		<button class="ajax-link" data-class="{$class}" data-function="add_tool" data-form="mcp_server_context_form_{$server.id}">{t key="mcp_manage.add_tool"}</button>
	</div>

	<table class="moredata" style="width:100%;margin-top:10px;">
		<thead>
			<tr class="table-head">
				<th style="width:4%;"></th>
				<th style="width:16%;">Tool</th>
				<th style="width:10%;">{t key="mcp_manage.tool_type"}</th>
				<th style="width:10%;">{t key="mcp_manage.operation"}</th>
				<th style="width:16%;">{t key="mcp_manage.target_note"}</th>
				<th style="width:14%;">{t key="mcp_manage.fields"}</th>
				<th style="width:12%;">{t key="common.status"}</th>
				<th>{t key="common.description"}</th>
				<th style="width:10%;"></th>
			</tr>
		</thead>
		<tbody class="mcp-tool-sort" data-server_id="{$server.id}">
			{foreach $items as $item}
				<tr id="{$item.id}" style="background:#FFF;">
					<td><span class="ui-icon ui-icon-arrowthick-2-n-s mcp-tool-handle"></span></td>
					<td>
						<div style="font-weight:600;">{$item.tool_name|escape}</div>
						<div style="font-size:11px;color:#6b7280;">{$item.title|escape}</div>
					</td>
					<td>{if $item.tool_type == 'app_action'}{t key="mcp_manage.app_action"}{else}{t key="mcp_manage.note_crud"}{/if}</td>
					<td>{$item.operation|escape}</td>
					<td>{if $item.tool_type == 'app_action'}{$item.action_class|escape}{else}{$item.target_note|escape}{/if}</td>
					<td>
						<div style="font-size:11px;color:#6b7280;">{$item.field_summary|escape}</div>
					</td>
					<td>
						{if $item.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}
						<div style="font-size:11px;color:{if $item.ready_status == 'ready'}#047857{else}#b45309{/if};">{$item.ready_status|escape}</div>
					</td>
					<td style="font-size:12px;line-height:1.5;">{$item.description|escape|nl2br nofilter}</td>
					<td>
						<button class="ajax-link listbutton" data-class="{$class}" data-function="delete_tool" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>
						<button class="ajax-link listbutton" data-class="{$class}" data-function="edit_tool" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button>
					</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
</div>

<script>
$(function () {
	$(".mcp-tool-sort").sortable({
		handle: ".mcp-tool-handle",
		axis: "y",
		update: function () {
			var log = $(this).sortable("toArray");
			var fd = new FormData();
			fd.append('class', '{$class}');
			fd.append('function', 'sort');
			fd.append('server_id', $(this).data('server_id'));
			fd.append('log', log);
			appcon('app.php', fd);
		}
	});
});
</script>
