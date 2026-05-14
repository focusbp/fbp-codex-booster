<?php

class line_webhook_rule_profile_basic {

	function run(Controller $ctl) {
		$context = (array) ($ctl->get_session("line_webhook_context") ?? []);
		$line_member = (array) ($context["line_member"] ?? []);
		$id = (int) ($line_member["id"] ?? 0);
		if ($id <= 0) {
			return [
				"reply_text" => "LINE member information could not be found.",
				"handled" => true,
			];
		}

		$url = $ctl->get_APP_URL("line_bot_basic_public", "profile", [
			"lm" => $ctl->encrypt($id),
		]);

		return [
			"reply_text" => "Open this URL to view or update your profile.\n" . $url,
			"handled" => true,
		];
	}
}
