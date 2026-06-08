<form id="user_password_reset_email_form_{$data.id}">
	<input type="hidden" name="id" value="{$data.id}">

	<p>{t key="user.login_id"}</p>
	<p>{$data.login_id}</p>

	<p>{t key="user.password_reset_email_required_help"}</p>

	<p>{t key="user.email"}</p>
	<input type="text" name="email" value="{$data.email|escape}" style="width:100%;">
	<div class="error_email">{$err_email}</div>

	<button class="ajax-link" data-class="user" data-function="password_reset_email_exe" data-form="user_password_reset_email_form_{$data.id}">{t key="common.next"}</button>
</form>
