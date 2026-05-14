<form id="event_sessions_original_management_add_form">
	{fields_form_direct db="event_sessions" fields=$form_fields data=$row item_margin_top="10px"}
	<p class="error_message error_title"></p>
	<p class="error_message error_starts_at"></p>
	<p class="error_message error_duration_minutes"></p>
	<p class="error_message error_capacity"></p>
	<p class="error_message error_status"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="add_save">Save</button>
	</div>
</form>
