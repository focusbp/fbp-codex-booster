{if count($event_registrations) > 0}
	<table class="event_registration_participants_table" style="margin-top:10px;">
		<thead>
			<tr>
				<th>Name</th>
				<th>Email</th>
				<th>Phone</th>
				<th>Status</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$event_registrations item=row}
				<tr>
					<td>{$row.name|escape}</td>
					<td>{$row.email|escape}</td>
					<td>{$row.phone|escape}</td>
					<td>{fields_view_direct db="event_registrations" fields="status" data=$row}</td>
					<td class="event_registration_participants_action">
						<button type="button" class="ajax-link listbutton original_screen_action_delete" invoke-function="participant_delete_dialog" data-id="{$row.id|escape}" title="Delete participant">
							<span class="material-symbols-outlined">delete</span>
						</button>
						<button type="button" class="ajax-link listbutton original_screen_action_edit" invoke-function="event_registration_status_dialog" data-id="{$row.id|escape}" title="Edit status">
							<span class="material-symbols-outlined">edit_square</span>
						</button>
					</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
{else}
	<p class="event_registration_participants_empty">No participants yet.</p>
{/if}
