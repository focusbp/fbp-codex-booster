<?php

include_once(__DIR__ . "/../../../fbp/app/webhook_line/webhook_line.php");

class line_webhook extends webhook_line {

	function __construct(Controller $ctl) {
		parent::__construct($ctl);
	}

	function receive(Controller $ctl) {
		parent::receive($ctl);
	}
}
