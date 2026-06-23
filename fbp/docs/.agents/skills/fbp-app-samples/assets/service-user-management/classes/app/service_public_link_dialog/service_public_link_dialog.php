<?php

class service_public_link_dialog {
	function run(Controller $ctl) {
		$ctl->assign("public_service_url", $ctl->get_APP_URL("public_service", "plans"));
		$ctl->show_multi_dialog("link.tpl", "Public Service Link");
	}
}
