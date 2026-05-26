<?php

class shop_public_link_dialog {

	function run(Controller $ctl): void {
		$ctl->assign("public_shop_url", $ctl->get_APP_URL("public_pages", "shop"));
		$ctl->show_multi_dialog("shop_public_link_dialog", "link.tpl", "Public EC Link", 600);
	}
}
