<form id="event_sessions_original_management_participant_add_form" onsubmit="return false;">
	<input type="hidden" name="session_id" value="{$row.session_id|escape}">
	<p style="margin:0 0 8px 0;color:#475569;">{$session_label|escape}</p>
	{fields_form_direct db="event_registrations" fields=$form_fields data=$row item_margin_top="10px"}
	<p class="error_message error_session_id"></p>
	<p class="error_message error_name"></p>
	<p class="error_message error_email"></p>
	<p class="error_message error_phone"></p>
	<p class="error_message error_message"></p>
	<p class="error_message error_status"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="participant_add_save" data-form="event_sessions_original_management_participant_add_form">Save</button>
	</div>
</form>
