<form id="mall_account_edit_form" class="stack_form">
	{fields_form_original name="name" type="text" value=$row.name title="氏名" item_margin_top="10px"}
	<p class="error_message error_name"></p>
	<div class="button_row button_row_end">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="account_update" data-form="mall_account_edit_form">更新</button>
	</div>
</form>
