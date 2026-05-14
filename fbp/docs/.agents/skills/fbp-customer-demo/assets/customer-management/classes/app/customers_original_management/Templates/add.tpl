<form id="customers_original_management_add_form">
	{fields_form_direct db="customers" fields=$form_fields data=$row item_margin_top="10px"}
	<p class="error_message error_company_name"></p>
	<p class="error_message error_email"></p>
	<p class="error_message error_status"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="add_save">Save</button>
	</div>
</form>
