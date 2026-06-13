<div>
	<p>{t key="mcp_manage.delete_tool_confirm"}</p>
	<table class="custom_events_table">
		<tbody>
			<tr><td style="width:30%;">Tool</td><td>{$data.tool_name|escape}</td></tr>
			<tr><td>{t key="common.title"}</td><td>{$data.title|escape}</td></tr>
		</tbody>
	</table>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		<button class="ajax-link" data-class="{$class}" data-function="delete_tool_exe" data-id="{$data.id}">{t key="common.delete"}</button>
	</div>
</div>
