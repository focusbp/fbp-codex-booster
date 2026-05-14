<div class="original_screen_delete_confirm">
	<p>Delete this appointment slot?</p>
	<p><strong>{$row.title|escape}</strong></p>
	<form id="schedule_appointment_slots_delete_form">
		<input type="hidden" name="id" value="{$row.id|escape}">
		<div class="original_screen_dialog_actions">
			<button type="button" class="ajax-link button_link danger" data-class="schedule_appointment_slots_original_management" data-function="delete_save" data-form="schedule_appointment_slots_delete_form">Delete</button>
		</div>
	</form>
</div>
