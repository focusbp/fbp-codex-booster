<?php

class public_service {

	private const MEMBER_SESSION = "service_member_id";
	private const PLAN_SESSION = "service_plan_id";
	private const LAST_PAYMENT_SESSION = "service_last_payment_id";
	private const RESET_TOKEN_TTL = 3600;

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function index(Controller $ctl): void {
		$this->plans($ctl);
	}

	function plans(Controller $ctl): void {
		$this->assignFrame($ctl, "Plans", [
			"plans" => $this->activePlans($ctl),
		]);
		$ctl->show_public_pages("plans.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function register(Controller $ctl): void {
		$this->assignFrame($ctl, "Create Account", [
			"row" => ["name" => "", "email" => ""],
			"errors" => [],
			"submit_url" => $ctl->get_APP_URL("public_service", "register_save"),
		]);
		$ctl->show_public_pages("register.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function register_save(Controller $ctl): void {
		$row = [
			"name" => trim((string) $ctl->POST("name")),
			"email" => trim((string) $ctl->POST("email")),
		];
		$errors = $this->validateMember($ctl, $row, true);
		$password = (string) $ctl->POST("password");
		if (strlen($password) < 8) {
			$errors["password"] = "Enter at least 8 characters.";
		}
		if ($errors !== []) {
			$this->assignFrame($ctl, "Create Account", [
				"row" => $row,
				"errors" => $errors,
				"submit_url" => $ctl->get_APP_URL("public_service", "register_save"),
			]);
			$ctl->show_public_pages("register.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
			return;
		}
		$row["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
		$row["status"] = "active";
		$row["created_at"] = time();
		$row["updated_at"] = time();
		$id = (int) $ctl->db("service_member")->insert($row);
		$ctl->set_session(self::MEMBER_SESSION, $id);
		$ctl->res_redirect($ctl->get_APP_URL("public_service", "account"));
	}

	function login(Controller $ctl): void {
		$this->assignFrame($ctl, "Login", [
			"email" => "",
			"error" => "",
			"submit_url" => $ctl->get_APP_URL("public_service", "login_exe"),
		]);
		$ctl->show_public_pages("login.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function login_exe(Controller $ctl): void {
		$email = trim((string) $ctl->POST("email"));
		$password = (string) $ctl->POST("password");
		$member = $this->memberByEmail($ctl, $email);
		if ($member === [] || !password_verify($password, (string) ($member["password_hash"] ?? ""))) {
			$this->assignFrame($ctl, "Login", [
				"email" => $email,
				"error" => "Email or password is incorrect.",
				"submit_url" => $ctl->get_APP_URL("public_service", "login_exe"),
			]);
			$ctl->show_public_pages("login.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
			return;
		}
		if ((string) ($member["status"] ?? "") !== "active") {
			$this->showError($ctl, "This account is not active.");
			return;
		}
		$ctl->set_session(self::MEMBER_SESSION, (int) ($member["id"] ?? 0));
		$ctl->res_redirect($ctl->get_APP_URL("public_service", "account"));
	}

	function logout(Controller $ctl): void {
		$ctl->set_session(self::MEMBER_SESSION, 0);
		$ctl->res_redirect($ctl->get_APP_URL("public_service", "plans"));
	}

	function account(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$this->assignFrame($ctl, "Account", [
			"member" => $member,
			"subscription" => $this->currentSubscription($ctl, (int) ($member["id"] ?? 0)),
			"payments" => $this->memberPayments($ctl, (int) ($member["id"] ?? 0)),
		]);
		$ctl->show_public_pages("account.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function request_password_reset(Controller $ctl): void {
		$this->assignFrame($ctl, "Reset Password", [
			"email" => "",
			"reset_url" => "",
			"error" => "",
			"submit_url" => $ctl->get_APP_URL("public_service", "request_password_reset_save"),
		]);
		$ctl->show_public_pages("request_password_reset.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function request_password_reset_save(Controller $ctl): void {
		$email = trim((string) $ctl->POST("email"));
		$member = $this->memberByEmail($ctl, $email);
		$resetUrl = "";
		if ($member !== []) {
			$token = bin2hex(random_bytes(24));
			$ctl->db("service_password_reset")->insert([
				"service_member_id" => (int) ($member["id"] ?? 0),
				"token" => $token,
				"expires_at" => time() + self::RESET_TOKEN_TTL,
				"used_at" => 0,
				"created_at" => time(),
			]);
			$resetUrl = $ctl->get_APP_URL("public_service", "reset_password", ["token" => $token]);
		}
		$this->assignFrame($ctl, "Reset Password", [
			"email" => $email,
			"reset_url" => $resetUrl,
			"error" => "",
			"submit_url" => $ctl->get_APP_URL("public_service", "request_password_reset_save"),
		]);
		$ctl->show_public_pages("request_password_reset.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function reset_password(Controller $ctl): void {
		$token = trim((string) $ctl->GET("token"));
		$reset = $this->validResetToken($ctl, $token);
		if ($reset === []) {
			$this->showError($ctl, "This reset link is invalid or expired.");
			return;
		}
		$this->assignFrame($ctl, "Set New Password", [
			"token" => $token,
			"error" => "",
			"submit_url" => $ctl->get_APP_URL("public_service", "reset_password_save"),
		]);
		$ctl->show_public_pages("reset_password.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	function reset_password_save(Controller $ctl): void {
		$token = trim((string) $ctl->POST("token"));
		$reset = $this->validResetToken($ctl, $token);
		$password = (string) $ctl->POST("password");
		if ($reset === [] || strlen($password) < 8) {
			$this->assignFrame($ctl, "Set New Password", [
				"token" => $token,
				"error" => "Enter a valid reset link and at least 8 password characters.",
				"submit_url" => $ctl->get_APP_URL("public_service", "reset_password_save"),
			]);
			$ctl->show_public_pages("reset_password.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
			return;
		}
		$member = $ctl->db("service_member")->get((int) ($reset["service_member_id"] ?? 0));
		if (is_array($member) && $member !== []) {
			$member["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
			$member["updated_at"] = time();
			$ctl->db("service_member")->update($member);
		}
		$reset["used_at"] = time();
		$ctl->db("service_password_reset")->update($reset);
		$ctl->res_redirect($ctl->get_APP_URL("public_service", "login"));
	}

	function subscribe(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$planId = (int) $ctl->decrypt((string) ($ctl->POST("plan_id") ?? ""));
		$plan = $this->plan($ctl, $planId);
		if ($plan === []) {
			$this->showError($ctl, "Plan was not found.");
			return;
		}
		$amount = (int) ($plan["price"] ?? 0);
		if ($amount <= 0) {
			$this->activateFreeSubscription($ctl, $member, $plan);
			$ctl->res_redirect($ctl->get_APP_URL("public_service", "account"));
			return;
		}
		$ctl->set_session(self::PLAN_SESSION, $planId);
		$ctl->show_square_dialog("public_service", "square_payment_callback", [
			"name" => (string) ($member["name"] ?? ""),
			"email" => (string) ($member["email"] ?? ""),
			"address" => "",
		], "", (string) $amount);
	}

	function square_payment_callback(Controller $ctl): void {
		$param = $ctl->get_square_callback_parameter_array() ?? [];
		$member = $this->currentMember($ctl);
		$plan = $this->plan($ctl, (int) ($ctl->get_session(self::PLAN_SESSION) ?? 0));
		if ($member === [] || $plan === []) {
			$ctl->show_square_dialog("public_service", "square_payment_callback", $param, "Payment session was not found.");
			return;
		}
		$amount = (int) ($plan["price"] ?? 0);
		if ($amount <= 0) {
			$ctl->show_square_dialog("public_service", "square_payment_callback", $param, "Payment amount must be greater than zero.");
			return;
		}
		try {
			$squareCustomerId = trim((string) ($member["square_customer_id"] ?? ""));
			if ($squareCustomerId === "") {
				$squareCustomerId = (string) $ctl->square_regist_customer((string) ($member["name"] ?? ""), (string) ($member["email"] ?? ""), "");
				if ($squareCustomerId === "") {
					throw new Exception((string) ($ctl->square_get_error() ?: "Square customer registration failed."));
				}
			}
			$squareCardId = (string) $ctl->square_regist_card($squareCustomerId);
			if ($squareCardId === "") {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square card registration failed."));
			}
			if (!$ctl->square_payment($squareCustomerId, $squareCardId, $amount)) {
				throw new Exception((string) ($ctl->square_get_error() ?: "Square payment failed."));
			}
			$member["square_customer_id"] = $squareCustomerId;
			$member["square_card_id"] = $squareCardId;
			$member["updated_at"] = time();
			$ctl->db("service_member")->update($member);
			$paymentId = $this->recordPaidSubscription($ctl, $member, $plan, $squareCustomerId, $squareCardId);
			$ctl->set_session(self::LAST_PAYMENT_SESSION, $paymentId);
			$ctl->set_session(self::PLAN_SESSION, 0);
			$ctl->close_square_dialog();
			$ctl->res_redirect($ctl->get_APP_URL("public_service", "thanks"));
		} catch (Throwable $e) {
			$message = trim($e->getMessage());
			if ($message === "") {
				$message = (string) ($ctl->square_get_error() ?: "Square payment failed.");
			}
			$ctl->show_square_dialog("public_service", "square_payment_callback", $param, $message, (string) $amount);
		}
	}

	function thanks(Controller $ctl): void {
		$member = $this->requireMember($ctl);
		if ($member === []) {
			return;
		}
		$this->assignFrame($ctl, "Thank You", [
			"payment" => $this->lastPayment($ctl, (int) ($member["id"] ?? 0)),
			"subscription" => $this->currentSubscription($ctl, (int) ($member["id"] ?? 0)),
		]);
		$ctl->show_public_pages("thanks.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}

	private function assignFrame(Controller $ctl, string $title, array $extra = []): void {
		$base = [
			"app_name" => "Service User Management",
			"page_title" => $title,
			"member" => $this->currentMember($ctl),
			"plans_url" => $ctl->get_APP_URL("public_service", "plans"),
			"login_url" => $ctl->get_APP_URL("public_service", "login"),
			"register_url" => $ctl->get_APP_URL("public_service", "register"),
			"logout_url" => $ctl->get_APP_URL("public_service", "logout"),
			"account_url" => $ctl->get_APP_URL("public_service", "account"),
			"password_reset_url" => $ctl->get_APP_URL("public_service", "request_password_reset"),
		];
		foreach (array_merge($base, $extra) as $key => $value) {
			$ctl->assign($key, $value);
		}
	}

	private function validateMember(Controller $ctl, array $row, bool $new): array {
		$errors = [];
		if ($row["name"] === "") {
			$errors["name"] = "Enter your name.";
		}
		if ($row["email"] === "" || !filter_var($row["email"], FILTER_VALIDATE_EMAIL)) {
			$errors["email"] = "Enter a valid email.";
		} else if ($new && $this->memberByEmail($ctl, $row["email"]) !== []) {
			$errors["email"] = "This email is already registered.";
		}
		return $errors;
	}

	private function memberByEmail(Controller $ctl, string $email): array {
		if ($email === "") {
			return [];
		}
		$rows = $ctl->db("service_member")->select("email", $email);
		return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
	}

	private function currentMember(Controller $ctl): array {
		$id = (int) ($ctl->get_session(self::MEMBER_SESSION) ?? 0);
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("service_member")->get($id);
		return is_array($row) ? $row : [];
	}

	private function requireMember(Controller $ctl): array {
		$member = $this->currentMember($ctl);
		if ($member !== []) {
			return $member;
		}
		$this->assignFrame($ctl, "Login", [
			"email" => "",
			"error" => "Please login or create an account first.",
			"submit_url" => $ctl->get_APP_URL("public_service", "login_exe"),
		]);
		$ctl->show_public_pages("login.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
		return [];
	}

	private function activePlans(Controller $ctl): array {
		$rows = [];
		foreach ($ctl->db("service_plan")->getall("sort", SORT_ASC) as $plan) {
			if ((string) ($plan["status"] ?? "") !== "active") {
				continue;
			}
			$plan["subscribe_url"] = $ctl->get_APP_URL("public_service", "subscribe");
			$plan["id_enc"] = $ctl->encrypt((string) ($plan["id"] ?? 0));
			$rows[] = $plan;
		}
		return $rows;
	}

	private function plan(Controller $ctl, int $id): array {
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("service_plan")->get($id);
		if (!is_array($row) || (string) ($row["status"] ?? "") !== "active") {
			return [];
		}
		return $row;
	}

	private function validResetToken(Controller $ctl, string $token): array {
		if ($token === "") {
			return [];
		}
		$rows = $ctl->db("service_password_reset")->select("token", $token);
		$row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
		if ($row === [] || (int) ($row["used_at"] ?? 0) > 0 || (int) ($row["expires_at"] ?? 0) < time()) {
			return [];
		}
		return $row;
	}

	private function recordPaidSubscription(Controller $ctl, array $member, array $plan, string $squareCustomerId, string $squareCardId): int {
		$this->closeActiveSubscriptions($ctl, (int) ($member["id"] ?? 0));
		$now = time();
		$periodEnd = strtotime("+1 month", $now);
		$subscriptionId = (int) $ctl->db("service_subscription")->insert([
			"service_member_id" => (int) ($member["id"] ?? 0),
			"service_plan_id" => (int) ($plan["id"] ?? 0),
			"status" => "active",
			"current_period_start" => $now,
			"current_period_end" => $periodEnd,
			"square_customer_id" => $squareCustomerId,
			"square_card_id" => $squareCardId,
			"created_at" => $now,
			"updated_at" => $now,
		]);
		return (int) $ctl->db("service_payment")->insert([
			"service_member_id" => (int) ($member["id"] ?? 0),
			"service_subscription_id" => $subscriptionId,
			"service_plan_id" => (int) ($plan["id"] ?? 0),
			"amount" => (int) ($plan["price"] ?? 0),
			"payment_status" => "paid",
			"square_customer_id" => $squareCustomerId,
			"square_card_id" => $squareCardId,
			"paid_at" => $now,
			"created_at" => $now,
		]);
	}

	private function activateFreeSubscription(Controller $ctl, array $member, array $plan): void {
		$this->closeActiveSubscriptions($ctl, (int) ($member["id"] ?? 0));
		$now = time();
		$ctl->db("service_subscription")->insert([
			"service_member_id" => (int) ($member["id"] ?? 0),
			"service_plan_id" => (int) ($plan["id"] ?? 0),
			"status" => "active",
			"current_period_start" => $now,
			"current_period_end" => strtotime("+1 month", $now),
			"created_at" => $now,
			"updated_at" => $now,
		]);
	}

	private function closeActiveSubscriptions(Controller $ctl, int $memberId): void {
		foreach ($ctl->db("service_subscription")->select("service_member_id", $memberId) as $subscription) {
			if ((string) ($subscription["status"] ?? "") !== "active") {
				continue;
			}
			$subscription["status"] = "cancelled";
			$subscription["cancelled_at"] = time();
			$subscription["updated_at"] = time();
			$ctl->db("service_subscription")->update($subscription);
		}
	}

	private function currentSubscription(Controller $ctl, int $memberId): array {
		$current = [];
		foreach ($ctl->db("service_subscription")->select("service_member_id", $memberId) as $row) {
			if ((string) ($row["status"] ?? "") !== "active") {
				continue;
			}
			if ((int) ($row["current_period_end"] ?? 0) < time()) {
				continue;
			}
			if ($current === [] || (int) ($row["id"] ?? 0) > (int) ($current["id"] ?? 0)) {
				$current = $row;
			}
		}
		return $current;
	}

	private function memberPayments(Controller $ctl, int $memberId): array {
		$rows = $ctl->db("service_payment")->select("service_member_id", $memberId);
		return is_array($rows) ? array_reverse($rows) : [];
	}

	private function lastPayment(Controller $ctl, int $memberId): array {
		$id = (int) ($ctl->get_session(self::LAST_PAYMENT_SESSION) ?? 0);
		if ($id <= 0) {
			return [];
		}
		$row = $ctl->db("service_payment")->get($id);
		return is_array($row) && (int) ($row["service_member_id"] ?? 0) === $memberId ? $row : [];
	}

	private function showError(Controller $ctl, string $message): void {
		$this->assignFrame($ctl, "Error", ["message" => $message]);
		$ctl->show_public_pages("error.tpl", "_service_head.tpl", "_service_header.tpl", "_service_footer.tpl");
	}
}
