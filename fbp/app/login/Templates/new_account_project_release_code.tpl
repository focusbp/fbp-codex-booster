<p>{t key="login.project_release_code_help" lang=$dialog_lang}</p>

<form id="new_account_project_release_code_form" style="height:220px;" onsubmit="return false;">
	<input type="hidden" name="class" value="login">
	<input type="hidden" name="function" value="make_new_account_release_api">
	<div class="form-wrap form-wrap-validation has-error">
		<p>{t key="setting.project_release_code" lang=$dialog_lang}</p>
		<input type="text" name="project_release_code" value="{$project_release_code|escape}">
		<p class="error_message error_project_release_code"></p>
	</div>

	<div style="display:flex;justify-content:space-between;gap:12px;margin-top:18px;">
		<button type="button" class="ajax-link" data-class="login" data-function="make_new_account_project_release_code_back">{t key="common.back" lang=$dialog_lang}</button>
		<button class="ajax-link" data-form="new_account_project_release_code_form">{t key="common.next" lang=$dialog_lang}</button>
	</div>
</form>
