<?php

class mcp_service_subject_provider implements McpSubjectProviderInterface {
	public function subjectType(): string {
		return "service_member";
	}

	public function currentSubject(Controller $ctl, array $server): ?McpSubject {
		return $this->handler($ctl)->currentSubject($ctl);
	}

	public function loginUrl(Controller $ctl, array $server, string $returnUrl): string {
		$_SESSION["mcp_service_return_url"] = $this->handler($ctl)->normalizeReturnUrl($ctl, $returnUrl);
		return $ctl->get_APP_URL("public_mcp_service_login", "login");
	}

	public function subjectLabel(Controller $ctl, McpSubject $subject): string {
		$member = $ctl->db("service_member")->get($subject->id());
		if (is_array($member) && !empty($member)) {
			return $this->handler($ctl)->memberLabel($member);
		}
		return $subject->label();
	}

	public function validateSubject(Controller $ctl, array $server, McpSubject $subject): bool {
		if ($subject->type() !== $this->subjectType() || $subject->id() <= 0) {
			return false;
		}
		return $this->handler($ctl)->isEnabledMember($ctl->db("service_member")->get($subject->id()));
	}

	public function onAuthorizeConfirmed(Controller $ctl, array $server, McpSubject $subject, array $oauthParams, string $scope): void {
	}

	public function onTokenRevoked(Controller $ctl, array $server, McpSubject $subject, array $tokenRow): void {
	}

	private function handler(Controller $ctl): mcp_service_login_handler {
		if (!class_exists("mcp_service_login_handler", false)) {
			$dir = new Dirs();
			include_once($dir->get_class_dir("mcp_service_login_handler") . "/mcp_service_login_handler.php");
		}
		return new mcp_service_login_handler();
	}
}
