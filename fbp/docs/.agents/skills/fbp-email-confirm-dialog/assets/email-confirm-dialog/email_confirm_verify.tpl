<form id="email_confirm_verify_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$row.id|escape}">
	<p class="original_screen_confirm_message">確認コードを送信しました</p>
	<p>送信先: {$row.pending_email|escape}</p>
	{fields_form_original name="code" type="text" value="" title="確認コード" item_margin_top="10px"}
	<p class="error_message error_code"></p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" data-class="YOUR_CLASS" data-function="email_confirm_verify" data-form="email_confirm_verify_form">登録</button>
	</div>
</form>
