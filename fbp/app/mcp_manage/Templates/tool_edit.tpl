<form id="{$form_id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$data.id|default:0}">
	<input type="hidden" name="server_id" value="{$data.server_id|default:0}">
	<table class="custom_events_table">
		<tbody>
			<tr><td style="width:30%;">{t key="common.status"}</td><td>{html_options name="enabled" options=$enabled_opt selected=$data.enabled|default:1}</td></tr>
			<tr><td>Tool name</td><td><input type="text" name="tool_name" value="{$data.tool_name|default:''|escape}"><p class="error_message error_tool_name"></p></td></tr>
			<tr><td>{t key="common.title"}</td><td><input type="text" name="title" value="{$data.title|default:''|escape}"><p class="error_message error_title"></p></td></tr>
			<tr><td>{t key="common.description"}</td><td><textarea name="description" style="height:90px;">{$data.description|default:''|escape}</textarea></td></tr>
			<tr><td>{t key="mcp_manage.tool_type"}</td><td>{html_options name="tool_type" options=$tool_type_opt selected=$data.tool_type|default:'note_crud'}<p class="error_message error_tool_type"></p></td></tr>
			<tr><td>{t key="mcp_manage.operation"}</td><td>{html_options name="operation" options=$operation_opt selected=$data.operation|default:'list'}<p class="error_message error_operation"></p></td></tr>
			<tr><td>{t key="mcp_manage.target_note"}</td><td>{html_options name="target_note" options=$note_options selected=$data.target_note|default:''}<p class="error_message error_target_note"></p></td></tr>
			<tr><td>{t key="mcp_manage.required_scope"}</td><td><input type="text" name="required_scope" value="{$data.required_scope|default:''|escape}"></td></tr>
			<tr><td>{t key="mcp_manage.requires_confirmation"}</td><td>{html_options name="requires_confirmation" options=$yes_no_opt selected=$data.requires_confirmation|default:0}</td></tr>
			<tr><td>{t key="mcp_manage.destructive"}</td><td>{html_options name="destructive" options=$yes_no_opt selected=$data.destructive|default:0}</td></tr>
			<tr><td>{t key="mcp_manage.max_limit"}</td><td><input type="number" name="max_limit" min="1" max="200" value="{$data.max_limit|default:20}"></td></tr>
		</tbody>
	</table>
	<div id="{$field_area_id}">
		{include file="./_tool_fields_matrix.tpl"}
	</div>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		{if $data.id|default:0 > 0}
			<button class="ajax-link" data-class="{$class}" data-function="edit_tool_exe" data-form="{$form_id}">{t key="common.save"}</button>
		{else}
			<button class="ajax-link" data-class="{$class}" data-function="add_tool_exe" data-form="{$form_id}">{t key="common.save"}</button>
		{/if}
	</div>
</form>

<script>
(function () {
	var formId = "{$form_id|escape:'javascript'}";
	var formKey = "{$form_key|escape:'javascript'}";
	var toolId = "{$data.id|default:0|escape:'javascript'}";
	var $form = $("#" + formId);
	$form.find("select[name='target_note'], select[name='tool_type']").off("change.mcpToolFields").on("change.mcpToolFields", function () {
		var fd = new FormData();
		fd.append("class", "{$class|escape:'javascript'}");
		fd.append("function", "tool_fields_area");
		fd.append("form_key", formKey);
		fd.append("tool_id", toolId);
		fd.append("target_note", $form.find("select[name='target_note']").val());
		fd.append("tool_type", $form.find("select[name='tool_type']").val());
		appcon("app.php", fd);
	});
})();
</script>
