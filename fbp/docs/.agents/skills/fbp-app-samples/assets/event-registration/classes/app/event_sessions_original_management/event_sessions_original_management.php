<?php

class event_sessions_original_management {

	private function sessionTable(): string {
		return "event_sessions";
	}

	private function registrationTable(): string {
		return "event_registrations";
	}

	private function listAreaId(): string {
		return "#event_sessions_original_management_list_area";
	}

	private function participantsSideAreaId(): string {
		return "#event_sessions_original_management_participants_side_area";
	}

	private function sessionFormFields(): array {
		return [
			"title",
			"starts_at",
			"duration_minutes",
			"capacity",
			"status",
			"memo",
		];
	}

	private function registrationFormFields(): array {
		return [
			"name",
			"email",
			"phone",
			"message",
			"status",
		];
	}

	private function emptySession(): array {
		return [
			"title" => "",
			"starts_at" => strtotime("+2 days 10:00"),
			"duration_minutes" => 30,
			"capacity" => 1,
			"status" => "open",
			"memo" => "",
		];
	}

	private function emptyRegistration(int $sessionId): array {
		return [
			"session_id" => $sessionId,
			"name" => "",
			"email" => "",
			"phone" => "",
			"message" => "",
			"status" => "new",
		];
	}

	private function sessionStatusOptions(Controller $ctl): array {
		return $ctl->get_constant_array("event_session_status", false);
	}

	private function registrationStatusOptions(Controller $ctl): array {
		return $ctl->get_constant_array("event_registration_status", false);
	}

	private function rememberMainArea(Controller $ctl): void {
		$ctl->set_session("__AUTO_LOAD_MAIN_AREA", [
			"class" => __CLASS__,
			"function" => "run",
			"parameters" => [],
		]);
	}

	private function rowFromPost(Controller $ctl, array $base = []): array {
		$row = array_merge($this->emptySession(), $base);
		foreach ($this->sessionFormFields() as $field) {
			$row[$field] = trim((string) ($ctl->POST($field) ?? ""));
		}
		$row["starts_at"] = $this->normalizeTimestamp($row["starts_at"] ?? "");
		if ($row["capacity"] === "") {
			$row["capacity"] = 1;
		}
		if ($row["duration_minutes"] === "") {
			$row["duration_minutes"] = 30;
		}
		if ($row["status"] === "") {
			$row["status"] = "open";
		}
		return $row;
	}

	private function registrationFromPost(Controller $ctl, int $sessionId): array {
		$row = $this->emptyRegistration($sessionId);
		foreach ($this->registrationFormFields() as $field) {
			$row[$field] = trim((string) ($ctl->POST($field) ?? ""));
		}
		if ($row["status"] === "") {
			$row["status"] = "new";
		}
		return $row;
	}

	private function normalizeTimestamp($value): int {
		if (is_int($value)) {
			return $value;
		}
		$value = trim((string) $value);
		if ($value === "") {
			return 0;
		}
		if (ctype_digit($value)) {
			return (int) $value;
		}
		$timestamp = strtotime($value);
		return $timestamp === false ? 0 : (int) $timestamp;
	}

	private function validateSession(Controller $ctl, array $row): bool {
		$ctl->validate($this->sessionTable(), $this->sessionFormFields(), $row);
		if (!isset($this->sessionStatusOptions($ctl)[$row["status"]])) {
			$ctl->res_error_message("status", "Select a valid status.");
		}
		if ((int) ($row["capacity"] ?? 0) < 1) {
			$ctl->res_error_message("capacity", "Capacity must be at least 1.");
		}
		if ((int) ($row["starts_at"] ?? 0) <= 0) {
			$ctl->res_error_message("starts_at", "Enter a valid start date and time.");
		}
		if ((int) ($row["duration_minutes"] ?? 0) < 1) {
			$ctl->res_error_message("duration_minutes", "Duration must be at least 1 minute.");
		}
		return $ctl->count_res_error_message() === 0;
	}

	private function validateRegistration(Controller $ctl, array $row): bool {
		if ((int) ($row["session_id"] ?? 0) <= 0) {
			$ctl->res_error_message("session_id", "Select a valid event session.");
		}
		if ((string) ($row["name"] ?? "") === "") {
			$ctl->res_error_message("name", "Enter a name.");
		}
		$email = (string) ($row["email"] ?? "");
		if ($email === "") {
			$ctl->res_error_message("email", "Enter an email.");
		} else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$ctl->res_error_message("email", "Enter a valid email.");
		}
		if (!isset($this->registrationStatusOptions($ctl)[$row["status"] ?? ""])) {
			$ctl->res_error_message("status", "Select a valid status.");
		}
		return $ctl->count_res_error_message() === 0;
	}

	private function assignRows(Controller $ctl): void {
		$isSearch = ((string) ($ctl->POST("_registration_session_search") ?? "") === "1");
		$search = [
			"keyword" => $isSearch ? trim((string) ($ctl->POST("keyword") ?? "")) : "",
			"status" => $isSearch ? trim((string) ($ctl->POST("status") ?? "")) : "",
		];
		$rows = $this->filteredSessions($ctl, $search);
		$rows = array_map(function ($row) use ($ctl) {
			$sessionId = (int) ($row["id"] ?? 0);
			$row["_registration_count"] = $this->countRegistrationsForSession($ctl, $sessionId);
			$row["_remaining"] = max(0, (int) ($row["capacity"] ?? 0) - (int) $row["_registration_count"]);
			$row["_date_label"] = $this->sessionDateLabel($row);
			$row["_time_label"] = $this->sessionTimeLabel($row);
			return $row;
		}, $rows);

		$ctl->assign("rows", $rows);
		$ctl->assign("count", count($rows));
		$ctl->assign("search", $search);
		$ctl->assign("session_status_options", $this->sessionStatusOptions($ctl));
		$ctl->assign("session_status_filter_options", ["" => "All Statuses"] + $this->sessionStatusOptions($ctl));
	}

	private function filteredSessions(Controller $ctl, array $search): array {
		$rows = $ctl->db($this->sessionTable())->getall("starts_at", SORT_ASC);
		usort($rows, function ($a, $b) {
			return strcmp($this->sessionSortKey($a), $this->sessionSortKey($b));
		});

		$keyword = mb_strtolower((string) ($search["keyword"] ?? ""));
		$status = (string) ($search["status"] ?? "");
		if ($keyword === "" && $status === "") {
			return $rows;
		}

		return array_values(array_filter($rows, function ($row) use ($keyword, $status) {
			if ($status !== "" && (string) ($row["status"] ?? "") !== $status) {
				return false;
			}
			if ($keyword === "") {
				return true;
			}
			foreach (["id", "title", "memo"] as $field) {
				if (mb_stripos((string) ($row[$field] ?? ""), $keyword) !== false) {
					return true;
				}
			}
			if (mb_stripos($this->sessionLabel($row), $keyword) !== false) {
				return true;
			}
			return false;
		}));
	}

	private function sessionSortKey(array $row): string {
		return sprintf("%015d %010d", (int) ($row["starts_at"] ?? 0), (int) ($row["id"] ?? 0));
	}

	private function countRegistrationsForSession(Controller $ctl, int $sessionId): int {
		if ($sessionId <= 0) {
			return 0;
		}
		$count = 0;
		foreach ($ctl->db($this->registrationTable())->select("session_id", $sessionId) as $row) {
			if ((string) ($row["status"] ?? "") !== "cancelled") {
				$count++;
			}
		}
		return $count;
	}

	private function registrationsForSession(Controller $ctl, int $sessionId): array {
		$rows = $ctl->db($this->registrationTable())->select("session_id", $sessionId, true, "AND", "id", SORT_DESC);
		return is_array($rows) ? $rows : [];
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

	private function assignParticipantsPanel(Controller $ctl, int $sessionId): bool {
		$session = $ctl->db($this->sessionTable())->get($sessionId);
		if (empty($session)) {
			$ctl->show_notification_text("Event session not found.");
			return false;
		}
		$ctl->assign("session", $session);
		$ctl->assign("session_label", $this->sessionLabel($session));
		$ctl->assign("event_registrations", $this->registrationsForSession($ctl, $sessionId));
		$ctl->assign("event_registration_status_options", $this->registrationStatusOptions($ctl));
		return true;
	}

	private function refreshParticipants(Controller $ctl, int $sessionId): void {
		$this->reload_list($ctl);
		if ($sessionId > 0 && $this->assignParticipantsPanel($ctl, $sessionId)) {
			$ctl->reload_area($this->participantsSideAreaId(), "participants_side_area.tpl");
		}
	}

	function run(Controller $ctl): void {
		$this->rememberMainArea($ctl);
		$this->assignRows($ctl);
		$ctl->show_main_area("list.tpl", "Event Sessions");
	}

	function reload_list(Controller $ctl): void {
		$this->assignRows($ctl);
		$ctl->reload_area($this->listAreaId(), "list_area.tpl");
	}

	function public_url_dialog(Controller $ctl): void {
		$ctl->assign("public_url", $ctl->get_APP_URL("event_registration_public", "page"));
		$ctl->show_multi_dialog("event_registration_public_url", "public_url.tpl", "Public Registration URL", 760);
	}

	function add_dialog(Controller $ctl): void {
		$ctl->assign("row", $this->emptySession());
		$ctl->assign("form_fields", $this->sessionFormFields());
		$ctl->show_multi_dialog("event_sessions_original_management_add", "add.tpl", "Add Event Session", 760);
	}

	function add_save(Controller $ctl): void {
		$row = $this->rowFromPost($ctl);
		if (!$this->validateSession($ctl, $row)) {
			return;
		}
		$ctl->db($this->sessionTable())->insert($row);
		$ctl->close_multi_dialog("event_sessions_original_management_add");
		$this->reload_list($ctl);
	}

	function edit_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->sessionTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->assign("form_fields", $this->sessionFormFields());
		$ctl->show_multi_dialog("event_sessions_original_management_edit", "edit.tpl", "Edit Event Session", 760);
	}

	function edit_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$existing = $ctl->db($this->sessionTable())->get($id);
		if (empty($existing)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		$row = $this->rowFromPost($ctl, $existing);
		$row["id"] = $id;
		if (!$this->validateSession($ctl, $row)) {
			return;
		}
		$ctl->db($this->sessionTable())->update($row);
		$ctl->close_multi_dialog("event_sessions_original_management_edit");
		$this->reload_list($ctl);
	}

	function delete_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->sessionTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->assign("registration_count", $this->countRegistrationsForSession($ctl, $id));
		$ctl->show_multi_dialog("event_sessions_original_management_delete", "delete_confirm.tpl", "Delete Event Session", 520);
	}

	function delete_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->sessionTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		if ($this->countRegistrationsForSession($ctl, $id) > 0) {
			$ctl->show_notification_text("This event session has registrations and cannot be deleted.");
			return;
		}
		$ctl->db($this->sessionTable())->delete($id);
		$ctl->close_multi_dialog("event_sessions_original_management_delete");
		$this->reload_list($ctl);
	}

	function participants_side_panel(Controller $ctl): void {
		$sessionId = (int) ($ctl->POST("id") ?? 0);
		if (!$this->assignParticipantsPanel($ctl, $sessionId)) {
			return;
		}
		$ctl->show_second_work_area("participants_side_panel.tpl", 760);
	}

	function participant_add_dialog(Controller $ctl): void {
		$sessionId = (int) ($ctl->POST("session_id") ?? $ctl->POST("id") ?? 0);
		$session = $ctl->db($this->sessionTable())->get($sessionId);
		if (empty($session)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		$ctl->assign("session", $session);
		$ctl->assign("session_label", $this->sessionLabel($session));
		$ctl->assign("row", $this->emptyRegistration($sessionId));
		$ctl->assign("form_fields", $this->registrationFormFields());
		$ctl->show_multi_dialog("event_sessions_original_management_participant_add", "participant_add.tpl", "Add Participant", 640);
	}

	function participant_add_save(Controller $ctl): void {
		$sessionId = (int) ($ctl->POST("session_id") ?? 0);
		$session = $ctl->db($this->sessionTable())->get($sessionId);
		if (empty($session)) {
			$ctl->show_notification_text("Event session not found.");
			return;
		}
		$row = $this->registrationFromPost($ctl, $sessionId);
		if (!$this->validateRegistration($ctl, $row)) {
			return;
		}
		$row["created_at"] = time();
		$row["updated_at"] = time();
		$ctl->db($this->registrationTable())->insert($row);
		$ctl->close_multi_dialog("event_sessions_original_management_participant_add");
		$this->refreshParticipants($ctl, $sessionId);
	}

	function participant_delete_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->registrationTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Registration not found.");
			return;
		}
		$session = $ctl->db($this->sessionTable())->get((int) ($row["session_id"] ?? 0));
		$ctl->assign("row", $row);
		$ctl->assign("session_label", $this->sessionLabel(is_array($session) ? $session : []));
		$ctl->show_multi_dialog("event_sessions_original_management_participant_delete", "participant_delete_confirm.tpl", "Delete Participant", 520);
	}

	function participant_delete_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->registrationTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Registration not found.");
			return;
		}
		$sessionId = (int) ($row["session_id"] ?? 0);
		$ctl->db($this->registrationTable())->delete($id);
		$ctl->close_multi_dialog("event_sessions_original_management_participant_delete");
		$this->refreshParticipants($ctl, $sessionId);
	}

	function event_registration_status_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->registrationTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Registration not found.");
			return;
		}
		$session = $ctl->db($this->sessionTable())->get((int) ($row["session_id"] ?? 0));
		$ctl->assign("row", $row);
		$ctl->assign("session_label", $this->sessionLabel(is_array($session) ? $session : []));
		$ctl->assign("event_registration_status_options", $this->registrationStatusOptions($ctl));
		$ctl->show_multi_dialog("event_sessions_original_management_status", "event_registration_status.tpl", "Update Registration Status", 520);
	}

	function event_registration_status_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->registrationTable())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Registration not found.");
			return;
		}
		$status = trim((string) ($ctl->POST("status") ?? ""));
		if (!isset($this->registrationStatusOptions($ctl)[$status])) {
			$ctl->res_error_message("status", "Select a valid status.");
			return;
		}
		$row["status"] = $status;
		$row["updated_at"] = time();
		$ctl->db($this->registrationTable())->update($row);
		$ctl->close_multi_dialog("event_sessions_original_management_status");
		$this->refreshParticipants($ctl, (int) ($row["session_id"] ?? 0));
	}
}
