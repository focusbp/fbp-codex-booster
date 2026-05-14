<?php

class line_webhook_rule_follow_basic {

	function run(Controller $ctl) {
		$context = (array) ($ctl->get_session("line_webhook_context") ?? []);
		$displayname = trim((string) ($context["displayname"] ?? ""));
		$setting = (array) ($ctl->get_setting() ?? []);
		$greeting = trim((string) ($setting["line_bot_greeting_message"] ?? ""));

		$message = "Thanks for adding this LINE account.";
		if ($displayname !== "") {
			$message = $displayname . ", " . $message;
		}
		if ($greeting !== "") {
			$message .= "\n\n" . $greeting;
		}

		return [
			"reply_text" => $message,
			"handled" => true,
		];
	}
}
