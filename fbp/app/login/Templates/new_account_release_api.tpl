<p>{t key="login.release_api_help" lang=$dialog_lang}</p>

<form id="new_account_release_api_form" style="height:390px;" onsubmit="return false;">
	<input type="hidden" name="class" value="login">
	<input type="hidden" name="function" value="make_new_account_mail_server">
	<div class="form-wrap form-wrap-validation has-error">
		<p>{t key="setting.api_key" lang=$dialog_lang}</p>
		<input type="text" name="api_key" value="{$api_key|escape}" autocomplete="off" spellcheck="false">
		<p class="error_message error_api_key"></p>
	</div>
	<div class="form-wrap form-wrap-validation has-error" style="margin-top:12px;">
		<p>{t key="setting.api_secret" lang=$dialog_lang}</p>
		<input type="text" name="api_secret" value="{$api_secret|escape}" autocomplete="off" spellcheck="false">
		<p class="error_message error_api_secret"></p>
	</div>
	<div class="form-wrap form-wrap-validation has-error" style="margin-top:12px;">
		<p>{t key="setting.release_api_key" lang=$dialog_lang}</p>
		<input type="text" name="release_api_key" value="{$release_api_key|escape}" autocomplete="off" spellcheck="false">
		<p class="error_message error_release_api_key"></p>
	</div>
	<div class="form-wrap form-wrap-validation has-error" style="margin-top:12px;">
		<p>{t key="setting.release_api_secret" lang=$dialog_lang}</p>
		<input type="text" name="release_api_secret" value="{$release_api_secret|escape}" autocomplete="off" spellcheck="false">
		<p class="error_message error_release_api_secret"></p>
	</div>

	<div style="display:flex;justify-content:space-between;gap:12px;margin-top:18px;">
		<button type="button" class="ajax-link" data-class="login" data-function="make_new_account_release_api_back">{t key="common.back" lang=$dialog_lang}</button>
		<div style="display:flex;gap:12px;">
			<button type="button" class="ajax-link" data-class="login" data-function="make_new_account_skip_release_api">{t key="common.skip" lang=$dialog_lang}</button>
			<button class="ajax-link" data-form="new_account_release_api_form">{t key="common.next" lang=$dialog_lang}</button>
		</div>
	</div>
</form>
