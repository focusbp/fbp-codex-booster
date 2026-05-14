<?php

class login {

	private $ffm_user;
	private $ffm_setting;
	private $remember_me_cookie_name = "remember_me";
	private $remember_me_ttl = 2592000; // 30 days
	private $pending_account_session_key = "login_first_account_pending";
	private $arr_smtp_secure = [0 => "false", 1 => "tls", 2 => "ssl"];

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
		$this->ffm_user = $ctl->db("user", "user");
		$this->ffm_setting = $ctl->db("setting", "setting");
	}

	function page(Controller $ctl) {		
		//ログイン画面を表示

		$ctl->display("login.tpl");
	}
	
	function make_new_account(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->show_notification_text($ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$locale_code = $this->normalize_locale_code($pending["locale_code"] ?? "", $framework_language_code);
		$project_release_code = trim((string) ($pending["project_release_code"] ?? ""));
		$release_api_key = trim((string) ($pending["release_api_key"] ?? ""));
		$release_api_secret = trim((string) ($pending["release_api_secret"] ?? ""));
		$smtp_from = trim((string) ($pending["smtp_from"] ?? ""));
		$smtp_server = trim((string) ($pending["smtp_server"] ?? ""));
		$smtp_port = trim((string) ($pending["smtp_port"] ?? ""));
		$smtp_user = trim((string) ($pending["smtp_user"] ?? ""));
		$smtp_password = (string) ($pending["smtp_password"] ?? "");
		$smtp_secure = $this->normalize_smtp_secure($pending["smtp_secure"] ?? 0);
		$smtp_email_test = trim((string) ($pending["smtp_email_test"] ?? ""));
		
		$login_id = (string) $pending["login_id"];
		$password = (string) $pending["password"];
		
		$user = array();
		$user["login_id"] = $login_id;
		$user["password"] = $this->hash_password($password);
		$user["name"] = "Admin";
		$user["type"] = 0;
		$user["email"] = "";
		$this->ffm_user->insert($user);

		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		$setting["id"] = $setting["id"] ?? 1;
		$setting["framework_language_code"] = $framework_language_code;
		$setting["locale_code"] = $locale_code;
		$setting["project_release_code"] = $project_release_code;
		$setting["release_api_key"] = $release_api_key;
		$setting["release_api_secret"] = $release_api_secret;
		$setting["smtp_from"] = $smtp_from;
		$setting["smtp_server"] = $smtp_server;
		$setting["smtp_port"] = $smtp_port;
		$setting["smtp_user"] = $smtp_user;
		$setting["smtp_password"] = $smtp_password;
		$setting["smtp_secure"] = $smtp_secure;
		$setting["smtp_email_test"] = $smtp_email_test;
		$setting["lang_default"] = I18nSimple::get_legacy_lang_code_from_setting($setting);
		$setting_list = $this->ffm_setting->select("id", $setting["id"]);
		if (count($setting_list) === 0) {
			$this->ffm_setting->insert($setting);
			$ctl->set_session("setting", $setting);
		} else {
			$ctl->save_setting($setting);
		}
		
		// Make .htaccess
		$scriptName = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
		$directoryPath = pathinfo($scriptName, PATHINFO_DIRNAME);
		if(endsWith($directoryPath, "/fbp")){
			$directoryPath = substr($directoryPath,0, strlen($directoryPath)-4);
		}
		if($directoryPath === "/" || $directoryPath === "."){
			$directoryPath = "";
		}
		$template = file_get_contents(dirname(__FILE__) . "/../setting/Templates/htaccess.tpl");
		$template = str_replace('{$class}', "login", $template);
		$template = str_replace('{$function}', "page", $template);
		$template = str_replace('{$subpath}',$directoryPath,$template);
		$template = str_replace('{$default_class_name}',"",$template);
		$template = str_replace('{$ssl}',"",$template);	
		file_put_contents(dirname(__FILE__) . "/../../../.htaccess", $template);

		$ctl->set_session($this->pending_account_session_key, null);
		$ctl->show_notification_text($ctl->t("login.account_created"));
		$ctl->close_all_dialog();
	}

	function make_new_account_next(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending)) {
			$pending = [];
		}

		$post = $ctl->POST();
		$framework_language_code = $this->normalize_framework_language_code((string) ($post["framework_language_code"] ?? "en"));
		$locale_code = $this->normalize_locale_code($post["locale_code"] ?? "", $framework_language_code);

		$pending["framework_language_code"] = $framework_language_code;
		$pending["locale_code"] = $locale_code;
		$ctl->set_session($this->pending_account_session_key, $pending);

		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("login_id", (string) ($pending["login_id"] ?? ""));
		$ctl->assign("password", (string) ($pending["password"] ?? ""));
		$ctl->show_multi_dialog("new_account", "new_account.tpl", $ctl->t("login.dialog.make_new_account", [], $framework_language_code));
	}

	function make_new_account_project_release_code(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending)) {
			$pending = [];
		}
		if (empty($pending["framework_language_code"]) || empty($pending["locale_code"])) {
			$ctl->res_error_message("login_id", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$post = $ctl->POST();
		$login_id = (string) ($post["login_id"] ?? "");
		$password = (string) ($post["password"] ?? "");

		$this->validate_new_account_credentials($ctl, $login_id, $password);
		if($ctl->count_res_error_message()>0){
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$pending["login_id"] = $login_id;
		$pending["password"] = $password;
		$ctl->set_session($this->pending_account_session_key, $pending);

		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		$pending["project_release_code"] = (string) ($pending["project_release_code"] ?? ($setting["project_release_code"] ?? ""));
		$ctl->set_session($this->pending_account_session_key, $pending);
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("project_release_code", (string) ($pending["project_release_code"] ?? ""));
		$ctl->show_multi_dialog("new_account", "new_account_project_release_code.tpl", $ctl->t("setting.project_release_code", [], $framework_language_code));
	}

	function make_new_account_release_api(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->res_error_message("project_release_code", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$post = $ctl->POST();
		$pending["project_release_code"] = trim((string) ($post["project_release_code"] ?? ""));
		$this->validate_project_release_code($ctl, $pending["project_release_code"]);
		if($ctl->count_res_error_message()>0){
			return;
		}
		$ctl->set_session($this->pending_account_session_key, $pending);

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("release_api_key", (string) ($pending["release_api_key"] ?? ($setting["release_api_key"] ?? "")));
		$ctl->assign("release_api_secret", (string) ($pending["release_api_secret"] ?? ($setting["release_api_secret"] ?? "")));
		$ctl->show_multi_dialog("new_account", "new_account_release_api.tpl", $ctl->t("setting.release_api_hmac", [], $framework_language_code));
	}

	function make_new_account_mail_server(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->res_error_message("release_api_key", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$post = $ctl->POST();
		$pending["release_api_key"] = trim((string) ($post["release_api_key"] ?? ""));
		$pending["release_api_secret"] = trim((string) ($post["release_api_secret"] ?? ""));
		$ctl->set_session($this->pending_account_session_key, $pending);

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$this->assign_mail_server_dialog($ctl, $pending, $framework_language_code);
		$ctl->show_multi_dialog("new_account", "new_account_mail_server.tpl", $ctl->t("login.mail_server_setting_title", [], $framework_language_code));
	}

	function make_new_account_skip_release_api(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->show_notification_text($ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$pending["release_api_key"] = "";
		$pending["release_api_secret"] = "";
		$ctl->set_session($this->pending_account_session_key, $pending);

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$this->assign_mail_server_dialog($ctl, $pending, $framework_language_code);
		$ctl->show_multi_dialog("new_account", "new_account_mail_server.tpl", $ctl->t("login.mail_server_setting_title", [], $framework_language_code));
	}

	function make_new_account_confirm(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->res_error_message("smtp_from", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$post = $ctl->POST();
		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		$pending["smtp_from"] = trim((string) ($post["smtp_from"] ?? ""));
		$pending["smtp_server"] = trim((string) ($post["smtp_server"] ?? ""));
		$pending["smtp_port"] = trim((string) ($post["smtp_port"] ?? ""));
		$pending["smtp_user"] = trim((string) ($post["smtp_user"] ?? ""));
		$posted_smtp_password = (string) ($post["smtp_password"] ?? "");
		$pending["smtp_password"] = ($posted_smtp_password === "") ? (string) ($setting["smtp_password"] ?? "") : $posted_smtp_password;
		$pending["smtp_secure"] = $this->normalize_smtp_secure($post["smtp_secure"] ?? 0);
		$pending["smtp_email_test"] = trim((string) ($post["smtp_email_test"] ?? ""));
		$ctl->set_session($this->pending_account_session_key, $pending);

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$this->show_new_account_confirm_dialog($ctl, $pending, $framework_language_code);
	}

	function make_new_account_confirm_skip_mail_server(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->res_error_message("smtp_from", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$pending["smtp_from"] = "";
		$pending["smtp_server"] = "";
		$pending["smtp_port"] = "";
		$pending["smtp_user"] = "";
		$pending["smtp_password"] = "";
		$pending["smtp_secure"] = 0;
		$pending["smtp_email_test"] = "";
		$ctl->set_session($this->pending_account_session_key, $pending);

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$this->show_new_account_confirm_dialog($ctl, $pending, $framework_language_code);
	}

	function make_new_account_back(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending)) {
			$pending = [];
		}
		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}
		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? ($setting["framework_language_code"] ?? "en")));
		$locale_code = $this->normalize_locale_code($pending["locale_code"] ?? ($setting["locale_code"] ?? ""), $framework_language_code);

		$ctl->assign("framework_language_code", $framework_language_code);
		$ctl->assign("locale_code", $locale_code);
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("arr_framework_language_code", I18nSimple::get_language_options());
		$ctl->assign("arr_locale_code", I18nSimple::get_locale_options());
		$ctl->assign("locale_option_map_json", json_encode($this->get_locale_option_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$ctl->show_multi_dialog("new_account", "new_account_locale.tpl", $ctl->t("login.dialog.language_locale", [], $framework_language_code));
	}

	function make_new_account_project_release_code_back(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["framework_language_code"]) || empty($pending["locale_code"])) {
			$ctl->res_error_message("login_id", $ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("login_id", (string) ($pending["login_id"] ?? ""));
		$ctl->assign("password", (string) ($pending["password"] ?? ""));
		$ctl->show_multi_dialog("new_account", "new_account.tpl", $ctl->t("login.dialog.make_new_account", [], $framework_language_code));
	}

	function make_new_account_mail_server_back(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->show_notification_text($ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("release_api_key", (string) ($pending["release_api_key"] ?? ""));
		$ctl->assign("release_api_secret", (string) ($pending["release_api_secret"] ?? ""));
		$ctl->show_multi_dialog("new_account", "new_account_release_api.tpl", $ctl->t("setting.release_api_hmac", [], $framework_language_code));
	}

	function make_new_account_release_api_back(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["framework_language_code"]) || empty($pending["locale_code"])) {
			$ctl->show_notification_text($ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("project_release_code", (string) ($pending["project_release_code"] ?? ""));
		$ctl->show_multi_dialog("new_account", "new_account_project_release_code.tpl", $ctl->t("setting.project_release_code", [], $framework_language_code));
	}

	function make_new_account_confirm_back(Controller $ctl){
		$pending = $ctl->get_session($this->pending_account_session_key);
		if (!is_array($pending) || empty($pending["login_id"]) || empty($pending["password"])) {
			$ctl->show_notification_text($ctl->t("login.validation.initial_account_session_expired"));
			return;
		}

		$framework_language_code = $this->normalize_framework_language_code((string) ($pending["framework_language_code"] ?? "en"));
		$this->assign_mail_server_dialog($ctl, $pending, $framework_language_code);
		$ctl->show_multi_dialog("new_account", "new_account_mail_server.tpl", $ctl->t("login.mail_server_setting_title", [], $framework_language_code));
	}
	
	function close(Controller $ctl){
		$ctl->close_all_dialog();
	}

	function login_form(Controller $ctl) {

		//ユーザが登録されているか確認
		$list = $this->ffm_user->select("type",0);
		if(count($list) == 0){
			$setting = $ctl->get_setting();
			if (!is_array($setting)) {
				$setting = [];
			}
			$framework_language_code = $this->normalize_framework_language_code((string) ($setting["framework_language_code"] ?? "en"));
			$locale_code = $this->normalize_locale_code($setting["locale_code"] ?? "", $framework_language_code);

			$ctl->assign("framework_language_code", $framework_language_code);
			$ctl->assign("locale_code", $locale_code);
			$ctl->assign("dialog_lang", $framework_language_code);
			$ctl->assign("arr_framework_language_code", I18nSimple::get_language_options());
			$ctl->assign("arr_locale_code", I18nSimple::get_locale_options());
			$ctl->assign("locale_option_map_json", json_encode($this->get_locale_option_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$ctl->show_multi_dialog("new_account", "new_account_locale.tpl", $ctl->t("login.dialog.language_locale", [], $framework_language_code));
			
		}else{
			$ctl->assign("user", null);
		}
		
		// Cookie
		if(!empty($_COOKIE["login_id"])){
			$cookie_login_id = $_COOKIE["login_id"];
		}else{
			$cookie_login_id = "";
		}
		if(!empty($_COOKIE[$this->remember_me_cookie_name])){
			$cookie_remember_me = $_COOKIE[$this->remember_me_cookie_name];
		}else{
			$cookie_remember_me = "";
		}
		if(!empty($_COOKIE["login_status"])){
			$cookie_login_status = $_COOKIE["login_status"];
		}else{
			$cookie_login_status = "";
		}

		// Logo check
		if ($ctl->is_saved_file("login_logo")) {
			$ctl->assign("flg_login_logo", true);
		}else{
			$ctl->assign("flg_login_logo", false);
		}

		// -------------
		// Cookieがログイン情報を持っている場合、チェックしてログイン処理
		// 持っていない場合は、ログインフォームを表示
		// -------------
		$login_id = $ctl->decrypt($cookie_login_id);
		$password = "";
		$user = null;

		if ($cookie_login_status == "logined" && $cookie_remember_me !== "") {
			$remember_login_id = $this->decode_remember_me_cookie($ctl, $cookie_remember_me);
			if ($remember_login_id !== "") {
				$user = $this->find_user_by_login_id($remember_login_id);
				if (is_array($user)) {
					$login_id = $remember_login_id;
					$password = "";
				}
			}
		}
		if (is_array($user)) {
			$this->login_ok($ctl, $user, $login_id, $password);
		} else {
			// デフォルト表示
			$ctl->assign("login_id", $login_id);
			$ctl->assign("password", "");
			
			$ctl->reload_area("#form", $ctl->fetch("form.tpl"));
		}
	}

	function check(Controller $ctl) {
		$login_id = $ctl->POST("login_id");
		$password = $ctl->POST("password");

		$user = $this->find_user_by_credentials($login_id, $password);
		if (is_array($user)) {
			$this->login_ok($ctl, $user, $login_id, $password);
		} else {
			$ctl->assign("login_id", $login_id);
			$ctl->assign("err_password", $ctl->t("validation.login.failed"));
			
			if($ctl->POST("_call_from") == "appcon"){
				$ctl->reload_area("#form", $ctl->fetch("form.tpl"));
			}else{
				$ctl->display("login.tpl");
			}
		}
	}

	function login_ok(Controller $ctl, $user, $login_id, $password) {
		
		$ctl->set_session("login", true);

		foreach ($user as $key => $val) {
			if ($key == "id") {
				$ctl->set_session("user_id", $user["id"]);
			} else {
				$ctl->set_session($key, $val);
			}
		}

		//admin判定
		$ctl->set_session("app_admin", ((int) ($user["type"] ?? -1) === 0));

		//---------------
		// Cookie処理
		//---------------
		app_setcookie("login_id", $ctl->encrypt($login_id), strtotime('+30 days'));
		app_setcookie("login_status", "logined", strtotime('+30 days'));
		$this->set_remember_me_cookie($ctl, $login_id);
		
		if ((int) ($user["flg_password_change_required"] ?? 0) === 1) {
			$ctl->res_redirect("app.php?class=password_reset&function=force_page");
			return;
		}
		
		$ctl->res_redirect("app.php?class=base");
	}

	function logo(Controller $ctl) {
		$ctl->res_saved_image("login_logo");
	}
	
	function logo_default(Controller $ctl) {
		$ctl->res_image("images","login_logo.png");
	}

	function logout(Controller $ctl) {
		$windowcode = $ctl->get_windowcode();
		$_SESSION[$windowcode] = [];
		app_setcookie("login_id", "",time() - 3600);
		app_setcookie("password", "",time() - 3600);
		app_setcookie($this->remember_me_cookie_name, "",time() - 3600);
		app_setcookie("login_status", "",time() - 3600);
		$ctl->res_redirect("app.php?class=login");
	}

	private function find_user_by_credentials($login_id, $password) {
		$user_list = $this->ffm_user->select(["login_id", "status"], [$login_id, 0], true);
		if (count($user_list) !== 1) {
			return null;
		}

		$user = $user_list[0];
		$stored_password = (string) ($user["password"] ?? "");
		if ($stored_password === "" || $password === "") {
			return null;
		}

		$info = password_get_info($stored_password);
		if ((int) ($info["algo"] ?? 0) !== 0) {
			if (password_verify($password, $stored_password)) {
				return $user;
			}
			return null;
		}

		if (!hash_equals($stored_password, (string) $password)) {
			return null;
		}

		$user["password"] = $this->hash_password((string) $password);
		$this->ffm_user->update($user);
		return $user;
	}

	private function find_user_by_login_id($login_id) {
		$user_list = $this->ffm_user->select(["login_id", "status"], [$login_id, 0], true);
		if (count($user_list) !== 1) {
			return null;
		}
		return $user_list[0];
	}

	private function hash_password($password) {
		$hash = password_hash((string) $password, PASSWORD_DEFAULT);
		if (!is_string($hash) || $hash === "") {
			throw new Exception("Failed to hash password.");
		}
		return $hash;
	}

	private function validate_new_account_credentials(Controller $ctl, string $login_id, string $password) {
		if (!preg_match('/^[a-zA-Z0-9@._\-!#$%&*?]+$/', $login_id)) {
		    $ctl->res_error_message("login_id", $ctl->t("login.validation.login_id_format"));
		}

		if (!preg_match('/^[a-zA-Z0-9@._\-!#$%&*?]+$/', $password)) {
		    $ctl->res_error_message("password", $ctl->t("login.validation.password_format"));
		}
	}

	private function validate_project_release_code(Controller $ctl, string $project_release_code) {
		if ($project_release_code === "") {
			return;
		}
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $project_release_code)) {
			$ctl->res_error_message("project_release_code", $ctl->t("login.validation.project_release_code_format"));
		}
	}

	private function normalize_smtp_secure($value): int {
		$normalized = (int) $value;
		if (!array_key_exists($normalized, $this->arr_smtp_secure)) {
			return 0;
		}
		return $normalized;
	}

	private function normalize_framework_language_code(string $code): string {
		$code = strtolower(trim($code));
		if (!preg_match('/^[a-z]{2}$/', $code)) {
			return "en";
		}
		return $code;
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

	private function assign_mail_server_dialog(Controller $ctl, array $pending, string $framework_language_code): void {
		$setting = $ctl->get_setting();
		if (!is_array($setting)) {
			$setting = [];
		}

		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("smtp_from", (string) ($pending["smtp_from"] ?? ($setting["smtp_from"] ?? "")));
		$ctl->assign("smtp_server", (string) ($pending["smtp_server"] ?? ($setting["smtp_server"] ?? "")));
		$ctl->assign("smtp_port", (string) ($pending["smtp_port"] ?? ($setting["smtp_port"] ?? "")));
		$ctl->assign("smtp_user", (string) ($pending["smtp_user"] ?? ($setting["smtp_user"] ?? "")));
		$ctl->assign("smtp_password", (string) ($pending["smtp_password"] ?? ""));
		$existing_smtp_password = (string) ($setting["smtp_password"] ?? "");
		$ctl->assign("smtp_password_placeholder", $existing_smtp_password === "" ? "" : $ctl->t("common.configured"));
		$ctl->assign("smtp_secure", $this->normalize_smtp_secure($pending["smtp_secure"] ?? ($setting["smtp_secure"] ?? 0)));
		$ctl->assign("arr_smtp_secure", $this->arr_smtp_secure);
	}

	private function show_new_account_confirm_dialog(Controller $ctl, array $pending, string $framework_language_code): void {
		$locale_options = I18nSimple::get_locale_options();
		$language_options = I18nSimple::get_language_options();

		$ctl->assign("dialog_lang", $framework_language_code);
		$ctl->assign("confirm_items", [
			["label_key" => "setting.framework_language_code", "value" => $language_options[$framework_language_code] ?? $framework_language_code],
			["label_key" => "setting.locale_code", "value" => $locale_options[$pending["locale_code"] ?? ""] ?? (string) ($pending["locale_code"] ?? "")],
			["label_key" => "setting.project_release_code", "value" => (string) ($pending["project_release_code"] ?? "")],
			["label_key" => "setting.release_api_key", "value" => ((string) ($pending["release_api_key"] ?? "") === "") ? "" : "********"],
			["label_key" => "setting.release_api_secret", "value" => ((string) ($pending["release_api_secret"] ?? "") === "") ? "" : "********"],
			["label_key" => "setting.mail_address_from", "value" => (string) ($pending["smtp_from"] ?? "")],
			["label_key" => "setting.mail_server", "value" => (string) ($pending["smtp_server"] ?? "")],
			["label_key" => "setting.mail_port", "value" => (string) ($pending["smtp_port"] ?? "")],
			["label_key" => "setting.mail_user", "value" => (string) ($pending["smtp_user"] ?? "")],
			["label_key" => "setting.mail_password", "value" => ((string) ($pending["smtp_password"] ?? "") === "") ? "" : "********"],
			["label_key" => "setting.smtp_secure", "value" => $this->arr_smtp_secure[$this->normalize_smtp_secure($pending["smtp_secure"] ?? 0)] ?? ""],
		]);
		$ctl->show_multi_dialog("new_account", "new_account_confirm.tpl", $ctl->t("login.dialog.confirm_new_account", [], $framework_language_code));
	}

	private function set_remember_me_cookie(Controller $ctl, $login_id) {
		$exp = time() + (int) $this->remember_me_ttl;
		$ua = (string) ($_SERVER["HTTP_USER_AGENT"] ?? "");
		$payload = json_encode([
			"login_id" => (string) $login_id,
			"exp" => $exp,
			"ua" => hash("sha256", $ua),
		], JSON_UNESCAPED_SLASHES);
		if (!is_string($payload) || $payload === "") {
			return;
		}
		$token = $ctl->encrypt($payload);
		app_setcookie($this->remember_me_cookie_name, $token, $exp);
	}

	private function decode_remember_me_cookie(Controller $ctl, $cookie) {
		$decoded = $ctl->decrypt((string) $cookie);
		if (!is_string($decoded) || $decoded === "") {
			return "";
		}
		$data = json_decode($decoded, true);
		if (!is_array($data)) {
			return "";
		}
		$exp = (int) ($data["exp"] ?? 0);
		if ($exp <= time()) {
			return "";
		}
		$login_id = (string) ($data["login_id"] ?? "");
		if ($login_id === "") {
			return "";
		}
		$ua_hash = (string) ($data["ua"] ?? "");
		$ua = (string) ($_SERVER["HTTP_USER_AGENT"] ?? "");
		if ($ua_hash === "" || !hash_equals($ua_hash, hash("sha256", $ua))) {
			return "";
		}
		return $login_id;
	}
}
