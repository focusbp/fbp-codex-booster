<form id="event_sessions_original_management_status_form">
	<input type="hidden" name="id" value="{$row.id|escape}">
	<p style="margin:0 0 8px 0;color:#475569;">{$session_label|escape}</p>
	<p style="margin:0 0 10px 0;font-weight:bold;">{$row.name|escape}</p>
	{fields_form_original name="status" type="dropdown" value=$row.status options_arr=$event_registration_status_options title="Status" item_margin_top="0px"}
	<p class="error_message error_status"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="event_registration_status_save" data-form="event_sessions_original_management_status_form">Save</button>
	</div>
</form>
