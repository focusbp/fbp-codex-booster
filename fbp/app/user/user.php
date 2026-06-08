<?php

class user {

	private $arr_status = array(0 => "Valid", 1 => "Invalid");
	private $arr_user_type = array(0 => "Admin", 1 => "User");
	private $user_type_opt_colors = array(0=>"#4BA3FF",1=>"#ebb000");
	private $developer_permission_opt = [
	    0 => "Not Allowed",
	    1 => "Developer Permission",
	];

	private $data_manager_permission_opt = [
	    0 => "Not Allowed",
	    1 => "Data Manager Permission",
	];
	private $password_reset_token_ttl = 259200;
	private $ffm;
	private $fmt_constant_array;
	private $fmt_values;

	function __construct(Controller $ctl) {
		$ctl->assign("arr_status", $this->arr_status);
		$ctl->assign("user_type_opt",$this->arr_user_type);
		$ctl->assign("user_type_opt_colors",$this->user_type_opt_colors);
		$ctl->assign("developer_permission_opt",$this->developer_permission_opt);
		$ctl->assign("data_manager_permission_opt",$this->data_manager_permission_opt);
		$this->ffm = $ctl->db("user");
		$this->fmt_constant_array = $ctl->db("constant_array","constant_array");
		$this->fmt_values = $ctl->db("values","constant_array");
	}

	function append(Controller $ctl) {

		$ctl->show_multi_dialog("user_add", "append.tpl", $ctl->t("user.dialog.add"), 800, true, true);
	}

	function append_exe(Controller $ctl) {
		$c = $ctl->POST();
		$c["status"] = 0;
		$c["login_id"] = $c["login_id"] ?? "";
		$c["email"] = $c["email"] ?? "";
		$login_id = $c["login_id"];
		$email = $c["email"];

		$flg = true;

		if (empty($c["login_id"])) {
			$flg = false;
			$ctl->assign("err_login_id", $ctl->t("user.validation.login_id_required"));
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$flg = false;
			$ctl->assign("err_email", $ctl->t("validation.email.invalid"));
		}
			
		//check whether login id is tacken or not
		//重複チェック
		if ($flg) {
			$list = $this->ffm->getall();
			foreach ($list as $d) {
				if (($d["login_id"] ?? "") == $login_id) {
					$flg = false;
					$ctl->assign("err_login_id", $ctl->t("user.validation.login_id_unavailable"));
				}
			}
		}

		if ($flg) {
			$insert_data = $c;
			$insert_data["password"] = $this->create_placeholder_password_hash();
			$insert_data["flg_password_change_required"] = 1;
			$insert_data["password_reset_token_hash"] = "";
			$insert_data["password_reset_token_expires_at"] = 0;
			$insert_data["password_reset_token_sent_at"] = 0;
			$id = $this->ffm->insert($insert_data);
			$ctl->close_multi_dialog("user_add");
			$ctl->ajax("user", "page");
			$this->show_account_invite_compose_dialog($ctl, (int) $id);
		} else {
			$ctl->assign("data", $ctl->POST());
			$ctl->show_multi_dialog("user_add", "append.tpl", $ctl->t("user.dialog.add"), 800, true, true);
		}
	}

	function edit(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->ffm->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("user_edit", "edit.tpl", $ctl->t("user.dialog.edit"), 800, true, true);
	}

	function edit_exe(Controller $ctl) {
		$c = $ctl->POST();
		$c["email"] = $c["email"] ?? "";
		$email = $c["email"];
		$flg = true;
		$id = (int) $ctl->POST("id");
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->assign("err_type", $ctl->t("user.validation.user_not_found"));
			$this->edit($ctl);
			return;
		}
		if ($email !== "") {
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$flg = false;
				$ctl->assign("err_email", $ctl->t("validation.email.invalid"));
			}
		}
		if ($this->is_oldest_user_id($id) && (int) ($data["type"] ?? 1) === 0 && (int) ($c["type"] ?? 1) !== 0) {
			$flg = false;
			$ctl->assign("err_type", $ctl->t("user.validation.primary_admin_type_locked"));
		}

		if (!$flg) {
			$edit_data = $data;
			foreach ($ctl->POST() as $key => $val) {
				$edit_data[$key] = $val;
			}
			$ctl->assign("data", $edit_data);
			$ctl->show_multi_dialog("user_edit", "edit.tpl", $ctl->t("user.dialog.edit"), 800, true, true);
			return;
		}

		foreach ($ctl->POST() as $key => $val) {
			if ($key === "password") {
				continue;
			}
			$data[$key] = $val;
		}
		
		$this->ffm->update($data);

		$ctl->close_multi_dialog("user_edit");
		$this->page($ctl);
	}

	private function is_oldest_user_id(int $id): bool {
		if ($id <= 0) {
			return false;
		}
		$list = $this->ffm->getall();
		$oldest_id = null;
		foreach ($list as $row) {
			$row_id = (int) ($row["id"] ?? 0);
			if ($row_id <= 0) {
				continue;
			}
			if ($oldest_id === null || $row_id < $oldest_id) {
				$oldest_id = $row_id;
			}
		}
		return $oldest_id !== null && $id === $oldest_id;
	}

	function delete(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm->get($id);
		if ($this->is_protected_oldest_admin($data)) {
			$ctl->show_notification_text($ctl->t("user.validation.primary_admin_delete_forbidden"));
			return;
		}
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("user_delete", "delete.tpl", $ctl->t("user.dialog.delete"), 800, true, true);
	}

	function delete_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm->get($id);
		if ($this->is_protected_oldest_admin($data)) {
			$ctl->show_notification_text($ctl->t("user.validation.primary_admin_delete_forbidden"));
			return;
		}
		$this->ffm->delete($id);
		$ctl->close_multi_dialog("user_delete");
		$ctl->ajax("user", "page");
	}

	private function is_protected_oldest_admin($data): bool {
		if (!is_array($data) || empty($data["id"])) {
			return false;
		}
		return $this->is_oldest_user_id((int) $data["id"]) && (int) ($data["type"] ?? 1) === 0;
	}

	function passchange(Controller $ctl) {
		$ctl->show_multi_dialog("change_password", "passchange.tpl", $ctl->t("user.dialog.change_password"), 800, true, true);
	}

	function passchange_exe(Controller $ctl) {
		$password = $ctl->POST("password");
		if ((string) $password === "") {
			$ctl->res_error_message("password", $ctl->t("password_reset.validation.password_required"));
			return;
		}
		$d = $this->ffm->get($ctl->get_session("user_id"));
		$d["password"] = $this->hash_password((string) $password);
		if (array_key_exists("flg_password_change_required", $d)) {
			$d["flg_password_change_required"] = 0;
		}
		if (array_key_exists("password_reset_token_hash", $d)) {
			$d["password_reset_token_hash"] = "";
		}
		if (array_key_exists("password_reset_token_expires_at", $d)) {
			$d["password_reset_token_expires_at"] = 0;
		}
		if (array_key_exists("password_reset_token_sent_at", $d)) {
			$d["password_reset_token_sent_at"] = 0;
		}
		$this->ffm->update($d);
		$ctl->close_multi_dialog("change_password");
	}

	function password_reset(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->res_error_message("id", $ctl->t("user.validation.user_not_found"));
			return;
		}
		if (!$this->has_valid_email($data)) {
			$this->show_password_reset_email_dialog($ctl, $data);
			return;
		}
		$this->show_password_reset_compose_dialog($ctl, $id);
	}

	function password_reset_email_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$email = trim((string) $ctl->POST("email"));
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->res_error_message("email", $ctl->t("user.validation.user_not_found"));
			return;
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$data["email"] = $email;
			$ctl->assign("err_email", $ctl->t("validation.email.invalid"));
			$this->show_password_reset_email_dialog($ctl, $data);
			return;
		}

		$data["email"] = $email;
		$this->ffm->update($data);
		$ctl->close_multi_dialog($this->password_reset_email_dialog_name($id));
		$this->show_password_reset_compose_dialog($ctl, $id);
	}

	function password_reset_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->res_error_message("id", $ctl->t("user.validation.user_not_found"));
			return;
		}
		try {
			$this->send_account_invite($ctl, (int) $data["id"]);
		} catch (Throwable $e) {
			$ctl->res_error_message("email", $this->get_account_invite_error_message($ctl, $e, $data));
			return;
		}
		$ctl->close_multi_dialog("user_password_reset_" . $id);
		$ctl->show_notification_text($ctl->t("user.notification.password_setup_link_sent"));
	}

	function account_invite_send_exe(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$dialog_name = (string) ($post["dialog_name"] ?? "");
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			$ctl->res_error_message("body", $ctl->t("user.validation.user_not_found"));
			return;
		}
		if (empty($data["email"]) || !filter_var((string) $data["email"], FILTER_VALIDATE_EMAIL)) {
			$ctl->res_error_message("body", $ctl->t("validation.email.invalid"));
			return;
		}
		if (trim((string) ($post["subject"] ?? "")) === "") {
			$ctl->res_error_message("subject", $ctl->t("validation.required"));
			return;
		}
		if (trim((string) ($post["body"] ?? "")) === "") {
			$ctl->res_error_message("body", $ctl->t("validation.required"));
			return;
		}
		try {
			$ctl->send_mail_text((string) $data["email"], (string) ($post["subject"] ?? ""), (string) ($post["body"] ?? ""), null, true);
		} catch (Throwable $e) {
			$ctl->res_error_message("body", $this->get_account_invite_error_message($ctl, $e, $data));
			return;
		}
		if ($dialog_name !== "") {
			$ctl->close_multi_dialog($dialog_name);
		}
		$notify_text = trim((string) ($post["notify_text"] ?? ""));
		if ($notify_text !== "") {
			$ctl->show_notification_text($notify_text);
		}
	}

	function page(Controller $ctl) {

		$search_word = $ctl->POST("search_word");
		$ctl->assign("search_word", $search_word);
		
		// ajax-auto 
		$max = $ctl->increment_post_value("max", 3);  // increment by 3
		$user_list = $this->ffm->filter(["login_id", "name"], [$search_word, $search_word], false, "OR", "id", SORT_DESC, $max, $is_last);
		$ctl->assign("max", $max);
		$ctl->assign("is_last", $is_last);

		$ctl->assign("user_list", $user_list);
		
		//url
		$login_url = $ctl->get_APP_URL("login","page");
		$ctl->assign("login_url", $login_url);

		$ctl->show_main_area("index.tpl", $ctl->t("user.page_title"));
	}

	function fr_verification_mail_send(Controller $ctl) {
		$post = $ctl->POST();
		$rand = rand(10000, 99999);
		$body = "Please enter this code and click submit, $rand";
		$subject = "Verify email";
		$ctl->send_mail_string('noreply@focus-business-platform.com', (string) ($post['email'] ?? ''), $subject, $body);
		echo json_encode(['key' => $ctl->encrypt($rand)]);
	}

	function fr_verification_mail_verify(Controller $ctl) {
		$post = $ctl->POST();
		$dkey = $ctl->decrypt((string) ($post['key'] ?? ''));
		if ((string) ($post['code'] ?? '') == $dkey) {
			echo json_encode(['status' => 1]);
		} else {
			echo json_encode(['status' => 0]);
		}
	}
	
	function image_sample(Controller $ctl){
		$ctl->res_image("images", "sample.png");
	}
	
	function upload_csv(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$code_list = ["UTF-8"=>"UTF-8(Exported from Google SpreadSheet/Mac)","win"=>"SJIS-win(Exported from Windows Excel)"];
		$ctl->assign("code_list",$code_list);
		$setting = $ctl->get_setting();
		$ctl->assign("date_format", !empty($setting["date_format"]) ? (string) $setting["date_format"] : "Y/m/d");
		$ctl->show_multi_dialog("upload_csv", "upload_csv.tpl", $ctl->t("user.dialog.upload_csv"), 800);
	}
	
	
	function upload_csv_confirm(Controller $ctl){
		
		if (!$ctl->is_posted_file("users_csv")){
			if (empty($post["users_csv"])){
				$errors["users_csv"] = $ctl->t("validation.required");
			}
			$ctl->assign("errors",$errors);
			$this->upload_csv($ctl);
			return;
		}
		
		$ctl->save_posted_file("users_csv", "users_csv.csv");
		$filepath = $ctl->get_saved_filepath("users_csv.csv");

		//open saved file
		$fp = fopen($filepath,"r");

		//set encoding for japanese
		if($ctl->POST("code") == "win"){
			stream_filter_register("convert.mbstring.*", "Stream_Filter_Mbstring");
			$filter_name = 'convert.mbstring.encoding.SJIS-win:UTF-8';
			stream_filter_append($fp, $filter_name, STREAM_FILTER_READ);
		}

		

		$formatter = $ctl->create_ValueFormatter();
		//read each line as csv
		$first = true;
		$list = [];
		$next_flg=true;
		while (($row = fgetcsv($fp, 0, ",", "\"", "")) !== false){
			
			$errors = [];
			
			if ($first){
				$first = false;
				continue;
			}

			if (empty($row[0])){
				$errors[] = $ctl->t("user.validation.csv_name_required");
			}
			if (empty($row[1])){
				$errors[] = $ctl->t("user.validation.csv_email_required");
			}
			if (!filter_var($row[1], FILTER_VALIDATE_EMAIL)){
				$errors[] = $ctl->t("validation.email.invalid");
			}
			$users = $this->ffm->select(["login_id"], [$row[1]], true);
			if(count($users) > 0){
				$errors[] = $ctl->t("user.validation.csv_email_duplicated");
			}
			
			$rec = [
			    "errors" => $errors,
			    "name" => $row[0],
			    "email" => $row[1]
			];
			
			if(count($errors)>0){
				$next_flg=false;
			}

			$list[] = $rec;


		}
		
		$ctl->set_session("userlist", $list);
		$ctl->assign("list",$list);
		$ctl->assign("next_flg",$next_flg);
		
		$ctl->show_multi_dialog("upload_csv", "upload_confirm.tpl", $ctl->t("user.dialog.upload_csv"), 800);

		fclose($fp);
	}
	
	
	function upload_csv_exe(Controller $ctl) {
		
		$formatter = $ctl->create_ValueFormatter();
		$list = $ctl->get_session("userlist");

		foreach($list as $rec){
			$insert_data=[
				'name'=>$rec["name"],
				'email'=>$rec["email"],
				'status' => 0,
				'login_id' => $rec["email"],
				'password' => $this->create_placeholder_password_hash(),
				'flg_password_change_required' => 1,
				'type' => 1, //member
				'date_join' => $formatter->format_date(time()),
				'created_at' => time(),
				'password_reset_token_hash' => "",
				'password_reset_token_expires_at' => 0,
				'password_reset_token_sent_at' => 0,
			];
			$id = $this->ffm->insert($insert_data);

			try{
				$this->send_account_invite($ctl, (int) $id);
			}catch(Exception $e){
				echo $e;
			}
		}
		
		$ctl->assign("count",count($list));
		$ctl->show_multi_dialog("upload_csv", "upload_finish.tpl", $ctl->t("user.dialog.upload_csv"), 800);
		$ctl->ajax("user","page");
	}

	private function hash_password($password) {
		$hash = password_hash((string) $password, PASSWORD_DEFAULT);
		if (!is_string($hash) || $hash === "") {
			throw new Exception("Failed to hash password.");
		}
		return $hash;
	}

	private function create_placeholder_password_hash(): string {
		return $this->hash_password(bin2hex(random_bytes(24)));
	}

	private function send_account_invite(Controller $ctl, int $id): void {
		$mail = $this->build_account_invite_mail($ctl, $id);
		$ctl->send_mail_text((string) $mail["email"], (string) $mail["subject"], (string) $mail["body"], null, true);
	}

	private function has_valid_email(array $data): bool {
		return !empty($data["email"]) && filter_var((string) $data["email"], FILTER_VALIDATE_EMAIL);
	}

	private function password_reset_email_dialog_name(int $id): string {
		return "user_password_reset_email_" . $id;
	}

	private function show_password_reset_email_dialog(Controller $ctl, array $data): void {
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog(
			$this->password_reset_email_dialog_name((int) $data["id"]),
			"password_reset_email.tpl",
			$ctl->t("user.dialog.set_reset_email"),
			600,
			true,
			true
		);
	}

	private function show_password_reset_compose_dialog(Controller $ctl, int $id): void {
		try {
			$this->show_account_invite_compose_dialog(
				$ctl,
				$id,
				"user_password_reset_" . $id,
				$ctl->t("user.dialog.reset_password"),
				$ctl->t("user.notification.password_setup_link_sent")
			);
		} catch (Throwable $e) {
			$data = $this->ffm->get($id);
			$ctl->res_error_message("email", $this->get_account_invite_error_message($ctl, $e, is_array($data) ? $data : []));
		}
	}

	private function show_account_invite_compose_dialog(Controller $ctl, int $id, string $dialog_name = "", string $dialog_title = "", string $notify_text = ""): void {
		$mail = $this->build_account_invite_mail($ctl, $id);
		if ($dialog_name === "") {
			$dialog_name = "user_account_invite_" . $id;
		}
		if ($dialog_title === "") {
			$dialog_title = $ctl->t("user.dialog.account_invite_compose");
		}
		$ctl->assign("dialog_name", $dialog_name);
		$ctl->assign("notify_text", $notify_text);
		$ctl->assign("data", $mail["data"]);
		$ctl->assign("send_to_email", $mail["email"]);
		$ctl->assign("subject", $mail["subject"]);
		$ctl->assign("body", $mail["body"]);
		$ctl->show_multi_dialog($dialog_name, "account_invite_compose.tpl", $dialog_title, 800, true, true);
	}

	private function build_account_invite_mail(Controller $ctl, int $id): array {
		$data = $this->ffm->get($id);
		if (!is_array($data) || empty($data["id"])) {
			throw new Exception("User not found.");
		}
		if (empty($data["email"]) || !filter_var((string) $data["email"], FILTER_VALIDATE_EMAIL)) {
			throw new Exception("Valid email is required.");
		}

		$token = bin2hex(random_bytes(24));
		$expires_at = time() + (int) $this->password_reset_token_ttl;
		$data["password_reset_token_hash"] = hash("sha256", $token);
		$data["password_reset_token_expires_at"] = $expires_at;
		$data["password_reset_token_sent_at"] = time();
		$data["flg_password_change_required"] = 1;
		$this->ffm->update($data);

		$mail_data = $data;
		$mail_data["reset_url"] = $ctl->get_APP_URL("password_reset", "token_page", ["token" => $token]);
		$mail_data["reset_expires_at"] = $ctl->create_ValueFormatter()->format_datetime($expires_at);
		$setting = $ctl->get_setting();
		$ctl->assign("data", $mail_data);
		$ctl->assign("setting", $setting);
		$dirs = new Dirs();
		$template_path = $dirs->get_class_dir("user") . "/Templates/default_account_invite.tpl";

		return [
			"data" => $mail_data,
			"email" => (string) $data["email"],
			"subject" => $ctl->t("user.email.account_invite_subject"),
			"body" => $ctl->fetch_string((string) file_get_contents($template_path)),
		];
	}

	private function get_account_invite_error_message(Controller $ctl, Throwable $e, array $data = []): string {
		if (empty($data["email"]) || !filter_var((string) $data["email"], FILTER_VALIDATE_EMAIL)) {
			return $ctl->t("validation.email.invalid");
		}

		$message = trim((string) $e->getMessage());
		if ($message === "") {
			return "Failed to send email.";
		}

		return $message;
	}

	private function current_origin() {
		if (!empty($_SERVER["HTTP_ORIGIN"])) {
			return (string) $_SERVER["HTTP_ORIGIN"];
		}
		$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
		$host = $_SERVER["HTTP_HOST"] ?? "";
		return $scheme . $host;
	}
}
