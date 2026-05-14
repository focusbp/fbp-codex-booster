<?php

class schedule_appointment_slots_original_management {

	private function tableName(): string {
		return "schedule_appointment_slots";
	}

	private function title(): string {
		return "Schedule Appointment Slots";
	}

	private function calendarAreaId(): string {
		return "#schedule_appointment_slots_original_management_calendar_area";
	}

	private function weekSessionKey(): string {
		return "schedule_appointment_slots_original_management_week";
	}

	private function slotFormFields(): array {
		return [
			"title",
			"starts_at",
			"duration_minutes",
			"status",
			"customer_name",
			"customer_email",
			"customer_phone",
			"customer_message",
			"internal_note",
		];
	}

	private function defaultSlot(Controller $ctl, int $startsAt = 0): array {
		if ($startsAt <= 0) {
			$startsAt = strtotime("+1 day 10:00");
		}
		return [
			"user_id" => $this->currentUserId($ctl),
			"title" => "Appointment",
			"starts_at" => $startsAt,
			"duration_minutes" => 30,
			"status" => "available",
			"customer_name" => "",
			"customer_email" => "",
			"customer_phone" => "",
			"customer_message" => "",
			"booked_at" => "",
			"internal_note" => "",
		];
	}

	private function rememberMainArea(Controller $ctl): void {
		$ctl->set_session("__AUTO_LOAD_MAIN_AREA", [
			"class" => __CLASS__,
			"function" => "run",
			"parameters" => [],
		]);
	}

	private function currentUserId(Controller $ctl): int {
		$userId = (int) ($ctl->get_login_user_id() ?? $ctl->get_session("user_id") ?? 0);
		if ($userId > 0) {
			return $userId;
		}
		$user = $this->firstUser($ctl);
		return (int) ($user["id"] ?? 0);
	}

	private function currentUser(Controller $ctl): array {
		$userId = $this->currentUserId($ctl);
		if ($userId > 0) {
			$user = $ctl->db("user", "user")->get($userId);
			if (is_array($user) && !empty($user)) {
				return $user;
			}
		}
		return $this->firstUser($ctl);
	}

	private function firstUser(Controller $ctl): array {
		$users = $ctl->db("user", "user")->getall("id", SORT_ASC);
		foreach ($users as $user) {
			if ((string) ($user["status"] ?? "0") === "0") {
				return $user;
			}
		}
		return is_array($users[0] ?? null) ? $users[0] : [];
	}

	private function userName(array $user): string {
		$name = trim((string) ($user["name"] ?? ""));
		if ($name !== "") {
			return $name;
		}
		$loginId = trim((string) ($user["login_id"] ?? ""));
		return $loginId !== "" ? $loginId : "User";
	}

	private function statusOptions(Controller $ctl): array {
		return $ctl->get_constant_array("schedule_appointment_status", false);
	}

	private function startOfWeek(int $time): int {
		$base = strtotime(date("Y-m-d 00:00:00", $time));
		$dayOfWeek = (int) date("N", $base);
		return (int) strtotime("-" . ($dayOfWeek - 1) . " day", $base);
	}

	private function currentWeekStart(Controller $ctl): int {
		$saved = (int) ($ctl->get_session($this->weekSessionKey()) ?? 0);
		if ($saved > 0) {
			return $this->startOfWeek($saved);
		}
		return $this->startOfWeek(time());
	}

	private function saveWeekStart(Controller $ctl, int $weekStart): void {
		$ctl->set_session($this->weekSessionKey(), $this->startOfWeek($weekStart));
	}

	private function dateFormat(Controller $ctl): string {
		$setting = $ctl->get_setting();
		return !empty($setting["date_format"]) ? (string) $setting["date_format"] : "Y/m/d";
	}

	private function monthDayFormat(Controller $ctl): string {
		$setting = $ctl->get_setting();
		return !empty($setting["month_day_format"]) ? (string) $setting["month_day_format"] : "n/j";
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

	private function slots(Controller $ctl, int $weekStart): array {
		$userId = $this->currentUserId($ctl);
		if ($userId <= 0) {
			return [];
		}
		$weekEnd = strtotime("+7 day", $weekStart);
		$rows = $ctl->db($this->tableName())->select("user_id", $userId, true, "AND", "starts_at", SORT_ASC);
		$result = [];
		foreach ($rows as $row) {
			$startsAt = (int) ($row["starts_at"] ?? 0);
			if ($startsAt < $weekStart || $startsAt >= $weekEnd) {
				continue;
			}
			$result[] = $row;
		}
		usort($result, function ($a, $b) {
			$adt = (int) ($a["starts_at"] ?? 0);
			$bdt = (int) ($b["starts_at"] ?? 0);
			if ($adt !== $bdt) {
				return $adt <=> $bdt;
			}
			return ((int) ($a["id"] ?? 0)) <=> ((int) ($b["id"] ?? 0));
		});
		return $result;
	}

	private function assignCalendarArea(Controller $ctl, int $weekStart): void {
		$slots = $this->slots($ctl, $weekStart);
		$assigned = [];
		$occupied = [];
		$startHour = 8;
		$endHour = 18;

		foreach ($slots as &$slot) {
			$startsAt = (int) ($slot["starts_at"] ?? 0);
			$duration = max(15, (int) ($slot["duration_minutes"] ?? 30));
			$endsAt = $startsAt + ($duration * 60);
			$slot["start_time"] = date("H:i", $startsAt);
			$slot["end_time"] = date("H:i", $endsAt);
			$slot["status_label"] = $this->statusOptions($ctl)[$slot["status"] ?? ""] ?? (string) ($slot["status"] ?? "");

			$slotTime = $startsAt - ($startsAt % 3600);
			$assigned[$slotTime][] = $slot;
			for ($i = $slotTime; $i < ceil($endsAt / 3600) * 3600; $i += 3600) {
				$occupied[$i] = "original_calendar_box_occupied";
			}

			$hour = (int) date("G", $startsAt);
			$endHourCandidate = (int) date("G", $endsAt);
			if ($hour < $startHour) {
				$startHour = $hour;
			}
			if ($endHourCandidate > $endHour) {
				$endHour = $endHourCandidate;
			}
		}

		if ($startHour > 8) {
			$startHour = 8;
		}
		if ($endHour < 18) {
			$endHour = 18;
		}

		$calendarDays = [];
		$dayKeys = [
			"cron.weekday.mon",
			"cron.weekday.tue",
			"cron.weekday.wed",
			"cron.weekday.thu",
			"cron.weekday.fri",
			"cron.weekday.sat",
			"cron.weekday.sun",
		];
		for ($i = 0; $i < 7; $i++) {
			$dayStart = strtotime("+" . $i . " day", $weekStart);
			$hours = [];
			for ($hour = $startHour; $hour <= $endHour; $hour++) {
				$hours[] = [
					"label" => sprintf("%02d:00", $hour),
					"target_time" => $dayStart + ($hour * 3600),
				];
			}
			$calendarDays[] = [
				"date_label" => date($this->monthDayFormat($ctl), $dayStart),
				"day_label" => $ctl->t($dayKeys[$i]),
				"is_today" => (date("Y-m-d", $dayStart) === date("Y-m-d")),
				"hours" => $hours,
			];
		}

		$user = $this->currentUser($ctl);
		$ctl->assign("assigned", $assigned);
		$ctl->assign("occupied", $occupied);
		$ctl->assign("calendar_days", $calendarDays);
		$ctl->assign("current_user_name", $this->userName($user));
		$ctl->assign("week_previous", strtotime("-7 day", $weekStart));
		$ctl->assign("week_next", strtotime("+7 day", $weekStart));
		$ctl->assign("week_current", $this->startOfWeek(time()));
		$ctl->assign("jump_date", date("Y-m-d", $weekStart));
		$ctl->assign("week_label", date($this->dateFormat($ctl), $weekStart) . " - " . date($this->dateFormat($ctl), strtotime("+6 day", $weekStart)));
		$ctl->assign("timezone_label", date_default_timezone_get());
	}

	private function assignForm(Controller $ctl, array $row): void {
		$ctl->assign("row", array_merge($this->defaultSlot($ctl), $row));
		$ctl->assign("form_fields", $this->slotFormFields());
	}

	private function collectSlotFromPost(Controller $ctl, array $base = []): array {
		$row = array_merge($this->defaultSlot($ctl), $base);
		foreach ($this->slotFormFields() as $field) {
			$row[$field] = $ctl->POST($field) ?? "";
		}
		$row["user_id"] = $this->currentUserId($ctl);
		$row["starts_at"] = $this->normalizeTimestamp($row["starts_at"] ?? "");
		$row["duration_minutes"] = (int) ($row["duration_minutes"] ?? 0);
		if ((string) ($row["status"] ?? "") === "") {
			$row["status"] = "available";
		}
		return $row;
	}

	private function validateSlot(Controller $ctl, array $row): bool {
		$ctl->validate($this->tableName(), $this->slotFormFields(), $row);
		if ((int) ($row["user_id"] ?? 0) <= 0) {
			$ctl->res_error_message("user_id", "Login user was not found.");
		}
		if ((int) ($row["starts_at"] ?? 0) <= 0) {
			$ctl->res_error_message("starts_at", "Enter a valid start date and time.");
		}
		if ((int) ($row["duration_minutes"] ?? 0) < 15) {
			$ctl->res_error_message("duration_minutes", "Duration must be at least 15 minutes.");
		}
		if (!isset($this->statusOptions($ctl)[$row["status"] ?? ""])) {
			$ctl->res_error_message("status", "Select a valid status.");
		}
		return $ctl->count_res_error_message() === 0;
	}

	private function ownSlot(Controller $ctl, int $id): array {
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db($this->tableName())->get($id);
		if (!is_array($row) || empty($row)) {
			return [];
		}
		return ((int) ($row["user_id"] ?? 0) === $this->currentUserId($ctl)) ? $row : [];
	}

	function run(Controller $ctl): void {
		$this->rememberMainArea($ctl);
		$this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
		$ctl->show_main_area("calendar.tpl", $this->title());
	}

	function set_week(Controller $ctl): void {
		$mode = (string) ($ctl->POST("mode") ?? "");
		$weekStart = $this->currentWeekStart($ctl);
		if ($mode === "previous") {
			$weekStart = strtotime("-7 day", $weekStart);
		} else if ($mode === "next") {
			$weekStart = strtotime("+7 day", $weekStart);
		} else if ($mode === "current") {
			$weekStart = $this->startOfWeek(time());
		} else if ($mode === "jump") {
			$jumpDate = trim((string) ($ctl->POST("jump_date") ?? ""));
			if ($jumpDate !== "") {
				$weekStart = $this->startOfWeek((int) strtotime($jumpDate));
			}
		}
		$this->saveWeekStart($ctl, $weekStart);
		$this->assignCalendarArea($ctl, $weekStart);
		$ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
	}

	function public_url_dialog(Controller $ctl): void {
		$user = $this->currentUser($ctl);
		$userId = (int) ($user["id"] ?? 0);
		if ($userId <= 0) {
			$ctl->show_notification_text("Login user was not found.");
			return;
		}
		$ctl->assign("current_user_name", $this->userName($user));
		$ctl->assign("public_url", $ctl->get_APP_URL("schedule_appointment_public", "calendar", [
			"user" => $ctl->encrypt((string) $userId),
		]));
		$ctl->show_multi_dialog("schedule_appointment_public_url", "public_url.tpl", "Schedule Appointment URL", 760);
	}

	function add_dialog(Controller $ctl): void {
		$startsAt = $this->normalizeTimestamp($ctl->POST("starts_at") ?? $ctl->POST("datetime") ?? "");
		$this->assignForm($ctl, $this->defaultSlot($ctl, $startsAt));
		$ctl->show_multi_dialog("schedule_appointment_slots_add", "add.tpl", "Add Appointment Slot", 760);
	}

	function add_save(Controller $ctl): void {
		$row = $this->collectSlotFromPost($ctl);
		if (!$this->validateSlot($ctl, $row)) {
			return;
		}
		if ((string) ($row["status"] ?? "") === "booked" && empty($row["booked_at"])) {
			$row["booked_at"] = time();
		}
		$ctl->db($this->tableName())->insert($row);
		$ctl->close_multi_dialog("schedule_appointment_slots_add");
		$this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
		$ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
	}

	function edit_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $this->ownSlot($ctl, $id);
		if (empty($row)) {
			$ctl->show_notification_text("Appointment slot not found.");
			return;
		}
		$this->assignForm($ctl, $row);
		$ctl->show_multi_dialog("schedule_appointment_slots_edit", "edit.tpl", "Edit Appointment Slot", 760);
	}

	function edit_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$existing = $this->ownSlot($ctl, $id);
		if (empty($existing)) {
			$ctl->show_notification_text("Appointment slot not found.");
			return;
		}
		$row = $this->collectSlotFromPost($ctl, $existing);
		$row["id"] = $id;
		if (!$this->validateSlot($ctl, $row)) {
			return;
		}
		if ((string) ($row["status"] ?? "") === "booked" && empty($row["booked_at"])) {
			$row["booked_at"] = time();
		}
		$ctl->db($this->tableName())->update($row);
		$ctl->close_multi_dialog("schedule_appointment_slots_edit");
		$this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
		$ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
	}

	function delete_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $this->ownSlot($ctl, $id);
		if (empty($row)) {
			$ctl->show_notification_text("Appointment slot not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->show_multi_dialog("schedule_appointment_slots_delete", "delete_confirm.tpl", "Delete Appointment Slot", 520);
	}

	function delete_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $this->ownSlot($ctl, $id);
		if (empty($row)) {
			$ctl->show_notification_text("Appointment slot not found.");
			return;
		}
		$ctl->db($this->tableName())->delete($id);
		$ctl->close_multi_dialog("schedule_appointment_slots_delete");
		$this->assignCalendarArea($ctl, $this->currentWeekStart($ctl));
		$ctl->reload_area($this->calendarAreaId(), "calendar_area.tpl");
	}
}
