<form id="line_member_original_management_add_form">
	{fields_form_direct db="line_member" fields=$form_fields data=$row item_margin_top="10px"}
	<p class="error_message error_name"></p>
	<p class="error_message error_member_type"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="add_save">Save</button>
	</div>
</form>
