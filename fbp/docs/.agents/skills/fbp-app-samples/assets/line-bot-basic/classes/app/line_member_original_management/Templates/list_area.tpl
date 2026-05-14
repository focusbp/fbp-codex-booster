<div id="line_member_original_management_list_area">
	<p class="original_screen_toolbar_note">LINE Members: {$count}</p>
	<table class="original_screen_table">
		<tbody>
			{foreach from=$rows item=row}
				<tr class="active_indicator">
					<td class="row_style" style="width:80px;">
						<span class="row_title">ID</span>
						<span class="row_value row_value_id" style="text-align:right;"><p>{$row.id|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">LINE Display Name</span>
						<span class="row_value"><p>{$row.line_name|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">Name</span>
						<span class="row_value"><p>{$row.name|escape}</p></span>
					</td>
					<td class="row_style" style="width:140px;">
						<span class="row_title">Member Type</span>
						<span class="row_value"><p>{fields_view_direct db="line_member" fields="member_type" data=$row}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">LINE User ID</span>
						<span class="row_value"><p>{$row.userid|escape}</p></span>
					</td>
					<td class="row_style original_screen_action_cell line_member_basic_action_cell">
						<button type="button" class="ajax-link listbutton original_screen_action_delete" data-class="line_member_original_management" data-function="delete_dialog" data-id="{$row.id}">
							<span class="material-symbols-outlined">delete</span>
						</button>
						<button type="button" class="ajax-link listbutton original_screen_action_edit" data-class="line_member_original_management" data-function="edit_dialog" data-id="{$row.id}">
							<span class="material-symbols-outlined">edit_square</span>
						</button>
					</td>
				</tr>
			{/foreach}
			{if count($rows) === 0}
				<tr>
					<td colspan="6" class="original_screen_empty_row">No LINE members found.</td>
				</tr>
			{/if}
		</tbody>
	</table>
</div>
