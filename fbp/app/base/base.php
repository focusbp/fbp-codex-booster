<?php

class base {

	private $fmt_db;

	function __construct(Controller $ctl) {
		
		if($ctl->GET("function") == "img"){
			$ctl->set_check_login(false);
			return;
		}
		
		$login_type = $ctl->get_login_type();
		$ctl->assign("login_type", $login_type);

		$this->fmt_db = $ctl->db("db","db");
	}

	private function normalize_external_url($url) {
		$url = trim((string) $url);
		if ($url === "") {
			return "";
		}
		if (!preg_match('#^https?://#i', $url)) {
			return "";
		}
		return $url;
	}
	

	function page(Controller $ctl) {

		//プロジェクトのCSSとjsを全て読み込む
		$dirs = new Dirs();
		$js_class_list = array();

		$base_projectlist = scandir($dirs->appdir_fw);
		foreach ($base_projectlist as $key => $c) {
			if ($c == "." || $c == ".." || $c == ".htaccess" || $c == "base") {
				continue;
			}
			try {
				if (is_file($dirs->get_class_dir($c) . "/script.js")) {
					$js_class_list[] = $c;
				}
			} catch (Exception $e) {
				
			}
		}

		if(!is_dir($dirs->appdir_user)){
			mkdir($dirs->appdir_user);
		}
		$base_projectlist = scandir($dirs->appdir_user);
		foreach ($base_projectlist as $key => $c) {
			if ($c == "." || $c == ".." || $c == ".htaccess" || $c == "base") {
				continue;
			}
			try {
				if (!is_file($dirs->get_class_dir($c) . "/public")) {
					if (is_file($dirs->get_class_dir($c) . "/script.js")) {
						$js_class_list[] = $c;
					}
				}
			} catch (Exception $e) {
				
			}
		}
		$ctl->assign("js_class_list", $js_class_list);
		
		$setting = $ctl->get_setting();
		$project_portal_url = $this->normalize_external_url($setting["project_portal_url"] ?? "");
		$ctl->assign("setting",$setting);
		$ctl->assign("project_portal_url", $project_portal_url);
		$ctl->assign("base_i18n", [
			"app_name" => $ctl->t("base.app_name"),
			"tagline" => $ctl->t("base.tagline"),
			"dev_mode" => $ctl->t("base.dev_mode"),
			"download_release_file" => $ctl->t("base.download_release_file"),
			"debug" => $ctl->t("base.debug"),
			"menu_open" => $ctl->t("base.menu.open"),
			"dashboard" => $ctl->t("base.menu.dashboard"),
			"databases" => $ctl->t("base.menu.databases"),
			"public_side" => $ctl->t("base.menu.public_side"),
			"homepage" => $ctl->t("base.menu.homepage"),
			"admin_console" => $ctl->t("base.menu.admin_console"),
			"project_portal" => $ctl->t("base.menu.project_portal"),
			"development_panel" => $ctl->t("base.menu.development_panel"),
			"release_backup" => $ctl->t("base.menu.release_backup"),
			"user_management" => $ctl->t("base.menu.user_management"),
			"system_setting" => $ctl->t("base.menu.system_setting"),
		]);
		$ctl->assign("base_empty_i18n", [
			"no_items" => $ctl->t("base.empty_state.no_items"),
		]);
		
		// 初期のメールテンプレートを入れる
		$ffm_email_format = $ctl->db("email_format", "email_format");
		$email_format_list = $ffm_email_format->select("key", "account_created");
		if(count($email_format_list) ==0){
			$txt = file_get_contents(dirname(__FILE__) . "/Templates/default_email.tpl");
			$arr = array();
			$arr["key"] = "account_created";
			$arr["template_name"] = "Account Created";
			$arr["subject"] = "Your New Account Details";
			$arr["body"] = $txt;
			$ffm_email_format->insert($arr);
		}
		
		// htaccess
		if(!is_file(dirname(__FILE__) . "/../../../.htaccess")){
			$ctl->ajax("setting","update");
		}

		// メインエリア自動読み込み
		$show_empty_main_menu = false;
		$dashboard_widgets = $ctl->db("dashboard", "dashboard")->getall("sort", SORT_ASC);
		$has_dashboard = count($dashboard_widgets) > 0;
		$alma = $ctl->get_session("__AUTO_LOAD_MAIN_AREA");
		if (!empty($alma)) {
			try{
				$dir = new Dirs();
				$dir->get_class_dir($alma["class"]);
				$ctl->ajax($alma["class"], $alma["function"], $alma["parameters"]);
			} catch (Exception $ex) {
				if ($has_dashboard) {
					$ctl->ajax("dashboard", "page");
				} else {
					$show_empty_main_menu = true;
				}
			}
			
		}else{
			// スタートアップ
			$class = $setting["startup_class1"];
			$function = $setting["startup_function1"];
			if (empty($class) || empty($function)) {
				if ($has_dashboard) {
					$ctl->ajax("dashboard", "page");
				} else {
					$show_empty_main_menu = true;
				}
			} else {
				try {
					$dir = new Dirs();
					$dir->get_class_dir($class);
					$ctl->ajax($class, $function);
				} catch (Exception $ex) {
					if ($has_dashboard) {
						$ctl->ajax("dashboard", "page");
					} else {
						$show_empty_main_menu = true;
					}
				}
			}
		}
		$ctl->assign("show_empty_main_menu", $show_empty_main_menu);
		
		// Sliced fileの期限切れを削除
		$ffm_sliced_file = $ctl->db("sliced_file","upload");
		$list_sliced = $ffm_sliced_file->getall();
		foreach($list_sliced as $d){
			$expire_time = $d["time_created"] + 6 * 60 * 60;
			if($expire_time < time()){
				$pathname = $d["pathname"];
				$ctl->delete_saved_file($pathname);
				$ffm_sliced_file->delete($d["id"]);
			}
		}
		
		$this->assign_menu($ctl);
		$ctl->invoke("show_menu");
		

		$ctl->assign("pagetitle", "FOCUS Business Platform");
		$ctl->display("index.tpl");
	}
	
	function show_menu(Controller $ctl){
		
		$this->assign_menu($ctl);
		
		$ctl->reload_area("#menu", "left_menu.tpl");
	}
	
	function show_left_sidemenu(Controller $ctl) {

		$this->assign_menu($ctl);		
		
		$ctl->show_sidemenu("left_menu.tpl", 300, 200, "left");
	}

	private function get_table_visibility_filter(Controller $ctl, string $table_name) {
		if (!isset($ctl->dirs) || !is_object($ctl->dirs)) {
			return null;
		}
		$class_name = $table_name . "_visibility_filter";
		$file_path = $ctl->dirs->appdir_user . "/" . $class_name . "/" . $class_name . ".php";
		if (!is_file($file_path)) {
			return null;
		}
		require_once $file_path;
		if (!class_exists($class_name)) {
			return null;
		}
		return new $class_name();
	}

	private function can_show_database_menu(Controller $ctl, array $db): bool {
		$table_name = (string)($db["tb_name"] ?? "");
		if ($table_name === "") {
			return true;
		}
		$filter = $this->get_table_visibility_filter($ctl, $table_name);
		if ($filter === null) {
			return true;
		}
		if (method_exists($filter, "can_show_menu")) {
			return (bool) $filter->can_show_menu($ctl, $table_name, $db);
		}
		if (method_exists($filter, "can_access")) {
			return (bool) $filter->can_access($ctl, "menu", $table_name, $db);
		}
		return true;
	}
	
	private function assign_menu(Controller $ctl){
		// If there is a menu.tpl, put it into the side menu.
		$dir = new Dirs();
		$menu_file = $dir->appdir_user . "/common/menu.tpl";
		if (is_file($menu_file)) {
			$ctl->assign('menu_file', $menu_file);
		}
		
		$setting = $ctl->get_setting();
		$project_portal_url = $this->normalize_external_url($setting["project_portal_url"] ?? "");
		$homepage_url = $this->normalize_external_url($setting["website_url"] ?? "");
		$show_homepage_menu = ((int) ($setting["show_menu_homepage"] ?? 0) === 1 && $homepage_url !== "");
		$ctl->assign("setting",$setting);
		$ctl->assign("project_portal_url", $project_portal_url);
		$ctl->assign("homepage_url", $homepage_url);
		$ctl->assign("show_homepage_menu", $show_homepage_menu);
		$ctl->assign("base_menu_i18n", [
			"dashboard" => $ctl->t("base.menu.dashboard"),
			"databases" => $ctl->t("base.menu.databases"),
			"public_side" => $ctl->t("base.menu.public_side"),
			"homepage" => $ctl->t("base.menu.homepage"),
			"admin_console" => $ctl->t("base.menu.admin_console"),
			"project_portal" => $ctl->t("base.menu.project_portal"),
			"development_panel" => $ctl->t("base.menu.development_panel"),
			"release_backup" => $ctl->t("base.menu.release_backup"),
			"user_management" => $ctl->t("base.menu.user_management"),
			"system_setting" => $ctl->t("base.menu.system_setting"),
		]);
		
		// Database Menu
		$list = $this->fmt_db->select("show_menu",1,true,"AND","sort",SORT_ASC);
		$database_menu = [];
		$is_app_admin = $ctl->is_app_admin();
		foreach ($list as $db) {
			$menu_visibility = (int) ($db["menu_visibility"] ?? 0);
			if ($menu_visibility === 1 && !$is_app_admin) {
				continue;
			}
			if (!$this->can_show_database_menu($ctl, $db)) {
				continue;
			}
			$database_menu[] = $db;
		}
		$ctl->assign("database_menu",$database_menu);

		// Dashboard Menu
		$dashboard_widgets = $ctl->db("dashboard", "dashboard")->getall("sort", SORT_ASC);
		$show_dashboard_menu = count($dashboard_widgets) > 0 && $ctl->authorize_management_access("dashboard", "page");
		$ctl->assign("show_dashboard_menu", $show_dashboard_menu);
		
		// Homepage
		$root_url = $ctl->get_APP_URL();
		$ctl->assign("root_url",$root_url);

		$empty_main_sections = [];

		if (count($database_menu) > 0) {
			$items = [];
			foreach ($database_menu as $db) {
				$items[] = [
					"type" => "ajax",
					"label" => $db["menu_name"],
					"class" => "db_exe",
					"function" => "page",
					"attributes" => [
						"data-db_id" => $db["id"],
					],
				];
			}
			$empty_main_sections[] = [
				"title" => $ctl->t("base.menu.databases"),
				"items" => $items,
			];
		}

		$admin_items = [];
		$can_show_project_portal = $project_portal_url !== "" && $ctl->authorize_management_access("panel", "page");
		$can_show_development_panel = (
			$setting["force_testmode"] == 1 ||
			($setting["force_testmode"] == 0 && $setting["show_developer_panel"] == 1)
		) && $ctl->authorize_management_access("panel", "page");
		$can_show_release_backup = $ctl->authorize_management_access("panel", "release_backup");
		$can_show_user_management = $ctl->authorize_management_access("user", "page");
		$can_show_system_setting = $ctl->authorize_management_access("setting", "page");

		if ($can_show_project_portal) {
			$admin_items[] = [
				"type" => "external",
				"label" => $ctl->t("base.menu.project_portal"),
				"url" => $project_portal_url,
				"attributes" => [
					"target" => "_blank",
					"rel" => "noopener",
				],
			];
		}
		if ($can_show_development_panel) {
			$admin_items[] = [
				"type" => "ajax",
				"label" => $ctl->t("base.menu.development_panel"),
				"class" => "panel",
				"function" => "page",
				"attributes" => [],
			];
		}
		if ($can_show_release_backup) {
			$admin_items[] = [
				"type" => "ajax",
				"label" => $ctl->t("base.menu.release_backup"),
				"class" => "panel",
				"function" => "release_backup",
				"attributes" => [],
			];
		}
		if ($can_show_user_management) {
			$admin_items[] = [
				"type" => "ajax",
				"label" => $ctl->t("base.menu.user_management"),
				"class" => "user",
				"function" => "page",
				"attributes" => [],
			];
		}
		if ($can_show_system_setting) {
			$admin_items[] = [
				"type" => "ajax",
				"label" => $ctl->t("base.menu.system_setting"),
				"class" => "setting",
				"function" => "page",
				"attributes" => [],
			];
		}
		$ctl->assign("can_show_project_portal", $can_show_project_portal);
		$ctl->assign("can_show_development_panel", $can_show_development_panel);
		$ctl->assign("can_show_release_backup", $can_show_release_backup);
		$ctl->assign("can_show_user_management", $can_show_user_management);
		$ctl->assign("can_show_system_setting", $can_show_system_setting);
		$ctl->assign("show_admin_console_menu", count($admin_items) > 0);

		if (count($admin_items) > 0) {
			$empty_main_sections[] = [
				"title" => $ctl->t("base.menu.admin_console"),
				"items" => $admin_items,
			];
		}

		$ctl->assign("empty_main_sections", $empty_main_sections);
	}

	function img(Controller $ctl) {
		$ctl->res_image("images", $ctl->GET("file"));
	}

}
