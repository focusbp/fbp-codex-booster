<form id="mcp_tool_type_select_form" onsubmit="return false;">
	<table class="custom_events_table">
		<tbody>
			<tr>
				<td style="width:30%;">{t key="mcp_manage.tool_type"}</td>
				<td>
					{html_options name="tool_type" options=$tool_type_opt selected='note_crud'}
					<p class="error_message error_tool_type"></p>
				</td>
			</tr>
		</tbody>
	</table>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		<button class="ajax-link" data-class="{$class}" data-function="add_tool_form" data-form="mcp_tool_type_select_form">{t key="common.next"}</button>
	</div>
</form>
