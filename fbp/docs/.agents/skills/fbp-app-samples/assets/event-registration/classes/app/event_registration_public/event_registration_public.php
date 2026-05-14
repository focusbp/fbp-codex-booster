<?php

class event_registration_public {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function page(Controller $ctl): void {
		$this->assignFrame($ctl, "Event Registration");
		$ctl->assign("sessions", $this->publicSessions($ctl));
		$ctl->assign("refresh_url", $ctl->get_APP_URL("event_registration_public", "page"));
		$ctl->show_public_pages("sessions.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function form(Controller $ctl): void {
		$session = $this->sessionFromKey($ctl, (string) ($ctl->GET("session") ?? $ctl->POST("session") ?? ""));
		if (empty($session) || !$this->isRegistrationOpen($ctl, $session)) {
			$this->showError($ctl, "This event session is no longer available.");
			return;
		}
		$this->showForm($ctl, $session, $this->emptyRegistration(), []);
	}

	function save(Controller $ctl): void {
		$sessionKey = (string) ($ctl->POST("session") ?? "");
		$session = $this->sessionFromKey($ctl, $sessionKey);
		if (empty($session) || !$this->isRegistrationOpen($ctl, $session)) {
			$this->showError($ctl, "This event session is no longer available.");
			return;
		}

		$row = [
			"session_id" => (int) ($session["id"] ?? 0),
			"name" => trim((string) ($ctl->POST("name") ?? "")),
			"email" => trim((string) ($ctl->POST("email") ?? "")),
			"phone" => trim((string) ($ctl->POST("phone") ?? "")),
			"message" => trim((string) ($ctl->POST("message") ?? "")),
			"status" => "new",
			"created_at" => time(),
			"updated_at" => time(),
		];

		$errors = $this->validateRegistration($ctl, $row);
		if ($errors !== []) {
			$this->showForm($ctl, $session, $row, $errors);
			return;
		}

		$registrationId = (int) ($ctl->db("event_registrations")->insert($row) ?? 0);
		$this->assignFrame($ctl, "Registration Complete");
		$ctl->assign("session", $session);
		$ctl->assign("session_label", $this->sessionLabel($session));
		$ctl->assign("registration_id", $registrationId);
		$ctl->assign("page_url", $ctl->get_APP_URL("event_registration_public", "page"));
		$ctl->show_public_pages("complete.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function showForm(Controller $ctl, array $session, array $row, array $errors): void {
		$this->assignFrame($ctl, "Register");
		$sessionKey = $ctl->encrypt((string) ($session["id"] ?? 0));
		$ctl->assign("session", $session);
		$ctl->assign("session_label", $this->sessionLabel($session));
		$ctl->assign("row", $row);
		$ctl->assign("errors", $errors);
		$ctl->assign("session_key", $sessionKey);
		$ctl->assign("save_url", $ctl->get_APP_URL("event_registration_public", "save"));
		$ctl->assign("page_url", $ctl->get_APP_URL("event_registration_public", "page"));
		$ctl->show_public_pages("form.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function showError(Controller $ctl, string $message): void {
		$this->assignFrame($ctl, "Registration Error");
		$ctl->assign("message", $message);
		$ctl->assign("page_url", $ctl->get_APP_URL("event_registration_public", "page"));
		$ctl->show_public_pages("error.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function assignFrame(Controller $ctl, string $pageTitle): void {
		$ctl->assign("page_title", $pageTitle);
		$ctl->assign("app_name", "Event Registration");
	}

	private function emptyRegistration(): array {
		return [
			"name" => "",
			"email" => "",
			"phone" => "",
			"message" => "",
		];
	}

	private function validateRegistration(Controller $ctl, array $row): array {
		$errors = [];
		if ((string) ($row["name"] ?? "") === "") {
			$errors["name"] = "Enter your name.";
		}
		if ((string) ($row["email"] ?? "") === "") {
			$errors["email"] = "Enter your email.";
		} elseif (!filter_var((string) $row["email"], FILTER_VALIDATE_EMAIL)) {
			$errors["email"] = "Enter a valid email.";
		}
		if ((int) ($row["session_id"] ?? 0) <= 0) {
			$errors["session"] = "Select a valid event session.";
		}
		return $errors;
	}

	private function sessionFromKey(Controller $ctl, string $sessionKey): array {
		$sessionKey = trim($sessionKey);
		if ($sessionKey === "") {
			return [];
		}
		$id = (int) $ctl->decrypt($sessionKey);
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("event_sessions")->get($id);
		return is_array($row) ? $row : [];
	}

	private function publicSessions(Controller $ctl): array {
		$rows = $ctl->db("event_sessions")->getall("starts_at", SORT_ASC);
		usort($rows, function ($a, $b) {
			return strcmp($this->sessionSortKey($a), $this->sessionSortKey($b));
		});
		$rows = array_values(array_filter($rows, function ($row) use ($ctl) {
			return $this->isRegistrationOpen($ctl, $row);
		}));

		return array_map(function ($row) use ($ctl) {
			$registered = $this->countActiveRegistrations($ctl, (int) ($row["id"] ?? 0));
			$row["_remaining"] = max(0, (int) ($row["capacity"] ?? 0) - $registered);
			$row["_label"] = $this->sessionLabel($row);
			$row["_date_label"] = $this->sessionDateLabel($row);
			$row["_time_label"] = $this->sessionTimeLabel($row);
			$row["_form_url"] = $ctl->get_APP_URL("event_registration_public", "form", [
				"session" => $ctl->encrypt((string) ($row["id"] ?? 0)),
			]);
			return $row;
		}, $rows);
	}

	private function isRegistrationOpen(Controller $ctl, array $session): bool {
		if ((string) ($session["status"] ?? "") !== "open") {
			return false;
		}
		if ($this->isPastSession($session)) {
			return false;
		}
		$registered = $this->countActiveRegistrations($ctl, (int) ($session["id"] ?? 0));
		return $registered < max(1, (int) ($session["capacity"] ?? 1));
	}

	private function isPastSession(array $session): bool {
		$startsAt = (int) ($session["starts_at"] ?? 0);
		if ($startsAt <= 0) {
			return true;
		}
		return $startsAt < time();
	}

	private function countActiveRegistrations(Controller $ctl, int $sessionId): int {
		if ($sessionId <= 0) {
			return 0;
		}
		$count = 0;
		foreach ($ctl->db("event_registrations")->select("session_id", $sessionId) as $row) {
			if ((string) ($row["status"] ?? "") !== "cancelled") {
				$count++;
			}
		}
		return $count;
	}

	private function sessionSortKey(array $row): string {
		return sprintf("%015d %010d", (int) ($row["starts_at"] ?? 0), (int) ($row["id"] ?? 0));
	}

	private function sessionLabel(array $session): string {
		return trim($this->sessionDateLabel($session) . " " . $this->sessionTimeLabel($session) . " " . (string) ($session["title"] ?? ""));
	}

	private function sessionDateLabel(array $session): string {
		$startsAt = (int) ($session["starts_at"] ?? 0);
		return $startsAt > 0 ? date("Y-m-d", $startsAt) : "";
	}

	private function sessionTimeLabel(array $session): string {
		$startsAt = (int) ($session["starts_at"] ?? 0);
		$duration = max(1, (int) ($session["duration_minutes"] ?? 30));
		if ($startsAt <= 0) {
			return "";
		}
		return date("H:i", $startsAt) . " - " . date("H:i", $startsAt + ($duration * 60));
	}
}
