<form id="customers_csv_upload_form" enctype="multipart/form-data">
	{fields_form_original name="file" type="file" title="CSV File" item_margin_top="10px"}
	<p class="error_message error_file"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="upload_exe">Import</button>
	</div>
</form>
