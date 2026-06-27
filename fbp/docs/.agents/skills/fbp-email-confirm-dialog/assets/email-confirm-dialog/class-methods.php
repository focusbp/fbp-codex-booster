<?php

// Sample trait for an FBP Original Screen class.
// Copy methods into the class or use the trait after replacing placeholders.
// Replace YOUR_TABLE, YOUR_DIALOG_NAME, YOUR_LIST_AREA_ID, and email_confirm_* names.

trait fbp_email_confirm_dialog_sample {
private function email_confirm_session_key(int $row_id): string {
	return "YOUR_DIALOG_NAME_email_confirm_" . $row_id;
}

private function email_confirm_pending(Controller $ctl, int $row_id): array {
	$pending = $ctl->get_session($this->email_confirm_session_key($row_id));
	return is_array($pending) ? $pending : [];
}

private function save_email_confirm_pending(Controller $ctl, int $row_id, array $pending): void {
	$ctl->set_session($this->email_confirm_session_key($row_id), $pending);
}

private function clear_email_confirm_pending(Controller $ctl, int $row_id): void {
	$ctl->set_session($this->email_confirm_session_key($row_id), "");
}

private function email_confirm_body(string $code): string {
	return implode("\n", [
		"メールアドレス登録を受け付けました。",
		"",
		"確認コード: " . $code,
		"",
		"10分以内に画面へ確認コードを入力してください。",
		"このメールに心当たりがない場合は破棄してください。",
	]);
}

function email_confirm_dialog(Controller $ctl) {
	$id = (int) ($ctl->POST("id") ?? 0);
	$row = $this->get_target_row($ctl, $id);
	if ($row === []) {
		$ctl->show_notification_text("対象データが見つかりません。");
		return;
	}
	$ctl->assign("row", $row);
	$ctl->show_multi_dialog("YOUR_DIALOG_NAME_email_confirm", "email_confirm_input.tpl", "メールアドレス登録", 560);
}

function email_confirm_send_code(Controller $ctl) {
	$id = (int) ($ctl->POST("id") ?? 0);
	$row = $this->get_target_row($ctl, $id);
	if ($row === []) {
		$ctl->show_notification_text("対象データが見つかりません。");
		return;
	}

	$email = trim((string) ($ctl->POST("email") ?? ""));
	if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$ctl->res_error_message("email", "メールアドレスの形式を確認してください。");
		return;
	}

	$code = str_pad((string) random_int(0, 9999), 4, "0", STR_PAD_LEFT);
	try {
		$ctl->send_mail_text($email, "メールアドレス確認コード", $this->email_confirm_body($code), null, true);
	} catch (Throwable $e) {
		$ctl->res_error_message("email", "確認コードメールの送信に失敗しました。");
		return;
	}

	$this->save_email_confirm_pending($ctl, $id, [
		"email" => $email,
		"code_hash" => password_hash($code, PASSWORD_DEFAULT),
		"expires_at" => time() + 600,
		"attempts" => 0,
	]);

	$row["pending_email"] = $email;
	$ctl->assign("row", $row);
	$ctl->show_multi_dialog("YOUR_DIALOG_NAME_email_confirm", "email_confirm_verify.tpl", "メールアドレス登録", 560);
}

function email_confirm_verify(Controller $ctl) {
	$id = (int) ($ctl->POST("id") ?? 0);
	$row = $this->get_target_row($ctl, $id);
	if ($row === []) {
		$ctl->show_notification_text("対象データが見つかりません。");
		return;
	}

	$code = trim((string) ($ctl->POST("code") ?? ""));
	if (!preg_match('/^\d{4}$/', $code)) {
		$ctl->res_error_message("code", "4桁の確認コードを入力してください。");
		return;
	}

	$pending = $this->email_confirm_pending($ctl, $id);
	if ($pending === [] || (int) ($pending["expires_at"] ?? 0) < time()) {
		$this->clear_email_confirm_pending($ctl, $id);
		$ctl->res_error_message("code", "確認コードの有効期限が切れています。再度送信してください。");
		return;
	}

	$email = trim((string) ($pending["email"] ?? ""));
	if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$this->clear_email_confirm_pending($ctl, $id);
		$ctl->res_error_message("code", "確認中のメールアドレスを確認できませんでした。再度送信してください。");
		return;
	}

	$attempts = (int) ($pending["attempts"] ?? 0);
	if (!password_verify($code, (string) ($pending["code_hash"] ?? ""))) {
		$pending["attempts"] = $attempts + 1;
		$this->save_email_confirm_pending($ctl, $id, $pending);
		if ((int) $pending["attempts"] >= 5) {
			$this->clear_email_confirm_pending($ctl, $id);
			$ctl->res_error_message("code", "確認コードの入力回数が上限に達しました。再度送信してください。");
			return;
		}
		$ctl->res_error_message("code", "確認コードが一致しません。");
		return;
	}

	$row["email"] = $email;
	$row["email_verified_at"] = time();
	$row["updated_at"] = time();
	$ctl->db("YOUR_TABLE")->update($row);
	$this->clear_email_confirm_pending($ctl, $id);

	$ctl->close_multi_dialog("YOUR_DIALOG_NAME_email_confirm");
	$this->assign_common($ctl);
	$this->assign_list_area($ctl, $this->current_filter($ctl));
	$ctl->reload_area("YOUR_LIST_AREA_ID", "list_area.tpl");
	$ctl->show_notification_text("メールアドレスを登録しました。");
}
}
