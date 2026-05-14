<div id="event_sessions_original_management_list_area">
	<p class="original_screen_toolbar_note">Event Sessions: {$count}</p>
	<table class="original_screen_table">
		<tbody>
			{foreach from=$rows item=row}
				<tr class="active_indicator">
					<td class="row_style" style="width:90px;">
						<span class="row_title">ID</span>
						<span class="row_value row_value_id"><p>{$row.id|escape}</p></span>
					</td>
					<td class="row_style">
						<span class="row_title">Title</span>
						<span class="row_value"><p>{$row.title|escape}</p></span>
					</td>
					<td class="row_style" style="width:150px;">
						<span class="row_title">Date</span>
						<span class="row_value"><p>{$row._date_label|escape}</p></span>
					</td>
					<td class="row_style" style="width:170px;">
						<span class="row_title">Time</span>
						<span class="row_value"><p>{$row._time_label|escape}</p></span>
					</td>
					<td class="row_style" style="width:130px;">
						<span class="row_title">Registrations</span>
						<span class="row_value"><p>{$row._registration_count|escape} / {$row.capacity|escape}</p></span>
					</td>
					<td class="row_style" style="width:130px;">
						<span class="row_title">Status</span>
						<span class="row_value"><p>{fields_view_direct db="event_sessions" fields="status" data=$row}</p></span>
					</td>
					<td class="row_style original_screen_action_cell event_registration_action_cell">
						<button type="button" class="ajax-link listbutton original_screen_action_delete" invoke-function="delete_dialog" data-id="{$row.id|escape}">
							<span class="material-symbols-outlined">delete</span>
						</button>
						<button type="button" class="ajax-link listbutton original_screen_action_edit" invoke-function="edit_dialog" data-id="{$row.id|escape}">
							<span class="material-symbols-outlined">edit_square</span>
						</button>
						<button type="button" class="ajax-link button_link event_registration_participants_button" invoke-function="participants_side_panel" data-id="{$row.id|escape}">Participants</button>
					</td>
				</tr>
			{/foreach}
			{if count($rows) === 0}
				<tr>
					<td colspan="7" class="original_screen_empty_row">No event sessions found.</td>
				</tr>
			{/if}
		</tbody>
	</table>
</div>
