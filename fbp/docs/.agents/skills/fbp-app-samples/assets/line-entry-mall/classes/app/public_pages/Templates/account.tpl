<main class="mall-page mall-form-page">
	<h1>会員情報</h1>
	<dl class="mall-detail-list">
		<div><dt>氏名</dt><dd>{$row.name|escape}</dd></div>
	</dl>
	<div class="mall-actions">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="account_edit_dialog">変更する</button>
	</div>
</main>
