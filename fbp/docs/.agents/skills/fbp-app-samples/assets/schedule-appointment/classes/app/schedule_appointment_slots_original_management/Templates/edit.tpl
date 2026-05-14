<form id="schedule_appointment_slots_edit_form">
	<input type="hidden" name="id" value="{$row.id|escape}">
	{fields_form_direct db="schedule_appointment_slots" fields=$form_fields data=$row item_margin_top="10px"}

	<p class="error_message error_user_id"></p>
	<p class="error_message error_title"></p>
	<p class="error_message error_starts_at"></p>
	<p class="error_message error_duration_minutes"></p>
	<p class="error_message error_status"></p>
	<p class="error_message error_customer_name"></p>
	<p class="error_message error_customer_email"></p>

	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" data-class="schedule_appointment_slots_original_management" data-function="edit_save" data-form="schedule_appointment_slots_edit_form">Save</button>
	</div>
</form>
