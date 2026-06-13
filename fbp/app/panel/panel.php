<?php

/**
 * Description of panel
 *
 * @author nakama
 */
class panel {


	function __construct(Controller $ctl) {
	}

	function page(Controller $ctl) {
		$ctl->invoke("page", [], "db");
		$ctl->assign("panel_tab_labels", [
			"db" => $ctl->t("panel.tab.database"),
			"dashboard" => $ctl->t("panel.tab.dashboard"),
			"constants" => $ctl->t("panel.tab.constants"),
			"webhook" => $ctl->t("panel.tab.webhook"),
			"embed_app" => $ctl->t("panel.tab.embed_app"),
			"mcp_server" => $ctl->t("panel.tab.mcp_server"),
			"public_pages" => $ctl->t("panel.tab.public_pages"),
			"public_assets" => $ctl->t("panel.tab.public_assets"),
			"db_additionals" => $ctl->t("panel.tab.db_additionals"),
			"cron" => $ctl->t("panel.tab.cron"),
			"email_templates" => $ctl->t("panel.tab.email_templates"),
		]);
		$ctl->show_main_area("index.tpl", $ctl->t("panel.title"));
	}

	function release_backup(Controller $ctl) {
		$setting = $ctl->get_setting();
		$ctl->assign("setting", $setting);
		$ctl->assign("bcp_export_enabled", $ctl->is_app_admin());
		$ctl->assign("bcp_export_download_filename", $this->build_bcp_export_download_filename($setting));
		$ctl->show_main_area("release_backup.tpl", $ctl->t("panel.release_backup.title"));
	}

	private function build_bcp_export_download_filename(array $setting): string {
		$code = trim((string) ($setting["project_release_code"] ?? ""));
		if ($code === "") {
			$code = "system";
		}
		$code = preg_replace('/[^A-Za-z0-9._-]/', '_', $code);
		if ($code === "" || $code === null) {
			$code = "system";
		}
		return "bcp-system-" . $code . "-" . date("Ymd-His") . ".zip";
	}

	
}
