<div class="setting_page">
	<div class="setting_page_bar">
		<div class="setting_page_bar_inner">
			<button class="ajax-link lang" data-class="setting" data-function="update" data-form="setting_form">{t key="setting.submit"}</button>
		</div>
	</div>

	<form id="setting_form" class="setting_form_layout">
		<div id="setting_tabs" style="overflow: hidden; padding-bottom: 20px; margin-top: 10px;">
			<ul>
				<li><a href="#setting-tab-general">{t key="setting.tab.system"}</a></li>
				<li><a href="#setting-tab-branding">{t key="setting.tab.web"}</a></li>
				<li><a href="#setting-tab-mail">{t key="setting.tab.mail_server"}</a></li>
				<li><a href="#setting-tab-openai">{t key="setting.tab.openai"}</a></li>
				<li><a href="#setting-tab-integrations">{t key="setting.tab.line_bot"}</a></li>
				<li><a href="#setting-tab-payment">{t key="setting.tab.square"}</a></li>
				<li><a href="#setting-tab-google">{t key="setting.tab.google"}</a></li>
				<li><a href="#setting-tab-api-auth">{t key="setting.tab.api_hmac"}</a></li>
				<li><a href="#setting-tab-vimeo">{t key="setting.tab.vimeo"}</a></li>
			</ul>

			<div id="setting-tab-general" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th>{t key="setting.project"}</th>
							<td>{t key="setting.project_release_code"}</td>
							<td><input type="text" name="project_release_code" value="{$setting.project_release_code}"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.language"}</th>
							<td>{t key="setting.framework_language_code"}</td>
							<td>{html_options name="framework_language_code" options=$arr_framework_language_code selected=$setting.framework_language_code}</td>
						</tr>
						<tr>
							<td>{t key="setting.locale_code"}</td>
							<td>
								{html_options name="locale_code" options=$arr_locale_code selected=$setting.locale_code}
								<div style="margin-top:6px;">
									<a href="#" class="setting_apply_locale_preset_link" style="color:#2563eb;">{t key="setting.apply_locale_preset_link"}</a>
								</div>
							</td>
						</tr>
						<tr>
							<th>{t key="setting.timezone_section"}</th>
							<td>{t key="setting.timezone"}</td>
							<td>{html_options name="timezone" options=$timezones selected=$setting.timezone}</td>
						</tr>
						<tr>
							<th rowspan="4">{t key="setting.date_display"}</th>
							<td>{t key="setting.date_format"}</td>
							<td><input type="text" name="date_format" value="{$setting.date_format}"></td>
						</tr>
						<tr>
							<td>{t key="setting.datetime_format"}</td>
							<td><input type="text" name="datetime_format" value="{$setting.datetime_format}"></td>
						</tr>
						<tr>
							<td>{t key="setting.year_month_format"}</td>
							<td><input type="text" name="year_month_format" value="{$setting.year_month_format}"></td>
						</tr>
						<tr>
							<td>{t key="setting.month_day_format"}</td>
							<td><input type="text" name="month_day_format" value="{$setting.month_day_format}"></td>
						</tr>
						<tr>
							<th rowspan="3">{t key="setting.number_display"}</th>
							<td>{t key="setting.number_decimal_separator"}</td>
							<td>{html_options name="number_decimal_separator" options=$arr_number_decimal_separator selected=$setting.number_decimal_separator}</td>
						</tr>
						<tr>
							<td>{t key="setting.number_thousands_separator"}</td>
							<td>{html_options name="number_thousands_separator" options=$arr_number_thousands_separator selected=$setting.number_thousands_separator}</td>
						</tr>
						<tr>
							<td>{t key="setting.number_decimal_digits"}</td>
							<td><input type="text" name="number_decimal_digits" value="{$setting.number_decimal_digits}"></td>
						</tr>
						<tr>
							<th rowspan="3">{t key="setting.currency_display"}</th>
							<td>{t key="setting.currency_symbol"}</td>
							<td><input type="text" name="currency_symbol" value="{$setting.currency_symbol}" placeholder="{$setting.currency}"></td>
						</tr>
						<tr>
							<td>{t key="setting.currency_symbol_position"}</td>
							<td>{html_options name="currency_symbol_position" options=$arr_currency_symbol_position selected=$setting.currency_symbol_position}</td>
						</tr>
						<tr>
							<td>{t key="setting.currency_decimal_digits"}</td>
							<td><input type="text" name="currency_decimal_digits" value="{$setting.currency_decimal_digits}"></td>
						</tr>
						<tr>
							<th rowspan="4">{t key="setting.mode"}</th>
							<td>{t key="setting.mode"}</td>
							<td>{html_options name="force_testmode" options=$arr_force_testmode selected=$setting.force_testmode}</td>
						</tr>
						<tr>
							<td>{t key="setting.developer_panel"}</td>
							<td>{html_options name="show_developer_panel" options=$arr_show_developer_panel selected=$setting.show_developer_panel}</td>
						</tr>
						<tr>
							<td>{t key="setting.error_report_level"}</td>
							<td>{html_options name="error_report_level" options=$arr_error_report_level selected=$setting.error_report_level}</td>
						</tr>
						<tr>
							<td>{t key="setting.show_homepage_link"}</td>
							<td>{html_options name="show_menu_homepage" options=$arr_show_menu selected=$setting.show_menu_homepage}</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-branding" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="2">{t key="setting.rewrite_for_root_access"}</th>
							<td>{t key="setting.class_name_default_login"}</td>
							<td><input type="text" name="rewrite_rule_root" value="{$setting.rewrite_rule_root}"></td>
						</tr>
						<tr>
							<td>{t key="setting.function"}</td>
							<td><input type="text" name="rewrite_rule_function" value="{$setting.rewrite_rule_function}"></td>
						</tr>
						<tr>
							<th>{t key="setting.ssl_section"}</th>
							<td>{t key="setting.ssl"}</td>
							<td>{html_options name="ssl" options=$arr_ssl selected=$setting.ssl}</td>
						</tr>
						<tr>
							<th>{t key="setting.default_class_name_in_url"}</th>
							<td>{t key="setting.default_class"}</td>
							<td><input type="text" name="default_class_name" value="{$setting.default_class_name}"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.admin_startup"}</th>
							<td>{t key="setting.class"}</td>
							<td><input type="text" name="startup_class1" value="{$setting.startup_class1}"></td>
						</tr>
						<tr>
							<td>{t key="setting.function"}</td>
							<td><input type="text" name="startup_function1" value="{$setting.startup_function1}"></td>
						</tr>
						<tr>
							<th rowspan="4">{t key="setting.system_name_section"}</th>
							<td>{t key="setting.system_name"} {literal}{$setting.system_name}{/literal}</td>
							<td><input type="text" name="system_name" value="{$setting.system_name}"></td>
						</tr>
						<tr>
							<td>{t key="setting.system_tag_line"}</td>
							<td><input type="text" name="system_tag_line" value="{$setting.system_tag_line}"></td>
						</tr>
						<tr>
							<td>{t key="setting.login_logo"}</td>
							<td>
								<input type="file" name="login_logo" class="fr_image_paste" data-text="{t key='setting.image_uploader'}">
								<br /><button type="button" class="ajax-link" data-class="{$class}" data-function="delete_login_logo" style="margin-top:0px;">{t key="setting.delete_login_logo"}</button>
							</td>
						</tr>
						<tr>
							<td>{t key="setting.favicon"}</td>
							<td>
								<input type="file" name="favicon" class="fr_image_paste" data-text="{t key='setting.image_uploader'}">
								<br /><button type="button" class="ajax-link" data-class="{$class}" data-function="delete_favicon" style="margin-top:0px;">{t key="setting.delete_favicon"}</button>
							</td>
						</tr>
						<tr>
							<th>{t key="setting.website"}</th>
							<td>{t key="setting.website_url"}</td>
							<td><input type="text" name="website_url" value="{$setting.website_url}"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.viewport"}</th>
							<td>{t key="setting.management_side"}</td>
							<td><input type="text" name="viewport_base" value="{$setting.viewport_base}"></td>
						</tr>
						<tr>
							<td>{t key="setting.public_side"}</td>
							<td><input type="text" name="viewport_public" value="{$setting.viewport_public}"></td>
						</tr>
						<tr>
							<th>{t key="setting.crawling"}</th>
							<td>{t key="setting.robots_txt"}</td>
							<td><textarea name="robots">{$setting.robots}</textarea></td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-mail" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="7">{t key="setting.mail_server_setting"}</th>
							<td>{t key="setting.mail_address_from"}</td>
							<td><input type="text" name="smtp_from" value="{$setting.smtp_from}"></td>
						</tr>
						<tr>
							<td>{t key="setting.mail_server"}</td>
							<td><input type="text" name="smtp_server" value="{$setting.smtp_server}"></td>
						</tr>
						<tr>
							<td>{t key="setting.mail_port"}</td>
							<td><input type="text" name="smtp_port" value="{$setting.smtp_port}"></td>
						</tr>
						<tr>
							<td>{t key="setting.mail_user"}</td>
							<td><input type="text" name="smtp_user" value="{$setting.smtp_user}"></td>
						</tr>
						<tr>
							<td>{t key="setting.mail_password"}</td>
							<td><input type="text" name="smtp_password" value="{$setting.smtp_password}"></td>
						</tr>
						<tr>
							<td>{t key="setting.smtp_secure"}</td>
							<td>{html_options name="smtp_secure" options=$arr_smtp_secure selected=$setting.smtp_secure}</td>
						</tr>
						<tr>
							<td>{t key="setting.email_for_testing"}</td>
							<td><input type="text" name="smtp_email_test" value="{$setting.smtp_email_test}"></td>
						</tr>
					</table>
					<div class="setting_tab_actions">
						<button class="ajax-link lang" data-class="setting" data-function="update" data-form="setting_form" data-send_test_mail="1">{t key="setting.submit_and_send_test_mail"}</button>
					</div>
				</div>
			</div>

			<div id="setting-tab-integrations" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="6">{t key="setting.line_bot_settings"}</th>
							<td>{t key="setting.webhook_url"}</td>
							<td><input type="text" value="{$line_webhook_url}" readonly></td>
						</tr>
						<tr>
							<td>{t key="setting.channel_secret"}</td>
							<td><input type="password" name="line_channel_secret" value="" placeholder="{$masked_setting.line_channel_secret}"></td>
						</tr>
						<tr>
							<td>{t key="setting.channel_access_token"}</td>
							<td><input type="password" name="line_accesstoken" value="" placeholder="{$masked_setting.line_accesstoken}"></td>
						</tr>
						<tr>
							<td>{t key="setting.log_file_path"}</td>
							<td><input type="text" name="line_logfile" value="{$setting.line_logfile}"></td>
						</tr>
						<tr>
							<td>{t key="setting.bot_greeting_message"}</td>
							<td><textarea name="line_bot_greeting_message">{$setting.line_bot_greeting_message}</textarea></td>
						</tr>
						<tr>
							<td>{t key="setting.line_forward_unknown_to_manager"}</td>
							<td>{html_options name="line_forward_unknown_to_manager" options=$arr_line_forward_unknown_to_manager selected=$setting.line_forward_unknown_to_manager}</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-openai" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="3">{t key="setting.for_bot"}</th>
							<td>{t key="setting.api_key"}</td>
							<td><input type="password" name="chatgpt_api_key" value="" placeholder="{$masked_setting.chatgpt_api_key}"></td>
						</tr>
						<tr>
							<td>{t key="setting.endpoint_url"}<br /><span style="font-size:10px;">{t key="setting.for_completions"}</span></td>
							<td><input type="text" name="chatgpt_api_url" value="{$setting.chatgpt_api_url}"></td>
						</tr>
						<tr>
							<td>{t key="setting.default_model"}</td>
							<td><input type="text" name="chatgpt_api_model" value="{$setting.chatgpt_api_model}"></td>
						</tr>
						<tr>
							<th rowspan="3">{t key="setting.for_coding"}</th>
							<td>{t key="setting.api_key"}</td>
							<td><input type="password" name="chatgpt_coding_key" value="" placeholder="{$masked_setting.chatgpt_coding_key}"></td>
						</tr>
						<tr>
							<td>{t key="setting.endpoint_url"}<br /><span style="font-size:10px;">{t key="setting.for_completions"}</span></td>
							<td><input type="text" name="chatgpt_coding_url" value="{$setting.chatgpt_coding_url}"></td>
						</tr>
						<tr>
							<td>{t key="setting.default_model"}</td>
							<td><input type="text" name="chatgpt_coding_model" value="{$setting.chatgpt_coding_model}"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.common"}</th>
							<td>{t key="setting.log_file"}</td>
							<td><input type="text" name="openai_logfile" value="{$setting.openai_logfile}"></td>
						</tr>
						<tr>
							<td>{t key="setting.max_vector_store_files"}</td>
							<td><input type="text" name="max_vs" value="{$setting.max_vs}"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.endpoints_for_completions"}</th>
							<td>OpenAI</td>
							<td>https://api.openai.com/v1/chat/completions</td>
						</tr>
						<tr>
							<td>Sakura</td>
							<td>https://api.ai.sakura.ad.jp/v1/chat/completions</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-payment" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="4">{t key="setting.square_setting"}</th>
							<td>{t key="setting.application_id"}</td>
							<td><input type="text" name="square_application_id" value="{$setting.square_application_id}"></td>
						</tr>
						<tr>
							<td>{t key="setting.access_token"}</td>
							<td><input type="text" name="square_access_token" value="{$setting.square_access_token}"></td>
						</tr>
						<tr>
							<td>{t key="setting.location_id"}</td>
							<td><input type="text" name="square_location_id" value="{$setting.square_location_id}"></td>
						</tr>
						<tr>
							<td>{t key="setting.currency"}</td>
							<td>{html_options name="currency" options=$currency_list selected=$setting.currency}</td>
						</tr>
					</table>
					<div class="setting_tab_actions">
						<button type="button" class="ajax-link lang" data-class="setting" data-function="square">{t key="setting.square_test"}</button>
					</div>
				</div>
			</div>

			<div id="setting-tab-google" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th>{t key="setting.google_settings"}</th>
							<td>{t key="setting.api_key"}</td>
							<td><input type="password" name="api_key_map" value="" placeholder="{$masked_setting.api_key_map}"></td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-api-auth" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th rowspan="2">{t key="setting.api_authentication_hmac"}</th>
							<td>{t key="setting.api_key"}</td>
							<td><input type="text" name="api_key" value="{$setting.api_key}" readonly onclick="this.select();"></td>
						</tr>
						<tr>
							<td>{t key="setting.api_secret"}</td>
							<td><input type="text" name="api_secret" value="{$setting.api_secret}" readonly onclick="this.select();"></td>
						</tr>
						<tr>
							<th rowspan="2">{t key="setting.release_api_hmac"}</th>
							<td>{t key="setting.release_api_key"}</td>
							<td><input type="text" name="release_api_key" value="" placeholder="{$masked_setting.release_api_key}" autocomplete="off" spellcheck="false"></td>
						</tr>
						<tr>
							<td>{t key="setting.release_api_secret"}</td>
							<td><input type="text" name="release_api_secret" value="" placeholder="{$masked_setting.release_api_secret}" autocomplete="off" spellcheck="false"></td>
						</tr>
					</table>
				</div>
			</div>

			<div id="setting-tab-vimeo" class="setting_tab_panel">
				<div class="setting_tab_inner">
					<table class="setting_detail_table">
						<tr>
							<th>{t key="setting.vimeo_setting"}</th>
							<td>{t key="setting.access_token"}</td>
							<td><input type="password" name="vimeo_access_token" value="" placeholder="{$masked_setting.vimeo_access_token}"></td>
						</tr>
					</table>
				</div>
			</div>

		</div>
	</form>
</div>

<script>
	$(function () {
		$("#setting_tabs").tabs();

		var localeOptionMap = {$locale_option_map_json nofilter};
		var localePresetMap = {$locale_preset_map_json nofilter};
		var presetFieldLabelMap = {$preset_field_label_map_json nofilter};
		var $languageSelect = $('select[name="framework_language_code"]');
		var $localeSelect = $('select[name="locale_code"]');
		var presetFieldNames = [
			"date_format",
			"datetime_format",
			"year_month_format",
			"month_day_format",
			"number_decimal_separator",
			"number_thousands_separator",
			"currency",
			"currency_symbol",
			"currency_symbol_position",
			"currency_decimal_digits"
		];
		var getField = function (fieldName) {
			return $('[name="' + fieldName + '"]');
		};

		var getDefaultLocaleForLanguage = function (languageCode) {
			var options = localeOptionMap[languageCode] || {};
			var firstValue = "";
			$.each(options, function (value) {
				if (firstValue === "") {
					firstValue = value;
				}
			});
			return firstValue;
		};

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

		var buildPresetChanges = function (localeCode) {
			var preset = localePresetMap[localeCode] || null;
			var changes = [];
			if (!preset) {
				return changes;
			}

			changes.push({
				field: "locale_code",
				label: presetFieldLabelMap.locale_code || "Locale Code",
				value: localeCode
			});

			$.each(presetFieldNames, function (_, fieldName) {
				var $field = getField(fieldName);
				if ($field.length === 0) {
					return;
				}
				var currentValue = String($field.val() === undefined ? "" : $field.val());
				var nextValue = String(preset[fieldName] === undefined ? "" : preset[fieldName]);
				if (currentValue !== nextValue) {
					changes.push({
						field: fieldName,
						label: presetFieldLabelMap[fieldName] || fieldName,
						value: nextValue
					});
				}
			});

			return changes;
		};

		var applyPresetChanges = function (changes) {
			$.each(changes, function (_, change) {
				getField(change.field).val(change.value);
			});
		};

		var confirmAndApplyPreset = function (localeCode) {
			var changes = buildPresetChanges(localeCode);
			if (changes.length === 0) {
				return;
			}
			applyPresetChanges(changes);
		};

		$languageSelect.on("change", function () {
			refreshLocaleOptions();
		});

		$(".setting_apply_locale_preset_link").on("click", function (e) {
			e.preventDefault();
			var localeCode = $localeSelect.val() || "";
			if (!localeCode) {
				var languageCode = $languageSelect.val() || "en";
				localeCode = getDefaultLocaleForLanguage(languageCode);
			}
			if (!localeCode) {
				return false;
			}
			confirmAndApplyPreset(localeCode);
			return false;
		});

		refreshLocaleOptions();
	});
</script>
