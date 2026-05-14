<form id="line_member_original_management_edit_form">
	<input type="hidden" name="id" value="{$row.id}">
	{fields_form_direct db="line_member" fields=$form_fields data=$row item_margin_top="10px"}
	<p class="error_message error_name"></p>
	<p class="error_message error_member_type"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="edit_save">Save</button>
	</div>
</form>
