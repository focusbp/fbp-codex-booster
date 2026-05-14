<?php

class line_webhook_rule_unmatch_basic {

	function run(Controller $ctl) {
		$context = (array) ($ctl->get_session("line_webhook_context") ?? []);
		$text = trim((string) ($context["text"] ?? ""));
		$userid = trim((string) ($context["userid"] ?? ""));
		if ($text !== "") {
			$ctl->log("[line bot basic unmatch] userid=" . $userid . " text=" . $text);
		}

		return [
			"reply_text" => "I could not match that message. Send \"Profile\" to open your member profile.",
			"handled" => true,
		];
	}
}
