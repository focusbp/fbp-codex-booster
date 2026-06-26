<?php

class McpSubject {
	private $type;
	private $id;
	private $label;
	private $user_id;

	function __construct(string $type, int $id, string $label = "", int $user_id = 0) {
		$this->type = trim($type) === "" ? "unknown" : trim($type);
		$this->id = $id;
		$this->label = trim($label);
		$this->user_id = $user_id;
	}

	function type(): string {
		return $this->type;
	}

	function id(): int {
		return $this->id;
	}

	function label(): string {
		return $this->label;
	}

	function userId(): int {
		return $this->user_id;
	}

	function toArray(): array {
		return [
			"subject_type" => $this->type,
			"subject_id" => $this->id,
			"subject_label" => $this->label,
			"user_id" => $this->user_id,
		];
	}
}

interface McpSubjectProviderInterface {
	public function subjectType(): string;
	public function currentSubject(Controller $ctl, array $server): ?McpSubject;
	public function loginUrl(Controller $ctl, array $server, string $returnUrl): string;
	public function subjectLabel(Controller $ctl, McpSubject $subject): string;
	public function validateSubject(Controller $ctl, array $server, McpSubject $subject): bool;
	public function onAuthorizeConfirmed(Controller $ctl, array $server, McpSubject $subject, array $oauthParams, string $scope): void;
	public function onTokenRevoked(Controller $ctl, array $server, McpSubject $subject, array $tokenRow): void;
}

interface McpLoginHandlerInterface {
	public function subjectType(): string;
	public function sessionKey(): string;
	public function currentSubject(Controller $ctl): ?McpSubject;
	public function authenticate(Controller $ctl, array $credentials): McpSubject;
	public function setMcpSession(Controller $ctl, McpSubject $subject): void;
	public function clearMcpSession(Controller $ctl): void;
	public function normalizeReturnUrl(Controller $ctl, string $returnUrl): string;
}

class McpFbpUserSubjectProvider implements McpSubjectProviderInterface {
	public function subjectType(): string {
		return "fbp_user";
	}

	public function currentSubject(Controller $ctl, array $server): ?McpSubject {
		if (!$ctl->get_session("login")) {
			return null;
		}
		$user_id = (int) ($ctl->get_session("user_id") ?? 0);
		if ($user_id <= 0) {
			return null;
		}
		$user = $ctl->db("user", "user")->get($user_id);
		if (!is_array($user) || empty($user["id"]) || (int) ($user["status"] ?? 1) !== 0) {
			return null;
		}
		return new McpSubject("fbp_user", (int) $user["id"], $this->userLabel($user), (int) $user["id"]);
	}

	public function loginUrl(Controller $ctl, array $server, string $returnUrl): string {
		$_SESSION["mcp_oauth_authorize_return"] = $returnUrl;
		return $ctl->get_APP_URL("login", "page");
	}

	public function subjectLabel(Controller $ctl, McpSubject $subject): string {
		if ($subject->label() !== "") {
			return $subject->label();
		}
		$user = $ctl->db("user", "user")->get($subject->id());
		return is_array($user) ? $this->userLabel($user) : "";
	}

	public function validateSubject(Controller $ctl, array $server, McpSubject $subject): bool {
		if ($subject->type() !== "fbp_user" || $subject->id() <= 0) {
			return false;
		}
		$user = $ctl->db("user", "user")->get($subject->id());
		return is_array($user) && !empty($user["id"]) && (int) ($user["status"] ?? 1) === 0;
	}

	public function onAuthorizeConfirmed(Controller $ctl, array $server, McpSubject $subject, array $oauthParams, string $scope): void {
	}

	public function onTokenRevoked(Controller $ctl, array $server, McpSubject $subject, array $tokenRow): void {
	}

	private function userLabel(array $user): string {
		$label = trim((string) ($user["name"] ?? ""));
		if ($label === "") {
			$label = trim((string) ($user["login_id"] ?? ""));
		}
		return $label === "" ? "user#" . (int) ($user["id"] ?? 0) : $label;
	}
}
