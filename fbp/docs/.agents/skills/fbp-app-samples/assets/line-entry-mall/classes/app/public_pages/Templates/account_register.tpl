<main class="mall-page mall-form-page">
	<h1>会員登録</h1>
	<form id="mall_account_form" onsubmit="return false;">
		<input type="hidden" name="userid" value="{$row.userid|escape}">
		{fields_form_original name="name" type="text" value=$row.name title="氏名" item_margin_top="10px"}
		<p class="error_message error_name"></p>
		<div class="mall-actions">
			<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="account_save" data-form="mall_account_form">登録</button>
		</div>
	</form>
</main>
