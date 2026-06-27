<form id="email_confirm_input_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$row.id|escape}">
	{fields_form_original name="email" type="text" value=$row.email title="メールアドレス" item_margin_top="10px"}
	<p class="error_message error_email"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" data-class="YOUR_CLASS" data-function="email_confirm_send_code" data-form="email_confirm_input_form">確認コードを送信</button>
	</div>
</form>
