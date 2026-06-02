<p>{t key="login.mail_server_help" lang=$dialog_lang}</p>

<form id="new_account_mail_server_form" onsubmit="return false;">
	<input type="hidden" name="class" value="login">
	<input type="hidden" name="function" value="make_new_account_confirm">
	<div class="form-wrap form-wrap-validation has-error">
		<p>{t key="setting.mail_address_from" lang=$dialog_lang}</p>
		<input type="text" name="smtp_from" value="{$smtp_from|escape}">
		<p class="error_message error_smtp_from"></p>

		<p style="margin-top:10px;">{t key="setting.mail_server" lang=$dialog_lang}</p>
		<input type="text" name="smtp_server" value="{$smtp_server|escape}">
		<p class="error_message error_smtp_server"></p>

		<p style="margin-top:10px;">{t key="setting.mail_port" lang=$dialog_lang}</p>
		<input type="text" name="smtp_port" value="{$smtp_port|escape}">
		<p class="error_message error_smtp_port"></p>

		<p style="margin-top:10px;">{t key="setting.mail_user" lang=$dialog_lang}</p>
		<input type="text" name="smtp_user" value="{$smtp_user|escape}">
		<p class="error_message error_smtp_user"></p>

		<p style="margin-top:10px;">{t key="setting.mail_password" lang=$dialog_lang}</p>
		<input type="password" name="smtp_password" value="" placeholder="{$smtp_password_placeholder|escape}">
		<p class="error_message error_smtp_password"></p>

		<p style="margin-top:10px;">{t key="setting.smtp_secure" lang=$dialog_lang}</p>
		<select name="smtp_secure">
			{html_options options=$arr_smtp_secure selected=$smtp_secure}
		</select>
		<p class="error_message error_smtp_secure"></p>
	</div>

	<div style="display:flex;justify-content:space-between;gap:12px;margin-top:18px;">
		<button type="button" class="ajax-link" data-class="login" data-function="make_new_account_mail_server_back">{t key="common.back" lang=$dialog_lang}</button>
		<div style="display:flex;gap:12px;">
			<button type="button" class="ajax-link" data-class="login" data-function="make_new_account_confirm_skip_mail_server">{t key="common.skip" lang=$dialog_lang}</button>
			<button class="ajax-link" data-form="new_account_mail_server_form">{t key="common.next" lang=$dialog_lang}</button>
		</div>
	</div>
</form>
