<?php

class schedule_appointment_public {

	private function tableName(): string {
		return "schedule_appointment_slots";
	}

	private function userSessionKey(): string {
		return "schedule_appointment_public_user_id";
	}

	private function bookedStatus(): string {
		return "0";
	}

	private function isCancelledStatus($status): bool {
		$status = (string) $status;
		return $status === "2" || $status === "cancelled";
	}

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function calendar(Controller $ctl): void {
		$context = $this->publicUserContext($ctl, (string) ($ctl->GET("user") ?? $ctl->POST("user") ?? ""));
		if (empty($context)) {
			$this->showError($ctl, "Appointment calendar was not found.");
			return;
		}

		$weekStart = $this->weekStartFromRequest($ctl);
		$this->assignFrame($ctl, "Appointment Calendar", $context);
		$this->assignCalendar($ctl, $context, $weekStart);
		$ctl->show_public_pages("calendar.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	function book(Controller $ctl): void {
		$context = $this->publicUserContext($ctl, (string) ($ctl->GET("user") ?? $ctl->POST("user") ?? ""));
		$startsAt = $this->startFromKey($ctl, (string) ($ctl->GET("start") ?? $ctl->POST("start") ?? ""));
		if (empty($context) || !$this->isStartBookable($ctl, (int) ($context["id"] ?? 0), $startsAt)) {
			$this->showError($ctl, "This appointment time is no longer available.");
			return;
		}
		$slot = $this->emptySlot($context, $startsAt);
		$this->showBookingForm($ctl, $context, $slot, $this->emptyBooking(), []);
	}

	function save(Controller $ctl): void {
		$context = $this->publicUserContext($ctl, (string) ($ctl->POST("user") ?? ""));
		$startsAt = $this->startFromKey($ctl, (string) ($ctl->POST("start") ?? ""));
		if (empty($context) || !$this->isStartBookable($ctl, (int) ($context["id"] ?? 0), $startsAt)) {
			$this->showError($ctl, "This appointment time is no longer available.");
			return;
		}

		$row = [
			"customer_name" => trim((string) ($ctl->POST("customer_name") ?? "")),
			"customer_email" => trim((string) ($ctl->POST("customer_email") ?? "")),
			"customer_phone" => trim((string) ($ctl->POST("customer_phone") ?? "")),
			"customer_message" => trim((string) ($ctl->POST("customer_message") ?? "")),
		];
		$errors = $this->validateBooking($row);
		if ($errors !== []) {
			$this->showBookingForm($ctl, $context, $this->emptySlot($context, $startsAt), $row, $errors);
			return;
		}

		$slot = $this->emptySlot($context, $startsAt);
		$slot["title"] = "Appointment";
		if ($row["customer_name"] !== "") {
			$slot["title"] = "Appointment: " . $row["customer_name"];
		}
		$slot["customer_name"] = $row["customer_name"];
		$slot["customer_email"] = $row["customer_email"];
		$slot["customer_phone"] = $row["customer_phone"];
		$slot["customer_message"] = $row["customer_message"];
		$slot["booked_at"] = time();
		$slotId = (int) ($ctl->db($this->tableName())->insert($slot) ?? 0);
		$slot["id"] = $slotId;

		$this->assignFrame($ctl, "Appointment Complete", $context);
		$ctl->assign("slot", $this->decorateSlot($ctl, $slot));
		$ctl->assign("calendar_url", $this->calendarUrl($ctl, $context));
		$ctl->show_public_pages("complete.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function publicUserContext(Controller $ctl, string $key): array {
		$userId = 0;
		$key = trim($key);
		if ($key !== "") {
			$userId = (int) $ctl->decrypt($key);
			if ($userId > 0) {
				$ctl->set_session($this->userSessionKey(), $userId);
			}
		}
		if ($userId <= 0) {
			$userId = (int) ($ctl->get_session($this->userSessionKey()) ?? 0);
		}
		if ($userId <= 0) {
			return [];
		}
		$user = $ctl->db("user", "user")->get($userId);
		if (!is_array($user) || empty($user)) {
			return [];
		}
		return [
			"id" => (int) ($user["id"] ?? 0),
			"name" => $this->userName($user),
			"key" => $ctl->encrypt((string) ($user["id"] ?? 0)),
		];
	}

	private function userName(array $user): string {
		$name = trim((string) ($user["name"] ?? ""));
		if ($name !== "") {
			return $name;
		}
		$loginId = trim((string) ($user["login_id"] ?? ""));
		return $loginId !== "" ? $loginId : "User";
	}

	private function weekStartFromRequest(Controller $ctl): int {
		$week = trim((string) ($ctl->GET("week") ?? $ctl->POST("week") ?? ""));
		if ($week !== "") {
			$time = strtotime($week);
			if ($time !== false) {
				return $this->startOfWeek((int) $time);
			}
		}
		return $this->startOfWeek(time());
	}

	private function startOfWeek(int $time): int {
		$base = strtotime(date("Y-m-d 00:00:00", $time));
		$dayOfWeek = (int) date("N", $base);
		return (int) strtotime("-" . ($dayOfWeek - 1) . " day", $base);
	}

	private function assignFrame(Controller $ctl, string $pageTitle, array $context = []): void {
		$ctl->assign("page_title", $pageTitle);
		$ctl->assign("app_name", "Schedule Appointment");
		$ctl->assign("provider_name", (string) ($context["name"] ?? ""));
	}

	private function assignCalendar(Controller $ctl, array $context, int $weekStart): void {
		$occupiedByTime = $this->occupiedSlotsByTime($ctl, (int) $context["id"], $weekStart);

		$calendarDays = [];
		$dayKeys = ["Mon", "Tue", "Wed", "Thu", "Fri"];
		for ($i = 0; $i < 5; $i++) {
			$dayStart = strtotime("+" . $i . " day", $weekStart);
			$times = [];
			for ($time = strtotime(date("Y-m-d 10:00:00", $dayStart)); $time <= strtotime(date("Y-m-d 17:00:00", $dayStart)); $time += 1800) {
				$slot = $occupiedByTime[$time] ?? [];
				$bookUrl = "";
				if ($slot === [] && $this->isGridTime($time) && $time > time()) {
					$bookUrl = $ctl->get_APP_URL("schedule_appointment_public", "book", [
						"user" => $context["key"],
						"start" => $ctl->encrypt((string) $time),
					]);
				}
				$times[] = [
					"label" => date("H:i", $time),
					"slot" => $slot,
					"book_url" => $bookUrl,
					"is_selectable" => $bookUrl !== "",
				];
			}
			$calendarDays[] = [
				"date_label" => date("n/j", $dayStart),
				"day_label" => $dayKeys[$i],
				"times" => $times,
			];
		}

		$ctl->assign("calendar_days", $calendarDays);
		$ctl->assign("week_label", date("Y/m/d", $weekStart) . " - " . date("Y/m/d", strtotime("+4 day", $weekStart)));
		$ctl->assign("previous_url", $this->calendarUrl($ctl, $context, strtotime("-7 day", $weekStart)));
		$ctl->assign("next_url", $this->calendarUrl($ctl, $context, strtotime("+7 day", $weekStart)));
	}

	private function calendarUrl(Controller $ctl, array $context, int $weekStart = 0): string {
		$params = ["user" => (string) ($context["key"] ?? "")];
		if ($weekStart > 0) {
			$params["week"] = date("Y-m-d", $weekStart);
		}
		return $ctl->get_APP_URL("schedule_appointment_public", "calendar", $params);
	}

	private function occupiedSlotsByTime(Controller $ctl, int $userId, int $weekStart): array {
		$slotsByTime = [];
		foreach ($this->occupiedSlots($ctl, $userId, $weekStart) as $slot) {
			$decorated = $this->decorateSlot($ctl, $slot);
			$startsAt = (int) ($slot["starts_at"] ?? 0);
			$duration = max(15, (int) ($slot["duration_minutes"] ?? 30));
			$endsAt = $startsAt + ($duration * 60);
			$gridStart = $startsAt - ($startsAt % 1800);
			for ($time = $gridStart; $time < $endsAt; $time += 1800) {
				if (($time + 1800) > $startsAt && $time < $endsAt) {
					$slotsByTime[$time] = $decorated;
				}
			}
		}
		return $slotsByTime;
	}

	private function occupiedSlots(Controller $ctl, int $userId, int $weekStart): array {
		$weekEnd = strtotime("+5 day", $weekStart);
		$rows = $ctl->db($this->tableName())->select("user_id", $userId, true, "AND", "starts_at", SORT_ASC);
		$result = [];
		foreach ($rows as $row) {
			$startsAt = (int) ($row["starts_at"] ?? 0);
			$endsAt = $startsAt + (max(15, (int) ($row["duration_minutes"] ?? 30)) * 60);
			if ($endsAt <= $weekStart || $startsAt >= $weekEnd || !$this->isOccupied($row)) {
				continue;
			}
			$result[] = $row;
		}
		return $result;
	}

	private function startFromKey(Controller $ctl, string $startKey): int {
		$start = (int) $ctl->decrypt(trim($startKey));
		return $start > 0 ? $start : 0;
	}

	private function isStartBookable(Controller $ctl, int $userId, int $startsAt): bool {
		if ($userId <= 0 || !$this->isGridTime($startsAt) || $startsAt <= time()) {
			return false;
		}
		return !$this->hasOverlap($ctl, $userId, $startsAt, 30);
	}

	private function isGridTime(int $startsAt): bool {
		if ($startsAt <= 0 || (int) date("N", $startsAt) > 5) {
			return false;
		}
		$minutes = ((int) date("G", $startsAt) * 60) + (int) date("i", $startsAt);
		return $minutes >= 600 && $minutes <= 1020 && ($minutes % 30) === 0;
	}

	private function hasOverlap(Controller $ctl, int $userId, int $startsAt, int $durationMinutes): bool {
		$endsAt = $startsAt + (max(15, $durationMinutes) * 60);
		$rows = $ctl->db($this->tableName())->select("user_id", $userId, true, "AND", "starts_at", SORT_ASC);
		foreach ($rows as $row) {
			if (!$this->isOccupied($row)) {
				continue;
			}
			$rowStart = (int) ($row["starts_at"] ?? 0);
			$rowEnd = $rowStart + (max(15, (int) ($row["duration_minutes"] ?? 30)) * 60);
			if ($startsAt < $rowEnd && $endsAt > $rowStart) {
				return true;
			}
		}
		return false;
	}

	private function isOccupied(array $slot): bool {
		return !$this->isCancelledStatus($slot["status"] ?? "");
	}

	private function emptySlot(array $context, int $startsAt): array {
		return [
			"user_id" => (int) ($context["id"] ?? 0),
			"title" => "Appointment",
			"starts_at" => $startsAt,
			"duration_minutes" => 30,
			"status" => $this->bookedStatus(),
			"customer_name" => "",
			"customer_email" => "",
			"customer_phone" => "",
			"customer_message" => "",
			"booked_at" => "",
			"internal_note" => "",
		];
	}

	private function decorateSlot(Controller $ctl, array $slot): array {
		$startsAt = (int) ($slot["starts_at"] ?? 0);
		$duration = max(15, (int) ($slot["duration_minutes"] ?? 30));
		$slot["_date_label"] = $startsAt > 0 ? date("Y/m/d", $startsAt) : "";
		$slot["_time_label"] = $startsAt > 0 ? date("H:i", $startsAt) . " - " . date("H:i", $startsAt + ($duration * 60)) : "";
		$slot["_start_key"] = $ctl->encrypt((string) $startsAt);
		return $slot;
	}

	private function emptyBooking(): array {
		return [
			"customer_name" => "",
			"customer_email" => "",
			"customer_phone" => "",
			"customer_message" => "",
		];
	}

	private function validateBooking(array $row): array {
		$errors = [];
		if ((string) ($row["customer_name"] ?? "") === "") {
			$errors["customer_name"] = "Enter your name.";
		}
		$email = (string) ($row["customer_email"] ?? "");
		if ($email === "") {
			$errors["customer_email"] = "Enter your email.";
		} else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors["customer_email"] = "Enter a valid email.";
		}
		return $errors;
	}

	private function showBookingForm(Controller $ctl, array $context, array $slot, array $row, array $errors): void {
		$this->assignFrame($ctl, "Book Appointment", $context);
		$slot = $this->decorateSlot($ctl, $slot);
		$ctl->assign("slot", $slot);
		$ctl->assign("row", array_merge($this->emptyBooking(), $row));
		$ctl->assign("errors", $errors);
		$ctl->assign("user_key", (string) ($context["key"] ?? ""));
		$ctl->assign("start_key", (string) ($slot["_start_key"] ?? ""));
		$ctl->assign("save_url", $ctl->get_APP_URL("schedule_appointment_public", "save"));
		$ctl->assign("calendar_url", $this->calendarUrl($ctl, $context));
		$ctl->show_public_pages("book.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function showError(Controller $ctl, string $message): void {
		$this->assignFrame($ctl, "Appointment Error");
		$ctl->assign("message", $message);
		$ctl->show_public_pages("error.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}
}
