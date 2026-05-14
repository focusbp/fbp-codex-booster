<?php

class password_reset {

	private $ffm_user;
	private $remember_me_cookie_name = "remember_me";
	private $remember_me_ttl = 2592000; // 30 days

	function __construct(Controller $ctl) {
		$called_function = (string) $ctl->get_called_function();
		if ($called_function === "token_page" || $called_function === "token_reset_exe") {
			$ctl->set_check_login(false);
		}
		$this->ffm_user = $ctl->db("user", "user");
	}

	function page(Controller $ctl) {
		$ctl->show_multi_dialog("password_reset", "form.tpl", $ctl->t("password_reset.dialog.reset"), 500, true, true);
	}

	function force_page(Controller $ctl) {
		$user_id = (int) $ctl->get_session("user_id");
		$data = $this->ffm_user->get($user_id);
		$ctl->assign("data", $data);
		$ctl->display("force_page.tpl");
	}

	function token_page(Controller $ctl) {
		$token = trim((string) ($ctl->GET("token") ?? $ctl->POST("token")));
		$data = $this->find_user_by_reset_token($token);
		if (!is_array($data)) {
			$ctl->assign("err_token", $ctl->t("password_reset.validation.invalid_or_expired"));
			$ctl->display("token_page.tpl");
			return;
		}
		$ctl->assign("data", $data);
		$ctl->assign("token", $token);
		$ctl->display("token_page.tpl");
	}

	function reset_exe(Controller $ctl) {
		$password = (string) $ctl->POST("password");
		$password_confirm = (string) $ctl->POST("password_confirm");
		if ($password === "") {
			$ctl->res_error_message("password", $ctl->t("password_reset.validation.password_required"));
			return;
		}
		if ($password !== $password_confirm) {
			$ctl->res_error_message("password_confirm", $ctl->t("password_reset.validation.password_confirm_mismatch"));
			return;
		}

		$user_id = (int) $ctl->get_session("user_id");
		$data = $this->ffm_user->get($user_id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->res_error_message("password", $ctl->t("user.validation.user_not_found"));
			return;
		}

		$data["password"] = $this->hash_password($password);
		if (array_key_exists("flg_password_change_required", $data)) {
			$data["flg_password_change_required"] = 0;
		}
		if (array_key_exists("password_reset_token_hash", $data)) {
			$data["password_reset_token_hash"] = "";
		}
		if (array_key_exists("password_reset_token_expires_at", $data)) {
			$data["password_reset_token_expires_at"] = 0;
		}
		if (array_key_exists("password_reset_token_sent_at", $data)) {
			$data["password_reset_token_sent_at"] = 0;
		}
		$this->ffm_user->update($data);
		$ctl->close_multi_dialog("password_reset");
	}

	function force_reset_exe(Controller $ctl) {
		$password = (string) $ctl->POST("password");
		$password_confirm = (string) $ctl->POST("password_confirm");
		if ($password === "") {
			$ctl->assign("err_password", $ctl->t("password_reset.validation.password_required"));
			$this->force_page($ctl);
			return;
		}
		if ($password !== $password_confirm) {
			$ctl->assign("err_password_confirm", $ctl->t("password_reset.validation.password_confirm_mismatch"));
			$this->force_page($ctl);
			return;
		}

		$user_id = (int) $ctl->get_session("user_id");
		$data = $this->ffm_user->get($user_id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->assign("err_password", $ctl->t("user.validation.user_not_found"));
			$this->force_page($ctl);
			return;
		}

		$data["password"] = $this->hash_password($password);
		if (array_key_exists("flg_password_change_required", $data)) {
			$data["flg_password_change_required"] = 0;
		}
		if (array_key_exists("password_reset_token_hash", $data)) {
			$data["password_reset_token_hash"] = "";
		}
		if (array_key_exists("password_reset_token_expires_at", $data)) {
			$data["password_reset_token_expires_at"] = 0;
		}
		if (array_key_exists("password_reset_token_sent_at", $data)) {
			$data["password_reset_token_sent_at"] = 0;
		}
		$this->ffm_user->update($data);
		$ctl->res_redirect("app.php?class=base");
	}

	function token_reset_exe(Controller $ctl) {
		$password = (string) $ctl->POST("password");
		$password_confirm = (string) $ctl->POST("password_confirm");
		$token = trim((string) $ctl->POST("token"));
		if ($password === "") {
			$ctl->assign("err_password", $ctl->t("password_reset.validation.password_required"));
			$this->token_page($ctl);
			return;
		}
		if ($password !== $password_confirm) {
			$ctl->assign("err_password_confirm", $ctl->t("password_reset.validation.password_confirm_mismatch"));
			$this->token_page($ctl);
			return;
		}

		$data = $this->find_user_by_reset_token($token);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->assign("err_token", $ctl->t("password_reset.validation.invalid_or_expired"));
			$this->token_page($ctl);
			return;
		}

		$data["password"] = $this->hash_password($password);
		if (array_key_exists("flg_password_change_required", $data)) {
			$data["flg_password_change_required"] = 0;
		}
		$data["password_reset_token_hash"] = "";
		$data["password_reset_token_expires_at"] = 0;
		$data["password_reset_token_sent_at"] = 0;
		$this->ffm_user->update($data);
		$this->login_as_reset_user($ctl, $data);
		$ctl->res_redirect($ctl->get_APP_URL("base"));
	}

	private function hash_password($password) {
		$hash = password_hash((string) $password, PASSWORD_DEFAULT);
		if (!is_string($hash) || $hash === "") {
			throw new Exception("Failed to hash password.");
		}
		return $hash;
	}

	private function find_user_by_reset_token(string $token): ?array {
		if ($token === "") {
			return null;
		}
		$token_hash = hash("sha256", $token);
		$list = $this->ffm_user->select("password_reset_token_hash", $token_hash, true);
		if (count($list) !== 1) {
			return null;
		}
		$data = $list[0];
		$expires_at = (int) ($data["password_reset_token_expires_at"] ?? 0);
		if ($expires_at <= time()) {
			return null;
		}
		return $data;
	}

	private function login_as_reset_user(Controller $ctl, array $user): void {
		$this->clear_auth_state($ctl);
		$ctl->set_session("login", true);

		foreach ($user as $key => $val) {
			if ($key === "id") {
				$ctl->set_session("user_id", $user["id"]);
			} else {
				$ctl->set_session($key, $val);
			}
		}

		$ctl->set_session("app_admin", ((int) ($user["type"] ?? -1) === 0));

		$login_id = (string) ($user["login_id"] ?? "");
		if ($login_id !== "") {
			$exp = time() + (int) $this->remember_me_ttl;
			app_setcookie("login_id", $ctl->encrypt($login_id), $exp);
			app_setcookie("login_status", "logined", $exp);
			$this->set_remember_me_cookie($ctl, $login_id, $exp);
		}
	}

	private function clear_auth_state(Controller $ctl): void {
		$windowcode = $ctl->get_windowcode();
		$_SESSION[$windowcode] = [];
		app_setcookie("login_id", "", time() - 3600);
		app_setcookie("password", "", time() - 3600);
		app_setcookie($this->remember_me_cookie_name, "", time() - 3600);
		app_setcookie("login_status", "", time() - 3600);
	}

	private function set_remember_me_cookie(Controller $ctl, string $login_id, int $exp): void {
		$ua = (string) ($_SERVER["HTTP_USER_AGENT"] ?? "");
		$payload = json_encode([
			"login_id" => $login_id,
			"exp" => $exp,
			"ua" => hash("sha256", $ua),
		], JSON_UNESCAPED_SLASHES);
		if (!is_string($payload) || $payload === "") {
			return;
		}
		$token = $ctl->encrypt($payload);
		app_setcookie($this->remember_me_cookie_name, $token, $exp);
	}
}
