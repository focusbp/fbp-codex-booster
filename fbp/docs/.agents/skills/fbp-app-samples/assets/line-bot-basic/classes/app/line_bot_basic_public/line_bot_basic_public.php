<?php

class line_bot_basic_public {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function profile(Controller $ctl) {
		$line_member = $this->sync_line_member($ctl);
		if ($line_member === []) {
			$this->show_error($ctl, "LINE member information could not be found.");
			return;
		}

		$this->show_profile($ctl, $line_member);
	}

	function profile_save(Controller $ctl) {
		$line_member = $this->sync_line_member($ctl);
		if ($line_member === []) {
			$this->show_error($ctl, "LINE member information could not be found.");
			return;
		}

		$name = trim((string) ($ctl->POST("name") ?? ""));
		if ($name === "") {
			$this->show_profile($ctl, $line_member, "Enter your name.");
			return;
		}

		$line_member["name"] = $name;
		if (array_key_exists("updated_at", $line_member)) {
			$line_member["updated_at"] = time();
		}
		$ctl->db("line_member")->update($line_member);
		$ctl->set_session("line_bot_basic_line_member", $line_member);

		$this->assign_frame($ctl, "Profile Updated");
		$ctl->assign("line_member", $line_member);
		$ctl->assign("profile_url", $ctl->get_APP_URL("line_bot_basic_public", "profile"));
		$ctl->show_public_pages("profile_saved.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function show_profile(Controller $ctl, array $line_member, string $error = ""): void {
		$this->assign_frame($ctl, "LINE Profile");
		$ctl->assign("line_member", $line_member);
		$ctl->assign("profile_error", $error);
		$ctl->assign("profile_save_url", $ctl->get_APP_URL("line_bot_basic_public", "profile_save"));
		$ctl->show_public_pages("profile.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function sync_line_member(Controller $ctl): array {
		$id_enc = trim((string) ($ctl->GET("lm") ?? $ctl->POST("lm") ?? ""));
		if ($id_enc !== "") {
			$ctl->set_session("line_bot_basic_member_id_enc", $id_enc);
		} else {
			$id_enc = trim((string) ($ctl->get_session("line_bot_basic_member_id_enc") ?? ""));
		}
		if ($id_enc === "") {
			return [];
		}

		$id = (int) $ctl->decrypt($id_enc);
		if ($id <= 0) {
			return [];
		}

		$line_member = $ctl->db("line_member")->get($id);
		if (empty($line_member) || !is_array($line_member)) {
			return [];
		}

		$ctl->set_session("line_bot_basic_line_member", $line_member);
		return $line_member;
	}

	private function show_error(Controller $ctl, string $message): void {
		$this->assign_frame($ctl, "LINE Profile Error");
		$ctl->assign("message", $message);
		$ctl->show_public_pages("error.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl");
	}

	private function assign_frame(Controller $ctl, string $page_title): void {
		$ctl->assign("page_title", $page_title);
		$ctl->assign("app_name", "LINE Bot Basic");
	}
}
