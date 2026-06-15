<form id="{$form_id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$data.id|default:0}">
	<input type="hidden" name="server_id" value="{$data.server_id|default:0}">
	<input type="hidden" name="tool_type" value="{$data.tool_type|default:'note_crud'|escape}">

	<p class="error_message error_tool_type"></p>

	<table class="custom_events_table">
		<tbody>
			<tr>
				<td style="width:30%;">{t key="mcp_manage.tool_type"}</td>
				<td>
					{if $data.tool_type|default:'note_crud' == 'app_action'}{t key="mcp_manage.app_action"}{else}{t key="mcp_manage.note_crud"}{/if}
				</td>
			</tr>
			<tr><td>{t key="common.status"}</td><td>{html_options name="enabled" options=$enabled_opt selected=$data.enabled|default:1}</td></tr>

			{if $data.tool_type|default:'note_crud' == 'app_action'}
				<tr>
					<td>Tool name</td>
					<td>
						<input type="text" name="tool_name" value="{$data.tool_name|default:''|escape}">
						<p class="error_message error_tool_name"></p>
					</td>
				</tr>
				<tr>
					<td>{t key="common.title"}</td>
					<td>
						<input type="text" name="title" value="{$data.title|default:''|escape}">
						<p class="error_message error_title"></p>
					</td>
				</tr>
				<tr>
					<td>{t key="mcp_manage.action_class"}</td>
					<td>
						<input type="text" name="action_class" value="{$data.action_class|default:''|escape}">
						<p class="error_message error_action_class"></p>
					</td>
				</tr>
				<tr>
					<td>{t key="common.description"}</td>
					<td>
						<textarea name="description" style="height:90px;">{$data.description|default:''|escape}</textarea>
						<p class="error_message error_description"></p>
					</td>
				</tr>
				<tr>
					<td>{t key="mcp_manage.required_scope"}</td>
					<td>
						<input type="text" name="required_scope" value="{$data.required_scope|default:'mcp.read'|escape}">
						<p class="error_message error_required_scope"></p>
					</td>
				</tr>
				<tr><td>{t key="mcp_manage.read_only"}</td><td>{html_options name="read_only" options=$yes_no_opt selected=$data.read_only|default:1}</td></tr>
				<tr><td>{t key="mcp_manage.requires_confirmation"}</td><td>{html_options name="requires_confirmation" options=$yes_no_opt selected=$data.requires_confirmation|default:0}</td></tr>
				<tr><td>{t key="mcp_manage.destructive"}</td><td>{html_options name="destructive" options=$yes_no_opt selected=$data.destructive|default:0}</td></tr>
			{else}
				<input type="hidden" name="tool_name" value="{$data.tool_name|default:''|escape}">
				<input type="hidden" name="title" value="{$data.title|default:''|escape}">
				<input type="hidden" name="action_class" value="">
				{if $data.id|default:0 == 0}
					<input type="hidden" name="description" value="{$data.description|default:''|escape}">
					<input type="hidden" name="required_scope" value="{$data.required_scope|default:'mcp.read'|escape}">
					<p class="error_message error_description"></p>
					<p class="error_message error_required_scope"></p>
				{/if}
				<p class="error_message error_tool_name"></p>
				<p class="error_message error_title"></p>
				<tr><td>{t key="mcp_manage.target_note"}</td><td>{html_options name="target_note" options=$note_options selected=$data.target_note|default:''}<p class="error_message error_target_note"></p></td></tr>
				<tr><td>{t key="mcp_manage.operation"}</td><td>{html_options name="operation" options=$operation_opt selected=$data.operation|default:'list'}<p class="error_message error_operation"></p></td></tr>
				{if $data.id|default:0 > 0}
					<tr><td>{t key="common.description"}</td><td><textarea name="description" style="height:90px;">{$data.description|default:''|escape}</textarea><p class="error_message error_description"></p></td></tr>
					<tr><td>{t key="mcp_manage.required_scope"}</td><td><input type="text" name="required_scope" value="{$data.required_scope|default:''|escape}"><p class="error_message error_required_scope"></p></td></tr>
				{/if}
				<tr><td>{t key="mcp_manage.requires_confirmation"}</td><td>{html_options name="requires_confirmation" options=$yes_no_opt selected=$data.requires_confirmation|default:0}</td></tr>
				<tr><td>{t key="mcp_manage.destructive"}</td><td>{html_options name="destructive" options=$yes_no_opt selected=$data.destructive|default:0}</td></tr>
				<tr><td>{t key="mcp_manage.max_limit"}</td><td><input type="number" name="max_limit" min="1" max="200" value="{$data.max_limit|default:20}"></td></tr>
			{/if}
		</tbody>
	</table>

	{if $data.tool_type|default:'note_crud' == 'note_crud'}
		<div id="{$field_area_id}">
			{include file="./_tool_fields_matrix.tpl"}
		</div>
	{/if}

	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		{if $data.id|default:0 > 0}
			<button class="ajax-link" data-class="{$class}" data-function="edit_tool_exe" data-form="{$form_id}">{t key="common.save"}</button>
		{else}
			<button class="ajax-link" data-class="{$class}" data-function="add_tool_exe" data-form="{$form_id}">{t key="common.save"}</button>
		{/if}
	</div>
</form>

{if $data.tool_type|default:'note_crud' == 'note_crud'}
<script>
(function () {
	var formId = "{$form_id|escape:'javascript'}";
	var formKey = "{$form_key|escape:'javascript'}";
	var toolId = "{$data.id|default:0|escape:'javascript'}";
	var $form = $("#" + formId);
	$form.find("select[name='target_note']").off("change.mcpToolFields").on("change.mcpToolFields", function () {
		var fd = new FormData();
		fd.append("class", "{$class|escape:'javascript'}");
		fd.append("function", "tool_fields_area");
		fd.append("form_key", formKey);
		fd.append("tool_id", toolId);
		fd.append("target_note", $form.find("select[name='target_note']").val());
		fd.append("tool_type", "note_crud");
		appcon("app.php", fd);
	});
})();
</script>
{/if}
