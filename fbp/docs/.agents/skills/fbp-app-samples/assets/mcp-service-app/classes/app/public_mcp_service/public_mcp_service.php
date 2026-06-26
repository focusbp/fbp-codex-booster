<?php

class public_mcp_service {
	private $sessionKey = "mcp_service_public_member_id";

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function register(Controller $ctl) {
		$fromMcp = $this->isMcpRegistration($ctl);
		$this->assignCommon($ctl);
		$ctl->assign("mode", "register");
		$ctl->assign("form", ["display_name" => "", "email" => ""]);
		$ctl->assign("from_mcp", $fromMcp ? 1 : 0);
		$ctl->assign("message", "");
		$this->show($ctl);
	}

	function portal(Controller $ctl) {
		$member = $this->currentMember($ctl);
		if (empty($member)) {
			$ctl->res_redirect($ctl->get_APP_URL("public_mcp_service", "register"));
			return;
		}
		$this->assignCommon($ctl);
		$ctl->assign("mode", "portal");
		$ctl->assign("member", $member);
		$this->show($ctl);
	}

	function logout(Controller $ctl) {
		$ctl->set_session($this->sessionKey, "");
		$ctl->res_redirect($ctl->get_APP_URL("public_mcp_service", "register"));
	}

	function register_save(Controller $ctl) {
		$fromMcp = $this->isMcpRegistration($ctl);
		$displayName = mb_substr(trim((string) ($ctl->POST("display_name") ?? "")), 0, 120);
		$email = $this->normalizeEmail((string) ($ctl->POST("email") ?? ""));
		$password = (string) ($ctl->POST("password") ?? "");
		$passwordConfirm = (string) ($ctl->POST("password_confirm") ?? "");
		$message = "";

		if ($displayName === "") {
			$message = "Name is required.";
		} elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$message = "Email is invalid.";
		} elseif ($password === "" || mb_strlen($password) < 8) {
			$message = "Password must be at least 8 characters.";
		} elseif ($password !== $passwordConfirm) {
			$message = "Password confirmation does not match.";
		} elseif (!empty($this->findMemberByEmail($ctl, $email))) {
			$message = "This email is already registered.";
		}

		if ($message !== "") {
			$this->assignCommon($ctl);
			$ctl->assign("mode", "register");
			$ctl->assign("form", ["display_name" => $displayName, "email" => $email]);
			$ctl->assign("from_mcp", $fromMcp ? 1 : 0);
			$ctl->assign("message", $message);
			$this->show($ctl);
			return;
		}

		$insert = [
			"display_name" => $displayName,
			"email" => $email,
			"password_hash" => password_hash($password, PASSWORD_DEFAULT),
			"status" => "1",
			"subject_type" => "service_member",
			"subject_id" => 0,
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		];
		$id = $ctl->db("service_member")->insert($insert);
		$member = $ctl->db("service_member")->get((int) $id);
		if (is_array($member)) {
			$member["subject_id"] = (int) $id;
			$ctl->db("service_member")->update($member);
		}
		if ($fromMcp) {
			$ctl->set_session($this->sessionKey, "");
			$ctl->res_redirect($ctl->get_APP_URL("public_mcp_service_login", "login") . "?email=" . rawurlencode($email));
			return;
		}
		$ctl->set_session($this->sessionKey, (int) $id);
		$ctl->res_redirect($ctl->get_APP_URL("public_mcp_service", "portal"));
	}

	private function show(Controller $ctl): void {
		$ctl->show_public_pages("page.tpl", "_head.tpl", null, null, ["css_mode" => "minimal"]);
	}

	private function assignCommon(Controller $ctl): void {
		$ctl->assign("page_title", "MCP Service Sample");
		$ctl->assign("register_action_url", $ctl->get_APP_URL("public_mcp_service", "register_save"));
		$ctl->assign("register_url", $ctl->get_APP_URL("public_mcp_service", "register"));
		$ctl->assign("portal_url", $ctl->get_APP_URL("public_mcp_service", "portal"));
		$ctl->assign("logout_url", $ctl->get_APP_URL("public_mcp_service", "logout"));
		$ctl->assign("mcp_login_url", $ctl->get_APP_URL("public_mcp_service_login", "login"));
		$ctl->assign("mcp_endpoint_url", $ctl->get_APP_URL("mcp_server", "rpc") . "?server=mcp_service");
	}

	private function currentMember(Controller $ctl): array {
		$memberId = (int) ($ctl->get_session($this->sessionKey) ?? 0);
		if ($memberId <= 0) {
			return [];
		}
		$member = $ctl->db("service_member")->get($memberId);
		if (!is_array($member) || (string) ($member["status"] ?? "1") !== "1") {
			$ctl->set_session($this->sessionKey, "");
			return [];
		}
		return $member;
	}

	private function findMemberByEmail(Controller $ctl, string $email): array {
		$email = $this->normalizeEmail($email);
		if ($email === "") {
			return [];
		}
		foreach ($ctl->db("service_member")->getall("id", SORT_ASC) as $member) {
			if ($this->normalizeEmail((string) ($member["email"] ?? "")) === $email) {
				return $member;
			}
		}
		return [];
	}

	private function normalizeEmail(string $email): string {
		return strtolower(trim($email));
	}

	private function isMcpRegistration(Controller $ctl): bool {
		$value = strtolower(trim((string) ($ctl->POST("from_mcp") ?? $ctl->GET("from") ?? "")));
		return $value === "1" || $value === "mcp";
	}
}
