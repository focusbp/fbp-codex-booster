<p>{t key="login.language_locale_help" lang=$dialog_lang}</p>
<p>{t key="login.timezone_help" lang=$dialog_lang}</p>

<form id="new_account_locale_form" style="height:380px;" onsubmit="return false;">
	<input type="hidden" name="class" value="login">
	<input type="hidden" name="function" value="make_new_account_next">
	<div class="form-wrap form-wrap-validation has-error">
		<p>{t key="setting.framework_language_code" lang=$dialog_lang}</p>
		<select name="framework_language_code">
			{html_options options=$arr_framework_language_code selected=$framework_language_code}
		</select>
		<p class="error_message error_framework_language_code"></p>

		<p style="margin-top:10px;">{t key="setting.locale_code" lang=$dialog_lang}</p>
		<select name="locale_code">
			{html_options options=$arr_locale_code selected=$locale_code}
		</select>
		<p class="error_message error_locale_code"></p>

		<p style="margin-top:10px;">{t key="setting.timezone" lang=$dialog_lang}</p>
		<select name="timezone">
			{html_options options=$timezones selected=$timezone}
		</select>
		<p class="error_message error_timezone"></p>
	</div>

	<div style="display:flex;justify-content:space-between;gap:12px;margin-top:18px;">
		<div></div>
		<button class="ajax-link" data-form="new_account_locale_form">{t key="common.next" lang=$dialog_lang}</button>
	</div>
</form>

<script>
	$(function () {
		var localeOptionMap = {$locale_option_map_json nofilter};
		var $dialog = $("#new_account_locale_form").closest(".multi_dialog");
		var $languageSelect = $dialog.find('select[name="framework_language_code"]');
		var $localeSelect = $dialog.find('select[name="locale_code"]');

		var refreshLocaleOptions = function () {
			var languageCode = $languageSelect.val() || "en";
			var options = localeOptionMap[languageCode] || {};
			var currentValue = $localeSelect.val();
			var html = "";
			var firstValue = "";

			$.each(options, function (value, label) {
				if (firstValue === "") {
					firstValue = value;
				}
				var selected = (value === currentValue) ? ' selected="selected"' : "";
				html += '<option value="' + value + '"' + selected + '>' + label + '</option>';
			});

			$localeSelect.html(html);
			if (!options[currentValue]) {
				$localeSelect.val(firstValue);
			}
		};

		$languageSelect.on("change", refreshLocaleOptions);
		refreshLocaleOptions();
	});
</script>
