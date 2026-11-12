<?php

class public_page_links {
	function page(Controller $ctl) {
		$ctl->assign("items", $ctl->get_management_public_page_links());
		$ctl->show_multi_dialog("public-page-links", "index.tpl", $ctl->t("public_page_links.title"), 760);
	}
}
