<?php

class public_mcp_service_login {
	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function login(Controller $ctl) {
		$email = strtolower(trim((string) ($ctl->GET("email") ?? "")));
		$this->assign($ctl, $email, "");
		$ctl->show_public_pages("login.tpl", "_head.tpl", null, null, ["css_mode" => "minimal"]);
	}

	function login_password(Controller $ctl) {
		$email = strtolower(trim((string) ($ctl->POST("email") ?? "")));
		$password = (string) ($ctl->POST("password") ?? "");
		try {
			$subject = $this->handler($ctl)->authenticate($ctl, ["email" => $email, "password" => $password]);
			$this->handler($ctl)->setMcpSession($ctl, $subject);
		} catch (Throwable $e) {
			$this->assign($ctl, $email, $e->getMessage());
			$ctl->show_public_pages("login.tpl", "_head.tpl", null, null, ["css_mode" => "minimal"]);
			return;
		}

		$returnUrl = $this->handler($ctl)->normalizeReturnUrl($ctl, (string) ($_SESSION["mcp_service_return_url"] ?? ""));
		$ctl->res_redirect($returnUrl);
	}

	function logout(Controller $ctl) {
		$this->handler($ctl)->clearMcpSession($ctl);
		$ctl->res_redirect($ctl->get_APP_URL("public_mcp_service_login", "login"));
	}

	private function assign(Controller $ctl, string $email, string $message): void {
		$ctl->assign("page_title", "MCP Service Login");
		$ctl->assign("email", $email);
		$ctl->assign("message", $message);
		$ctl->assign("login_action_url", $ctl->get_APP_URL("public_mcp_service_login", "login_password"));
		$ctl->assign("register_url", $ctl->get_APP_URL("public_mcp_service", "register") . "?from=mcp");
	}

	private function handler(Controller $ctl): mcp_service_login_handler {
		if (!class_exists("mcp_service_login_handler", false)) {
			$dir = new Dirs();
			include_once($dir->get_class_dir("mcp_service_login_handler") . "/mcp_service_login_handler.php");
		}
		return new mcp_service_login_handler();
	}
}
