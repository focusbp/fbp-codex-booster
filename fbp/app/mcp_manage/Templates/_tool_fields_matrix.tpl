<div class="mcp-tool-fields-block" style="margin-top:16px;">
	<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:8px;">
		<div>
			<div style="font-weight:600;font-size:13px;">{t key="mcp_manage.fields"}</div>
			<p style="margin:4px 0 0;color:#4b5563;font-size:12px;line-height:1.6;">{t key="mcp_manage.fields_description"}</p>
		</div>
	</div>
	{if $field_rows|@count > 0}
		<table class="moredata" style="width:100%;">
			<thead>
				<tr class="table-head">
					<th style="width:20%;">{t key="mcp_manage.field_name"}</th>
					<th>{t key="common.title"}</th>
					<th style="width:10%;">Input</th>
					<th style="width:10%;">Required</th>
					<th style="width:10%;">Output</th>
					<th style="width:10%;">Search</th>
				</tr>
			</thead>
			<tbody>
				{foreach $field_rows as $field}
					<tr>
						<td>{$field.parameter_name|default:''|escape}</td>
						<td>{$field.parameter_title|default:''|escape}</td>
						<td style="text-align:center;"><input type="checkbox" name="input_fields[]" value="{$field.parameter_name|default:''|escape}" {if $field.mcp_input|default:0 == 1}checked{/if}></td>
						<td style="text-align:center;"><input type="checkbox" name="required_fields[]" value="{$field.parameter_name|default:''|escape}" {if $field.mcp_required|default:0 == 1}checked{/if}></td>
						<td style="text-align:center;"><input type="checkbox" name="output_fields[]" value="{$field.parameter_name|default:''|escape}" {if $field.mcp_output|default:0 == 1}checked{/if}></td>
						<td style="text-align:center;"><input type="checkbox" name="search_fields[]" value="{$field.parameter_name|default:''|escape}" {if $field.mcp_search|default:0 == 1}checked{/if}></td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	{elseif $selected_target_note|default:'' == ''}
		<div style="border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;padding:10px;">{t key="mcp_manage.select_note_for_fields"}</div>
	{else}
		<div style="border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;padding:10px;">{t key="mcp_manage.no_fields"}</div>
	{/if}
	<p class="error_message error_fields"></p>
</div>
