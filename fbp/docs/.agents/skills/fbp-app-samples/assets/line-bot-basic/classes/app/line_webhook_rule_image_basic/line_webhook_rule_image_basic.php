<?php

class line_webhook_rule_image_basic {

	function run(Controller $ctl) {
		return [
			"reply_text" => "Image received. Thank you.",
			"handled" => true,
		];
	}
}
