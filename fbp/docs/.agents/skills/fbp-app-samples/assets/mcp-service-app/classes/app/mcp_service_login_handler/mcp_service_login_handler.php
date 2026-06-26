<?php

class mcp_service_login_handler implements McpLoginHandlerInterface {
	public function subjectType(): string {
		return "service_member";
	}

	public function sessionKey(): string {
		return "mcp_service_member_id";
	}

	public function currentSubject(Controller $ctl): ?McpSubject {
		$member_id = (int) ($ctl->get_session($this->sessionKey()) ?? 0);
		if ($member_id <= 0) {
			return null;
		}
		$member = $ctl->db("service_member")->get($member_id);
		if (!$this->isEnabledMember($member)) {
			$this->clearMcpSession($ctl);
			return null;
		}
		return new McpSubject($this->subjectType(), $member_id, $this->memberLabel($member), (int) ($member["fbp_user_id"] ?? 0));
	}

	public function authenticate(Controller $ctl, array $credentials): McpSubject {
		$email = $this->normalizeEmail((string) ($credentials["email"] ?? ""));
		$password = (string) ($credentials["password"] ?? "");
		if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
			throw new Exception("Email or password is invalid.");
		}
		$member = $this->findMemberByEmail($ctl, $email);
		$hash = (string) ($member["password_hash"] ?? "");
		if (!$this->isEnabledMember($member) || $hash === "" || !password_verify($password, $hash)) {
			throw new Exception("Email or password is invalid.");
		}
		$member["mcp_last_login_at"] = date("Y-m-d H:i:s");
		$member["updated_at"] = date("Y-m-d H:i:s");
		$ctl->db("service_member")->update($member);
		return new McpSubject($this->subjectType(), (int) $member["id"], $this->memberLabel($member), (int) ($member["fbp_user_id"] ?? 0));
	}

	public function setMcpSession(Controller $ctl, McpSubject $subject): void {
		$ctl->set_session($this->sessionKey(), $subject->id() > 0 ? $subject->id() : "");
	}

	public function clearMcpSession(Controller $ctl): void {
		$ctl->set_session($this->sessionKey(), "");
	}

	public function normalizeReturnUrl(Controller $ctl, string $returnUrl): string {
		$returnUrl = trim($returnUrl);
		if ($returnUrl === "") {
			return $ctl->get_APP_URL("public_mcp_service_login", "login");
		}
		$appUrl = $ctl->get_APP_URL();
		if ($appUrl !== "" && strpos($returnUrl, $appUrl) === 0) {
			return $returnUrl;
		}
		if (strpos($returnUrl, "mcp_server*authorize") !== false) {
			return $returnUrl;
		}
		return $ctl->get_APP_URL("public_mcp_service_login", "login");
	}

	public function normalizeEmail(string $email): string {
		return strtolower(trim($email));
	}

	public function findMemberByEmail(Controller $ctl, string $email): array {
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

	public function isEnabledMember($member): bool {
		return is_array($member)
			&& (int) ($member["id"] ?? 0) > 0
			&& (string) ($member["status"] ?? "1") === "1";
	}

	public function memberLabel(array $member): string {
		$label = trim((string) ($member["display_name"] ?? ""));
		if ($label === "") {
			$label = trim((string) ($member["email"] ?? ""));
		}
		return $label === "" ? "service_member#" . (int) ($member["id"] ?? 0) : $label;
	}
}
