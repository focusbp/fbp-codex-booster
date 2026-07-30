<?php

/*
 *  YOU CAN'T CHANGE THIS PROJECT.
 *  It will be overwritten when the framework updates.
 */

include_once (__DIR__ . "/../../lib/FrameworkTheme.php");

class setting {

	private $ctl;
	private $ffm;
	private $sensitive_keys = [
		"api_key",
		"api_secret",
		"api_key_map",
		"chatgpt_api_key",
		"line_accesstoken",
			"line_channel_secret",
			"smtp_password",
			"square_application_secret",
			"square_access_token",
			"vimeo_access_token",
			"vimeo_client_secret",
		"release_api_key",
		"release_api_secret",
	];
	private $arr_display_errors = [0 => "On Console", 1 => "Display to Window"];
	private $arr_smtp_secure = [0 => "false", 1 => "tls", 2 => "ssl"];
	private $arr_force_testmode = ["0" => "Production Mode", "1" => "Developer mode"];
	private $arr_show_menu = [0=>"No",1=>"Yes"];
	private $arr_ssl = [0=>"http and https","https only"];
	private $arr_flg_show_lang_on_chat = [0=>"Show",1=>"Hide"];
	private $arr_show_developer_panel = [0=>"Hide",1=>"Show"];
	private $arr_error_report_level = [];
	private $arr_framework_language_code = [];
	private $arr_locale_code = [];
	private $currency_list = [];
	private $arr_number_decimal_separator = [];
	private $arr_number_thousands_separator = [];
	private $arr_currency_symbol_position = [];

	function __construct(Controller $ctl) {
		$this->ctl = $ctl;
		$this->ffm = $ctl->db("setting", "setting");
		$ctl->assign("arr_customize", [0 => "Default", 1 => "Customize"]);
		$ctl->assign("arr_onoff", [0 => "On", 1 => "Off"]);
		$ctl->assign("arr_display_errors", $this->arr_display_errors);
		$ctl->assign("arr_smtp_secure", $this->arr_smtp_secure);
		$this->arr_framework_language_code = I18nSimple::get_language_options();
		$this->arr_locale_code = I18nSimple::get_locale_options();
		$ctl->assign("arr_framework_language_code", $this->arr_framework_language_code);
		$ctl->assign("arr_locale_code", $this->arr_locale_code);
		$ctl->assign("locale_option_map_json", json_encode($this->get_locale_option_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$ctl->assign("locale_preset_map_json", json_encode($this->get_locale_preset_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$ctl->assign("preset_field_label_map_json", json_encode($this->get_preset_field_label_map($ctl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$ctl->assign("arr_force_testmode", $this->arr_force_testmode);
		$ctl->assign("arr_show_menu", $this->arr_show_menu);
		$ctl->assign("arr_ssl",$this->arr_ssl);
		$ctl->assign("arr_flg_show_lang_on_chat",$this->arr_flg_show_lang_on_chat);
		$ctl->assign("arr_show_developer_panel",$this->arr_show_developer_panel);
		$this->arr_error_report_level = [
			"legacy_compatible" => $ctl->t("setting.error_report_level.option.legacy_compatible"),
			"strict" => $ctl->t("setting.error_report_level.option.strict"),
		];
		$ctl->assign("arr_error_report_level", $this->arr_error_report_level);
		$ctl->assign("arr_line_forward_unknown_to_manager", $this->get_line_forward_unknown_to_manager_options($ctl));
		$this->arr_number_decimal_separator = $this->get_number_decimal_separator_options($ctl);
		$this->arr_number_thousands_separator = $this->get_number_thousands_separator_options($ctl);
		$this->arr_currency_symbol_position = $this->get_currency_symbol_position_options($ctl);
		$ctl->assign("arr_number_decimal_separator", $this->arr_number_decimal_separator);
		$ctl->assign("arr_number_thousands_separator", $this->arr_number_thousands_separator);
		$ctl->assign("arr_currency_symbol_position", $this->arr_currency_symbol_position);
		
		$this->currency_list = include (__DIR__."/currency.php");
		$ctl->assign("currency_list", $this->currency_list);
		
		$timezones = array_combine(timezone_identifiers_list(), timezone_identifiers_list());
		$ctl->assign('timezones', $timezones);

	}

	private function normalize_project_portal_url($url) {
		$url = trim((string) $url);
		if ($url === "") {
			return "";
		}
		if (!preg_match('#^https?://#i', $url)) {
			return "";
		}
		return $url;
	}

	function update(Controller $ctl) {
		$setting = $this->save_posted_setting($ctl);
		$this->regenerate_setting_files($ctl, $setting);

		// Login Logo
		if ($ctl->is_posted_file("login_logo")) {
			$ctl->save_posted_file("login_logo", "login_logo");
		}

		// 
		// favicon
		if ($ctl->is_posted_file("favicon")) {
			$ctl->save_posted_file("favicon", "favicon");
		}
		
		// Test Mail
		if ($ctl->POST("send_test_mail") == 1) {
			$setting = $this->ffm->get(1);
			$ctl->set_session("setting", $setting);
			$to = $setting["smtp_email_test"];
			try {
				$ctl->send_mail_string($setting["smtp_from"], $to, "TEST", "This is test mail from setting.\n" . $_SERVER["HTTP_HOST"], null, true);
				$ctl->show_notification_text("Success!");
				return;
			} catch (Throwable $e) {
				$ctl->show_notification_text($e->getMessage(), 8, "#D14343", "#FFF", 16, 920);
				return;
			}
		}

		$ctl->show_notification_text($ctl->t("setting.notification.saved"));
		$ctl->res_reload();
	}

	function api_get_setting(Controller $ctl): array {
		$setting = $this->ffm->get(1);
		if (!is_array($setting)) {
			$setting = [];
		}
		return $setting;
	}

	function api_update_setting(Controller $ctl, array $data): array {
		$setting = $this->save_setting_values($ctl, $data, true);
		$files = $this->regenerate_setting_files($ctl, $setting);
		return [
			"setting" => $setting,
			"files" => $files,
		];
	}

	private function save_posted_setting(Controller $ctl): array {
		return $this->save_setting_values($ctl, $ctl->POST(), false);
	}

	private function save_setting_values(Controller $ctl, array $values, bool $filter_known_fields): array {
		$setting = $this->ffm->get(1);
		if ($setting == null) {
			$setting = array();
			$this->ffm->insert($setting);
		}
		$known_fields = $filter_known_fields ? $this->get_setting_field_names() : null;
		foreach ($values as $key => $val) {
			$key = (string) $key;
			if ($key === "smtp_password_web") {
				$key = "smtp_password";
			}
			if ($known_fields !== null && !isset($known_fields[$key])) {
				continue;
			}
			if (in_array($key, $this->sensitive_keys, true) && trim((string) $val) === "") {
				continue;
			}
			$setting[$key] = $val;
		}
		if (empty($setting["rewrite_rule_root"])) {
			$setting["rewrite_rule_root"] = "login";
		}
		if (empty($setting["rewrite_rule_function"])) {
			$setting["rewrite_rule_function"] = "page";
		}
		if (empty($setting["currency"])) {
			$setting["currency"] = "JPY";
		}
		if (empty($setting["robots"])) {
			$setting["robots"] = "User-Agent: *\nAllow: /\n";
		}
		if (empty($setting["timezone"])){
			$setting["timezone"] = "Asia/Tokyo";
		}
		if (empty($setting["date_format"])) {
			$setting["date_format"] = "Y/m/d";
		}
		if (empty($setting["datetime_format"])) {
			$setting["datetime_format"] = "Y/m/d H:i";
		}
		if (empty($setting["year_month_format"])) {
			$setting["year_month_format"] = "Y/m";
		}
		if (empty($setting["month_day_format"])) {
			$setting["month_day_format"] = "n/j";
		}
		$setting["number_decimal_separator"] = $this->normalize_number_decimal_separator($setting["number_decimal_separator"] ?? "");
		$setting["number_thousands_separator"] = $this->normalize_number_thousands_separator($setting["number_thousands_separator"] ?? "");
		if (!isset($setting["number_decimal_digits"]) || $setting["number_decimal_digits"] === "") {
			$setting["number_decimal_digits"] = 2;
		}
		if (!isset($setting["currency_symbol_position"]) || $setting["currency_symbol_position"] === "") {
			$setting["currency_symbol_position"] = "before";
		}
		if (!isset($setting["currency_decimal_digits"]) || $setting["currency_decimal_digits"] === "") {
			$setting["currency_decimal_digits"] = $this->get_default_currency_decimal_digits((string) $setting["currency"]);
		}
		$setting["framework_language_code"] = $this->normalize_framework_language_code((string) ($setting["framework_language_code"] ?? "en"));
		$setting["locale_code"] = $this->normalize_locale_code($setting["locale_code"] ?? "", $setting["framework_language_code"]);
		$setting["lang_priority"] = 1;
		$setting["lang_default"] = I18nSimple::get_legacy_lang_code_from_setting($setting);
		if (!isset($setting["flg_show_lang_on_chat"])) {
			$setting["flg_show_lang_on_chat"] = 0;
		}
		if (!isset($setting["line_forward_unknown_to_manager"])) {
			$setting["line_forward_unknown_to_manager"] = 0;
		}
		$setting = fbp_normalize_framework_theme_setting($setting);
		$setting["project_portal_url"] = $this->normalize_project_portal_url($setting["project_portal_url"] ?? "");
		$setting["error_report_level"] = $this->normalize_error_report_level($setting["error_report_level"] ?? "");
		
		
		$this->ffm->update($setting);
		$ctl->set_session("setting", $setting);
		return $setting;
	}

	function regenerate_setting_files(Controller $ctl, array $setting): array {
		$scriptName = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
		$directoryPath = pathinfo($scriptName, PATHINFO_DIRNAME);
		if (endsWith($directoryPath, "/fbp")) {
			$directoryPath = substr($directoryPath, 0, strlen($directoryPath) - 4);
		}
		if ($directoryPath === "/" || $directoryPath === ".") {
			$directoryPath = "";
		}

		$template = file_get_contents(dirname(__FILE__) . "/Templates/htaccess.tpl");
		$template = str_replace('{$class}', $setting["rewrite_rule_root"], $template);
		$template = str_replace('{$function}', $setting["rewrite_rule_function"], $template);
		$template = str_replace('{$subpath}', $directoryPath, $template);
		$template = str_replace('{$default_class_name}', $setting["default_class_name"] ?? "", $template);
		if ((int) ($setting["ssl"] ?? 0) === 1) {
			$template = str_replace('{$ssl}', 'RewriteCond %{HTTPS} off' . "\n" . 'RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R]', $template);
		} else {
			$template = str_replace('{$ssl}', "", $template);
		}
		$htaccess_path = dirname(__FILE__) . "/../../../.htaccess";
		file_put_contents($htaccess_path, $template);

		$robots_path = dirname(__FILE__) . "/../../../robots.txt";
		file_put_contents($robots_path, $setting["robots"] ?? "User-Agent: *\nAllow: /\n");

		return [
			"htaccess" => is_file($htaccess_path),
			"robots" => is_file($robots_path),
		];
	}

	private function get_setting_field_names(): array {
		$fields = [];
		$fmt_path = __DIR__ . "/fmt/setting.fmt";
		if (!is_file($fmt_path)) {
			return $fields;
		}
		foreach (file($fmt_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$parts = explode(",", trim($line));
			if (!empty($parts[0])) {
				$fields[$parts[0]] = true;
			}
		}
		return $fields;
	}

	private function save_posted_square_setting(Controller $ctl): array {
		$setting = $this->ffm->get(1);
		if ($setting == null) {
			$setting = array();
			$this->ffm->insert($setting);
		}

		$square_keys = [
			"square_application_id",
			"square_application_secret",
			"square_access_token",
			"square_location_id",
			"currency",
		];
		foreach ($square_keys as $key) {
			$val = $ctl->POST($key);
			if ($val === null) {
				continue;
			}
			if (in_array($key, $this->sensitive_keys, true) && trim((string) $val) === "") {
				continue;
			}
			$setting[$key] = $val;
		}
		if (empty($setting["currency"])) {
			$setting["currency"] = "JPY";
		}

		$this->ffm->update($setting);
		$ctl->set_session("setting", $setting);
		return $setting;
	}

	function page(Controller $ctl) {

		$ctl->generate_api_credentials();
		$setting = $this->ffm->get(1);
		$changed = false;

		if (empty($setting["user_type_name0"])) {
			$setting["user_type_name0"] = "User";
		}
		if (empty($setting["currency"])) {
			$setting["currency"] = "JPY";
		}
		if (empty($setting["timezone"])){
			$setting["timezone"] = "Asia/Tokyo";
		}
		if (empty($setting["date_format"])) {
			$setting["date_format"] = "Y/m/d";
		}
		if (empty($setting["datetime_format"])) {
			$setting["datetime_format"] = "Y/m/d H:i";
		}
		if (empty($setting["year_month_format"])) {
			$setting["year_month_format"] = "Y/m";
		}
		if (empty($setting["month_day_format"])) {
			$setting["month_day_format"] = "n/j";
		}
		$setting["number_decimal_separator"] = $this->normalize_number_decimal_separator($setting["number_decimal_separator"] ?? "");
		$setting["number_thousands_separator"] = $this->normalize_number_thousands_separator($setting["number_thousands_separator"] ?? "");
		if (!isset($setting["number_decimal_digits"]) || $setting["number_decimal_digits"] === "") {
			$setting["number_decimal_digits"] = 2;
		}
		if (!isset($setting["currency_symbol_position"]) || $setting["currency_symbol_position"] === "") {
			$setting["currency_symbol_position"] = "before";
		}
		if (!isset($setting["currency_decimal_digits"]) || $setting["currency_decimal_digits"] === "") {
			$setting["currency_decimal_digits"] = $this->get_default_currency_decimal_digits((string) $setting["currency"]);
		}
		$setting["framework_language_code"] = $this->normalize_framework_language_code((string) ($setting["framework_language_code"] ?? "en"));
		$setting["locale_code"] = $this->normalize_locale_code($setting["locale_code"] ?? "", $setting["framework_language_code"]);
		$setting["lang_priority"] = 1;
		$setting["lang_default"] = I18nSimple::get_legacy_lang_code_from_setting($setting);
		if (!isset($setting["flg_show_lang_on_chat"])) {
			$setting["flg_show_lang_on_chat"] = 0;
		}
		if (!isset($setting["line_forward_unknown_to_manager"])) {
			$setting["line_forward_unknown_to_manager"] = 0;
		}
		$normalized_theme_setting = fbp_normalize_framework_theme_setting($setting);
		if (
			($setting["framework_primary_color"] ?? "") !== ($normalized_theme_setting["framework_primary_color"] ?? "") ||
			($setting["framework_menu_text_color"] ?? "") !== ($normalized_theme_setting["framework_menu_text_color"] ?? "")
		) {
			$changed = true;
		}
		$setting = $normalized_theme_setting;
		$normalized_error_report_level = $this->normalize_error_report_level($setting["error_report_level"] ?? "");
		if (($setting["error_report_level"] ?? "") !== $normalized_error_report_level) {
			$changed = true;
		}
		$setting["error_report_level"] = $normalized_error_report_level;
		if ($changed && !empty($setting["id"])) {
			$this->ffm->update($setting);
		}

		$ctl->assign("setting", $setting);
		$ctl->assign("masked_setting", $this->mask_sensitive_setting($setting));
		$ctl->assign("line_webhook_url", $ctl->get_APP_URL("webhook_line", "receive"));
		$ctl->assign("mcp_server_info", $this->get_mcp_server_info($ctl));
		$ctl->assign("mcp_servers_info", $this->get_mcp_servers_info($ctl));

		$ctl->show_main_area("index.tpl", $ctl->t("setting.dialog.index"));
	}

	function mcp_server_detail(Controller $ctl) {
		$id = (int) ($ctl->POST("server_id") ?? ($ctl->GET("server_id") ?? 0));
		$server = $this->find_mcp_server_info($ctl, $id);
		if (empty($server)) {
			$ctl->show_notification_text("MCP Server not found.");
			return;
		}
		$ctl->assign("mcp_server", $server);
		$ctl->show_multi_dialog("setting_mcp_server_detail_" . (int) $server["id"], "mcp_server_detail.tpl", "MCP Server", 760, true, true);
	}

	private function get_mcp_server_info(Controller $ctl): array {
		$base_url = $this->get_mcp_base_url($ctl);
		return [
			"status" => $ctl->t("setting.mcp_status_enabled"),
			"title" => "FBP MCP Server",
			"auth_mode" => "oauth2",
			"endpoint_url" => $ctl->get_APP_URL("mcp_server", "rpc"),
			"authorization_url" => $ctl->get_APP_URL("mcp_server", "authorize"),
			"token_url" => $ctl->get_APP_URL("mcp_server", "token"),
			"resource_metadata_url" => $base_url . "/.well-known/oauth-protected-resource",
		];
	}

	private function get_mcp_servers_info(Controller $ctl): array {
		$ffm_servers = $ctl->db("mcp_server_config", "mcp_manage");
		$ffm_tools = $ctl->db("mcp_tools", "mcp_manage");
		$rows = $ffm_servers->getall("sort", SORT_ASC);
		if (count($rows) === 0) {
			$default = $this->get_mcp_server_info($ctl);
			$default["id"] = 0;
			$default["server_key"] = "default";
			$default["subject_type"] = "fbp_user";
			$default["subject_provider_class"] = "";
			$default["enabled"] = 1;
			$default["tool_count"] = 0;
			return [$default];
		}

		$list = [];
		foreach ($rows as $server) {
			$id = (int) ($server["id"] ?? 0);
			$server_key = (string) ($server["server_key"] ?? "default");
			$list[] = [
				"id" => $id,
				"enabled" => (int) ($server["enabled"] ?? 0),
				"status" => (int) ($server["enabled"] ?? 0) === 1 ? $ctl->t("setting.mcp_status_enabled") : $ctl->t("common.disabled"),
				"server_key" => $server_key !== "" ? $server_key : "default",
				"title" => (string) ($server["title"] ?? ""),
				"description" => (string) ($server["description"] ?? ""),
				"auth_mode" => (string) ($server["auth_mode"] ?? "oauth2"),
				"subject_type" => (string) ($server["subject_type"] ?? "fbp_user"),
				"subject_provider_class" => (string) ($server["subject_provider_class"] ?? ""),
				"endpoint_url" => $this->mcp_url($ctl, "rpc", $server_key),
				"authorization_url" => $this->mcp_url($ctl, "authorize", $server_key),
				"token_url" => $ctl->get_APP_URL("mcp_server", "token"),
				"resource_metadata_url" => $this->mcp_url($ctl, "oauth_protected_resource", $server_key),
				"tool_count" => $id > 0 ? count($ffm_tools->select("server_id", $id)) : 0,
			];
		}
		return $list;
	}

	private function find_mcp_server_info(Controller $ctl, int $id): array {
		foreach ($this->get_mcp_servers_info($ctl) as $server) {
			if ((int) ($server["id"] ?? 0) === $id) {
				return $server;
			}
		}
		return [];
	}

	private function mcp_url(Controller $ctl, string $function, string $server_key): string {
		$params = [];
		if ($server_key !== "" && $server_key !== "default") {
			$params["server"] = $server_key;
		}
		$url = $ctl->get_APP_URL("mcp_server", $function);
		if (count($params) === 0) {
			return $url;
		}
		return $url . (strpos($url, "?") === false ? "?" : "&") . http_build_query($params);
	}

	private function get_mcp_base_url(Controller $ctl): string {
		$resource = $ctl->get_APP_URL("mcp_server", "rpc");
		$suffix = "/mcp_server*rpc";
		if (substr($resource, -strlen($suffix)) === $suffix) {
			return substr($resource, 0, -strlen($suffix));
		}
		return rtrim(preg_replace('#/mcp_server\*rpc(?:[?&].*)?$#', "", $resource), "/");
	}

	function json_upload(Controller $ctl) {
		$ctl->deny_forbidden_access();
	}

	function json_upload_exe(Controller $ctl) {
		$ctl->deny_forbidden_access();
	}

	function json_download(Controller $ctl) {
		$ctl->deny_forbidden_access();
	}

	function delete_login_logo(Controller $ctl) {
		$ctl->delete_saved_file("login_logo");
		$ctl->show_notification_text($ctl->t("setting.notification.login_logo_deleted"));
	}
	
	function delete_favicon(Controller $ctl) {
		$ctl->delete_saved_file("favicon");
		$ctl->show_notification_text($ctl->t("setting.notification.favicon_deleted"));
	}

	function square(Controller $ctl) {
		$setting = $this->save_posted_square_setting($ctl);
		$ctl->set_square(
			$setting["square_application_id"] ?? "",
			$setting["square_access_token"] ?? "",
			$setting["square_location_id"] ?? ""
		);

		// Get customer informations before input credit card.
		$name = "Test";
		$email = "info@soshiki-kaikaku.com";
		$address = "テスト住所";
		$amount = 100; // 100 Yen

		$callback_parameter_array = ["name" => $name, "email" => $email, "address" => $address, "amount" => $amount];

		// Show credit card dialog.
		$ctl->show_square_dialog("setting", "pay", $callback_parameter_array);
	}

	function openai_connection_test(Controller $ctl) {
		$setting = $this->ffm->get(1);
		if (!is_array($setting)) {
			$setting = [];
		}

		$key = trim((string) $ctl->POST("chatgpt_api_key"));
		if ($key === "") {
			$key = trim((string) ($setting["chatgpt_api_key"] ?? ""));
		}
		$url = trim((string) $ctl->POST("chatgpt_api_url"));
		$model = trim((string) $ctl->POST("chatgpt_api_model"));

		if ($url === "") {
			$url = trim((string) ($setting["chatgpt_api_url"] ?? ""));
		}
		if ($model === "") {
			$model = trim((string) ($setting["chatgpt_api_model"] ?? ""));
		}

		if ($key === "" || $url === "" || $model === "") {
			$ctl->show_notification_text($ctl->t("setting.openai_connection_test_missing"), 8, "#D14343", "#FFF", 16, 920);
			return;
		}

		try {
			$reply = $this->request_openai_connection_test($key, $url, $model);
			$ctl->show_notification_text($ctl->t("setting.openai_connection_test_success") . ": " . $reply, 8, "#2E7D32", "#FFF", 16, 920);
		} catch (Throwable $e) {
			$ctl->show_notification_text($ctl->t("setting.openai_connection_test_failed") . ": " . $e->getMessage(), 8, "#D14343", "#FFF", 16, 920);
		}
	}

	private function request_openai_connection_test(string $key, string $url, string $model): string {
		$payload = [
		    "model" => $model,
		    "messages" => [
			["role" => "user", "content" => "接続テストです。OKと返信してください。"],
		    ],
		    "max_completion_tokens" => 16,
		];

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
		    "Content-Type: application/json",
		    "Authorization: Bearer " . $key,
		]);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$response = curl_exec($curl);
		if ($response === false) {
			$errno = curl_errno($curl);
			$error = curl_error($curl);
			curl_close($curl);
			throw new Exception("curl error(" . $errno . "): " . $error);
		}
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		$decoded = json_decode($response, true);
		if (!is_array($decoded)) {
			throw new Exception("invalid JSON response: " . json_last_error_msg());
		}
		if ($status < 200 || $status >= 300) {
			$message = $decoded["error"]["message"] ?? ("HTTP " . $status);
			throw new Exception((string) $message);
		}

		$reply = trim((string) ($decoded["choices"][0]["message"]["content"] ?? ""));
		if ($reply === "") {
			throw new Exception("empty response");
		}
		return $reply;
	}

	function pay(Controller $ctl) {

		// You can call set_square($square_application_id=,$square_access_token)  here to change square account.
		// $ctl->set_square("","");
		// Get parameters from the framework.
		$param = $ctl->get_square_callback_parameter_array();

		try {
			// Regist Customer SQUARE and get customer id
			$square_customer_id = $ctl->square_regist_customer($param["name"], $param["email"], $param["address"]);
			if ((string) $square_customer_id === "") {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square customer registration failed."));
			}

			// Regist the Card
			$card_id = $ctl->square_regist_card($square_customer_id);
			if ((string) $card_id === "") {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square card registration failed."));
			}

			// ------------------------------------------------------------------------------
			// If you save square_customer_id and card_id, You can execute payment any time , any amount!
			// ------------------------------------------------------------------------------
			// Execute Payment
			$result = $ctl->square_payment($square_customer_id, $card_id, $param["amount"]);

			if ($result) {
				$ctl->close_square_dialog();
				$ctl->assign("msg", "SUCCESS");
				$ctl->show_multi_dialog("square_dialog", "square_result.tpl", $ctl->t("setting.square_result"));
			} else {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square payment failed."));
			}
		} catch (Exception $e) {
			$ctl->show_square_dialog("setting", "pay", $param, $e->getMessage());
		}
	}

	private function mask_sensitive_setting($setting) {
		if (!is_array($setting)) {
			return [];
		}
		foreach ($this->sensitive_keys as $key) {
			if (isset($setting[$key])) {
				$setting[$key] = $this->mask_secret_value((string) $setting[$key]);
			}
		}
		return $setting;
	}

	private function mask_secret_value(string $value): string {
		if ($value === "") {
			return "";
		}
		return $this->ctl->t("common.configured");
	}

	private function normalize_framework_language_code(string $code): string {
		$code = strtolower(trim($code));
		if (!preg_match('/^[a-z]{2}$/', $code)) {
			return "en";
		}
		return $code;
	}

	private function normalize_error_report_level($value): string {
		$value = trim((string) $value);
		if (!in_array($value, ["legacy_compatible", "strict"], true)) {
			return "legacy_compatible";
		}
		return $value;
	}

	private function normalize_locale_code($value, string $framework_language_code): string {
		$value = trim((string) $value);
		$allowed = $this->get_locale_options_by_language($framework_language_code);
		if (isset($allowed[$value])) {
			return $value;
		}
		return I18nSimple::get_default_locale_code_from_language_code($framework_language_code);
	}

	private function get_locale_option_map(): array {
		return [
			"ja" => $this->get_locale_options_by_language("ja"),
			"en" => $this->get_locale_options_by_language("en"),
			"zh" => $this->get_locale_options_by_language("zh"),
		];
	}

	private function get_locale_preset_map(): array {
		return [
			"ja-JP" => [
				"date_format" => "Y/m/d",
				"datetime_format" => "Y/m/d H:i",
				"year_month_format" => "Y/m",
				"month_day_format" => "n/j",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "JPY",
				"currency_symbol" => "¥",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 0,
			],
			"ja-OS" => [
				"date_format" => "Y/m/d",
				"datetime_format" => "Y/m/d H:i",
				"year_month_format" => "Y/m",
				"month_day_format" => "n/j",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "JPY",
				"currency_symbol" => "¥",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 0,
			],
			"en-US" => [
				"date_format" => "m/d/Y",
				"datetime_format" => "m/d/Y h:i A",
				"year_month_format" => "M Y",
				"month_day_format" => "n/j",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "USD",
				"currency_symbol" => "$",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 2,
			],
			"en-GB" => [
				"date_format" => "d/m/Y",
				"datetime_format" => "d/m/Y H:i",
				"year_month_format" => "M Y",
				"month_day_format" => "j/n",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "GBP",
				"currency_symbol" => "£",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 2,
			],
			"zh-CN" => [
				"date_format" => "Y/m/d",
				"datetime_format" => "Y/m/d H:i",
				"year_month_format" => "Y/m",
				"month_day_format" => "n/j",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "CNY",
				"currency_symbol" => "¥",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 2,
			],
			"zh-TW" => [
				"date_format" => "Y/m/d",
				"datetime_format" => "Y/m/d H:i",
				"year_month_format" => "Y/m",
				"month_day_format" => "n/j",
				"number_decimal_separator" => "dot",
				"number_thousands_separator" => "comma",
				"currency" => "TWD",
				"currency_symbol" => "NT$",
				"currency_symbol_position" => "before",
				"currency_decimal_digits" => 0,
			],
		];
	}

	private function get_preset_field_label_map(Controller $ctl): array {
		return [
			"locale_code" => $ctl->t("setting.locale_code"),
			"date_format" => $ctl->t("setting.date_format"),
			"datetime_format" => $ctl->t("setting.datetime_format"),
			"year_month_format" => $ctl->t("setting.year_month_format"),
			"month_day_format" => $ctl->t("setting.month_day_format"),
			"number_decimal_separator" => $ctl->t("setting.number_decimal_separator"),
			"number_thousands_separator" => $ctl->t("setting.number_thousands_separator"),
			"currency" => $ctl->t("setting.currency"),
			"currency_symbol" => $ctl->t("setting.currency_symbol"),
			"currency_symbol_position" => $ctl->t("setting.currency_symbol_position"),
			"currency_decimal_digits" => $ctl->t("setting.currency_decimal_digits"),
		];
	}

	private function get_locale_options_by_language(string $language_code): array {
		$all = I18nSimple::get_locale_options();
		$map = [
			"ja" => ["ja-JP", "ja-OS"],
			"en" => ["en-US", "en-GB"],
			"zh" => ["zh-CN", "zh-TW"],
		];
		$codes = $map[$language_code] ?? [I18nSimple::get_default_locale_code_from_language_code($language_code)];
		$options = [];
		foreach ($codes as $code) {
			if (isset($all[$code])) {
				$options[$code] = $all[$code];
			}
		}
		return $options;
	}

	private function get_line_forward_unknown_to_manager_options(Controller $ctl): array {
		return [
			0 => $ctl->t("setting.line_forward_unknown_to_manager.option.forward"),
			1 => $ctl->t("setting.line_forward_unknown_to_manager.option.no_forward"),
		];
	}

	private function get_number_decimal_separator_options(Controller $ctl): array {
		return [
			"dot" => "Dot (.)",
			"comma" => "Comma (,)",
		];
	}

	private function get_number_thousands_separator_options(Controller $ctl): array {
		return [
			"comma" => "Comma (,)",
			"dot" => "Dot (.)",
			"space" => "Space",
			"none" => "None",
		];
	}

	private function get_currency_symbol_position_options(Controller $ctl): array {
		return [
			"before" => "Before Amount",
			"after" => "After Amount",
		];
	}

	private function get_default_currency_decimal_digits(string $currency): int {
		if (in_array(strtoupper($currency), ["JPY", "KRW", "VND"], true)) {
			return 0;
		}
		return 2;
	}

	private function normalize_number_decimal_separator($value): string {
		$value = trim((string) $value);
		if ($value === "," || $value === "comma") {
			return "comma";
		}
		return "dot";
	}

	private function normalize_number_thousands_separator($value): string {
		$value = (string) $value;
		if ($value === "," || $value === "comma") {
			return "comma";
		}
		if ($value === "." || $value === "dot") {
			return "dot";
		}
		if ($value === " " || $value === "space") {
			return "space";
		}
		if ($value === "" || $value === "none") {
			return "none";
		}
		return "comma";
	}

}
