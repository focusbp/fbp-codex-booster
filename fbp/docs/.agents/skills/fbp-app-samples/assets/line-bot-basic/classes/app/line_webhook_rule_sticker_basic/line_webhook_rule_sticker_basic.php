<?php

class line_webhook_rule_sticker_basic {

	function run(Controller $ctl) {
		return [
			"reply_sticker" => [
				"package_id" => "11537",
				"sticker_id" => "52002734",
			],
			"handled" => true,
		];
	}
}
