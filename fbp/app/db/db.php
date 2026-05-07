<?php

class db {

	private $fmt_db;
	private $fmt_db_fields;
	private $fmt_screen;
	private $fmt_screen_fields;
	private $type_opt = [
	    "text" => "Text"
	    , "number" => "Number(Integer)"
	    , "float" => "Number(Float)"
	    , "textarea" => "Textarea"
	    , "textarea_links" => "Textarea replaced html links"
	    , "markdown" => "Markdown"
	    , "dropdown" => "Dropdown"
	    , "checkbox" => "Checkbox"
	    , "radio" => "Radio"
	    , "date" => "Date"
	    , "datetime" => "Date & Time"
	    , "year_month" => "Year/Month"
	    , "color" => "Color"
	    , "file" => "File"
	    , "image" => "Image"
	    , "vimeo" => "Vimeo"
	];
	// Default length
	private $type_length = [
	    "text" => 255
	    , "number" => 24
	    , "float" => 24
	    , "textarea" => 1000
	    , "textarea_links" => 1000
	    , "markdown" => 1000
	    , "dropdown" => 24
	    , "checkbox" => 255
	    , "radio" => 3
	    , "date" => 15
	    , "datetime" => 15
	    , "year_month" => 15
	    , "color" => 15
	    , "file" => 24
	    , "image" => 24
	    , "vimeo" => 255
	];
	// Validation
	private $validation_opt = [
	    0 => "",
	    1 => "Required",
	];
	// Duplicate
	private $duplicate_check_opt = [
	    0 => "",
	    1 => "Disallow Duplicates",
	];
	private $display_format_opt = [
	    0 => "None",
	    1 => "Currency Format",
	    2 => "Number Format",
	];
	private $format_check_title_opt = [
	    '' => '',
	    'email' => 'Email',
	    'phone' => 'Phone Number',
	    'postal' => 'Postal Code',
	    'url' => 'URL',
	    'date_yyyy_mm_dd' => 'Date (YYYY/MM/DD)',
	    'alphanumeric' => 'Alphanumeric',
	    'alphabet_only' => 'Alphabet Only',
	    'numeric_only' => 'Numeric Only',
	    'any_characters' => 'Any Characters (Allows Letters, Numbers, and Symbols)',
	    'password_easy' => 'Password (Allows Any Combination of Characters)',
	    'password_hard' => 'Password (Requires All Character Types, Minimum 8 Characters)'
	];
	private $show_menu_opt = [
	    0 => "Hide",
	    1 => "Show"
	];
	private $menu_visibility_opt = [
	    0 => "All Users",
	    1 => "Admin Only"
	];
	private $sort_order_opt = [
	    4 => "ASC",
	    3 => "DESC"
	];
	private $list_type_opt = [
	    0 => "Search and Table",
	    1 => "Manual Sort",
	    2 => "Weekly Calendar"
	];
	private $screen_build_type_opt = [
	    0 => "Standard Screen",
	    1 => "Original Screen"
	];
	private $side_list_type_opt = [
	    0 => "Same as Main Screen",
	    1 => "Search and Table",
	    2 => "Manual Sort"
	];
	private $default_screen_list = [
	    "add",
	    "edit",
	    "delete",
	    "list",
	    "search",
	    "list_on_side",
	];
	private $show_id_opt = [
	    0 => "Hide",
	    1 => "Show"
	];
	private $show_duplicate_opt = [
	    0 => "Hide",
	    1 => "Show"
	];
	private $api_access_policy_opt = [
	    "add" => "Add",
	    "update" => "Update",
	    "select" => "Select",
	    "filter" => "Filter",
	    "login" => "Login",
	    "onpay" => "On Pay",
	];
	private $api_scope_opt = [
	    "none" => "None",
	    "user" => "User"
	];
	private $api_allow_delete_opt = [
	    0 => "Deny",
	    1 => "Allow"
	];
	private $show_icon_on_parent_list_opt = [
	    0 => "Show",
	    1 => "Hide"
	];
	private $dropdown_item_display_type_opt = [
	    "field" => "Field",
	    "template" => "Multiple Fields (Template)"
	];
	
	private $cascade_delete_flag_opt = [
	    0 => "Do not delete",
	    1 => "Cascade delete"
	];

	private function get_dropdown_display_field_candidates(): array {
		$candidates = [];
		$db_list = $this->fmt_db->getall("tb_name", SORT_ASC);
		foreach ($db_list as $db) {
			$table_name = $db["tb_name"] ?? "";
			if ($table_name === "") {
				continue;
			}
			$field_list = $this->fmt_db_fields->select("db_id", $db["id"], true, "AND", "sort", SORT_ASC);
			foreach ($field_list as $field) {
				$parameter_name = $field["parameter_name"] ?? "";
				if ($parameter_name === "" || $parameter_name === "id" || $parameter_name === "_id_enc") {
					continue;
				}
				$parameter_title = trim((string) ($field["parameter_title"] ?? ""));
				$candidates[$table_name][$parameter_name] = ($parameter_title !== "") ? $parameter_title : $parameter_name;
			}
		}
		return $candidates;
	}

	private function get_display_format_options(Controller $ctl): array {
		return [
			0 => $ctl->t("db.display_format.none"),
			1 => $ctl->t("db.display_format.currency"),
			2 => $ctl->t("db.display_format.number"),
		];
	}

	private function normalize_display_format(string $type, $value): int {
		if (!in_array($type, ["number", "float"], true)) {
			return 0;
		}
		$value = (int) $value;
		if (!in_array($value, [0, 1, 2], true)) {
			return 0;
		}
		return $value;
	}

	function __construct(Controller $ctl) {
		$this->fmt_db = $ctl->db("db");
		$this->fmt_db_fields = $ctl->db("db_fields");
		$this->fmt_screen = $ctl->db("screen");
		$this->fmt_screen_fields = $ctl->db("screen_fields");

		$ctl->assign('type_opt', $this->type_opt);
		$ctl->assign("validation_opt", $this->validation_opt);
		$ctl->assign("constant_array_opt", $ctl->get_all_constant_array_names(true));
		$ctl->assign("show_menu_opt", $this->show_menu_opt);
		$ctl->assign("menu_visibility_opt", $this->menu_visibility_opt);
		$ctl->assign("sort_order_opt", $this->sort_order_opt);
		$ctl->assign("screen_build_type_opt", $this->screen_build_type_opt);
		$ctl->assign("list_type_opt", $this->list_type_opt);
		$ctl->assign("side_list_type_opt", $this->side_list_type_opt);
		$ctl->assign("duplicate_check_opt", $this->duplicate_check_opt);
		$ctl->assign("display_format_opt", $this->get_display_format_options($ctl));
		$ctl->assign("format_check_title_opt", $this->format_check_title_opt);
		$ctl->assign("show_id_opt", $this->show_id_opt);
		$ctl->assign("show_duplicate_opt", $this->show_duplicate_opt);
		$ctl->assign("api_access_policy_opt", $this->api_access_policy_opt);
		$ctl->assign("api_scope_opt", $this->api_scope_opt);
		$ctl->assign("api_allow_delete_opt", $this->api_allow_delete_opt);
		$ctl->assign("show_icon_on_parent_list_opt", $this->show_icon_on_parent_list_opt);
		$ctl->assign("cascade_delete_flag_opt",$this->cascade_delete_flag_opt);
		$ctl->assign("dropdown_item_display_type_opt", $this->dropdown_item_display_type_opt);
		$ctl->assign("dropdown_display_field_candidates", $this->get_dropdown_display_field_candidates());
	}

	function get_parent_opt($my_id) {

		$list = $this->fmt_db->getall("sort", SORT_ASC);
		$opt = [0 => ""];
		foreach ($list as $key => $d) {
			if ($d["id"] != $my_id) {
				$opt[$d["id"]] = $d["tb_name"];
			}
		}
		return $opt;
	}

	function get_default_length(Controller $ctl) {
		$type = $ctl->POST("type");
		$arr = [
		    "length" => $this->type_length[$type]
		];
		$ctl->res_json($arr);
		exit;
	}

	//index page
	function page(Controller $ctl) {

		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$items = $this->fmt_db->getall("sort", SORT_ASC);
		$ctl->assign("items", $items);

		$ctl->assign("parents_opt", $this->get_parent_opt(null));

		//$ctl->show_main_area("db", "index.tpl", "Database");
		//$ctl->show_multi_dialog("db", "index.tpl", "Database", 800);
		$ctl->reload_area("#tabs-db", "index.tpl");

		// update FFM
		$ctl->ajax("db", "make_table_format");
	}

	//view add page
	function add(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);

		$ctl->assign("parents_opt", $this->get_parent_opt(null));

		$ctl->show_multi_dialog("add_db", "add.tpl", $ctl->t("db.dialog.add_table"), 600, true, true);
	}

	//save add data
	function add_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);

		//validation
		$errors = $this->validate_db_data($ctl, $post, "add");
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->add($ctl);
			return;
		}

		$data["sortkey"] = "id";
		$data["sort_order"] = 4; // SORT_ASC
		// Default
		$post["api_allow_domain"] = '*';
		$post["api_thanks_html"] = "<p>Thank you</p>";
		$post["menu_name"] = $post["tb_name"];
		$post["show_menu"] = 1;
		$post["sort_order"] = 3;
		$post["screen_build_type"] = isset($post["screen_build_type"]) ? (int)$post["screen_build_type"] : 0;
		$post["side_list_type"] = isset($post["side_list_type"]) ? (int)$post["side_list_type"] : 0;
		if ((int) ($post["screen_build_type"] ?? 0) === 1) {
			$post["list_type"] = 0;
			$post["side_list_type"] = 0;
		}

		$id = $this->fmt_db->insert($post);

		if (!empty($post['parent_tb_id'])) {
			$data['db_id'] = $id;
			$data['type'] = 'number';
			$data["length"] = $this->type_length[$data["type"]];
			$data['parameter_name'] = 'parent_id';
			$data['parameter_title'] = 'Parent ID';
			$this->fmt_db_fields->insert($data);
		}

		if ((int)($post["list_type"] ?? 0) === 2) {
			$this->ensure_weekly_calendar_fields((int)$id);
		}

		$ctl->close_multi_dialog("add_db");

		$ctl->invoke("show_menu", [], "base");

		$this->page($ctl);
	}

	//validation
	function validate_db_data(Controller $ctl, $post, $page) {
		$errors = [];
		$tb_name = $post["tb_name"] ?? "";
		$post_id = $post["id"] ?? null;

		if ($tb_name === "") {
			$errors["tb_name"] = $ctl->t("db.validation.table_name_required");
		}

		if ($tb_name !== "" && !preg_match('/^[a-z0-9_]+$/', $tb_name)) {
			$errors["tb_name"] = $ctl->t("db.validation.table_name_format");
		}

		// Duplicate error check
		$list = $this->fmt_db->getall();
		foreach ($list as $d) {
			if ($tb_name == ($d["tb_name"] ?? "")) {
				if ($post_id != ($d["id"] ?? null)) {
					$errors["tb_name"] = $ctl->t("db.validation.table_name_duplicated");
				}
			}
		}

		return $errors;
	}

	//view edit page
	function edit(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$mode_post = (string) ($post["mode"] ?? "");
		$screen_name = (string) ($post["screen"] ?? "");

		if ($mode_post === "screen") {
			$mode = "screen";
			$ctl->set_session("db_field_edit_mode", "screen");
			$ctl->set_session("db_field_edit_screen_name", $screen_name);
			$ctl->assign("show_reload_button", true);
		} else if ($mode_post === "database") {
			$mode = "database";
			$ctl->set_session("db_field_edit_mode", null);
		} else {
			// セッションから取る
			$mode = $ctl->get_session("db_field_edit_mode");
			$screen_name = $ctl->get_session("db_field_edit_screen_name");
			if ($mode == null) {
				$mode = "database";
			}
		}

		$data = $this->fmt_db->get($id);
		if (empty($data)) {
			$ctl->show_notification_text($ctl->t("db.notification.table_not_found") ?: "Table not found.");
			return;
		}

		if ($data["list_width"] == 0) {
			$data["list_width"] = 400;
		}

		if ($data["side_list_type"] === "" || $data["side_list_type"] === null) {
			$data["side_list_type"] = 0;
		}
		if ($data["screen_build_type"] === "" || $data["screen_build_type"] === null) {
			$data["screen_build_type"] = 0;
		}
		if ($data["show_search_id"] === "" || $data["show_search_id"] === null) {
			$data["show_search_id"] = 0;
		}
		if ($data["menu_visibility"] === "" || $data["menu_visibility"] === null) {
			$data["menu_visibility"] = 0;
		}

		if ($data["edit_width"] == 0) {
			$data["edit_width"] = 800;
		}
		if (empty($data["dropdown_item_display_type"])) {
			$data["dropdown_item_display_type"] = "field";
		}
		if (empty($data["dropdown_item_template"])) {
			$data["dropdown_item_template"] = "";
		}

		$ctl->assign("data", $data);

		$parameters = $this->fmt_db_fields->select('db_id', $id, false, "AND", 'sort', SORT_ASC);

		// IDが入っているかチェック
		foreach ($parameters as &$param) {
			if ($param["type"] == "dropdown" || $param["type"] == "checkbox") {
				if (startsWith($param["constant_array_name"], "table")) {
					$param["option_list"] = [];
				} else {
					$param["option_list"] = $ctl->get_constant_array($param["constant_array_name"]);
				}
			}
		}

		$ctl->assign("parameters", $parameters);

		$sortkey_opt = ["id" => "ID"];
		$dropdown_item_opt = ["id" => "ID"];
		foreach ($parameters as $p) {
			// Sort field options
			// Allow only text or number
			if ($p["type"] == "text" || $p["type"] == "number") {
				$sortkey_opt[$p["parameter_name"]] = $p["parameter_name"];
			}

			// Identifer Field for child table
			if ($p["parameter_name"] != "parent_id") {
				// Allow only text or number
				if ($p["type"] == "text" || $p["type"] == "number") {
					$dropdown_item_opt[$p["parameter_name"]] = $p["parameter_name"];
				}
			}
		}
			$ctl->assign("sortkey_opt", $sortkey_opt);
			$ctl->assign("dropdown_item_opt", $dropdown_item_opt);

			$ctl->assign("parents_opt", $this->get_parent_opt($id));
			$child_tables = $this->fmt_db->select("parent_tb_id", $id, true, "AND", "sort", SORT_ASC);
			$ctl->assign("has_child_tables", count($child_tables) > 0);

			// Screen Dropdown
			$screen_list = $this->fmt_screen->select("tb_name", $data["tb_name"], true, "AND", "id", SORT_ASC);
		$screen_opt = [];

		// デフォルトのスクリーンを全て入れる
		$s = 0;
		foreach ($this->default_screen_list as $name) {
			$flg = true;
			foreach ($screen_list as $screen_row) {
				if ($name == $screen_row["screen_name"]) {
					$flg = false;
				}
			}
			$s++;
			if ($flg) {
				$arr = [
				    "tb_name" => $data["tb_name"],
				    "screen_name" => $name,
				    "sort" => $s
				];
				$this->fmt_screen->insert($arr);
			}
		}

		// 再取得
		$screen_list = $this->fmt_screen->select("tb_name", $data["tb_name"], true, "AND", "id", SORT_ASC);
		foreach ($screen_list as $key => $val) {
			if ($val["screen_name"] != "create_account") { // create_accountは廃止 20250930 中間
				$screen_opt[$val["id"]] = $val["screen_name"];
			}
		}
		$ctl->assign("screen_opt", $screen_opt);

		$screen_fields = $this->fmt_screen_fields->select("tb_name", $data["tb_name"], true, "AND", "id", SORT_ASC);
		if (count($screen_fields) == 0) {
			$ctl->assign("flg_change_tb_name", true);
		}

		$api_url = $ctl->get_APP_URL("api", "js");
		$ctl->assign("api_url", $api_url);
		$ctl->assign("api_embed_tag", $ctl->fetch("_api_url.tpl"));

		// update FFM
		$ctl->ajax("db", "make_table_format");

		if ($mode == "database") {
			$ctl->show_multi_dialog("edit_db", "edit.tpl", $ctl->t("db.dialog.edit_setting"), 1200, "_top.tpl", true);
			$ctl->invoke("list", ["tb_name" => $data["tb_name"], "target_area" => "#db_additionals_area", "reload_db_id" => $data["id"]], "db_additionals");
		} else {

			$screen_list = $this->fmt_screen->select(["tb_name", "screen_name"], [$data["tb_name"], $screen_name]);
			$screen = $screen_list[0] ?? null;
			if ($screen === null) {
				$ctl->show_notification_text("Screen not found.");
				return;
			}
			$ctl->assign("screen", $screen);

			$ctl->assign("child", $post["child"] ?? null);
			$ctl->assign("parent_id", $post["parent_id"] ?? null);

			$ctl->show_multi_dialog("edit_db", "_edit_field.tpl", $ctl->t("db.dialog.edit_setting"), 1200, "_top.tpl", true);
		}
	}

	//save edited data
	function edit_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);

		//validation
		$errors = $this->validate_db_data($ctl, $post, "edit");
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->edit($ctl);
			return;
		}

		$id = (int) ($post['id'] ?? 0);
		$data = $this->fmt_db->get($id);
		foreach ($_POST as $key => $value) {
			$data[$key] = $value;
		}
		$data["screen_build_type"] = isset($data["screen_build_type"]) ? (int) $data["screen_build_type"] : 0;
		$data["side_list_type"] = isset($data["side_list_type"]) ? (int) $data["side_list_type"] : 0;
		if ((int) ($data["screen_build_type"] ?? 0) === 1) {
			$data["list_type"] = 0;
			$data["side_list_type"] = 0;
		}
			if (empty($data["dropdown_item_display_type"])) {
				$data["dropdown_item_display_type"] = "field";
			}
			if ($data["dropdown_item_display_type"] !== "template") {
				$data["dropdown_item_template"] = "";
			}

		// Set menu_name as tb_name when it is empty.
		if ($data["show_menu"] == 1 && empty($data["menu_name"])) {
			$data["menu_name"] = $data["tb_name"];
		}

		$this->fmt_db->update($data);

		// Add or delete parent_id field.
		$list = $this->fmt_db_fields->select(["db_id", "parameter_name"], [$id, "parent_id"]);
		if (!empty($post['parent_tb_id'])) {
			if (count($list) == 0) {
				$f = [];
				$f['db_id'] = $id;
				$f['type'] = 'number';
				$f["length"] = $this->type_length[$f["type"]];
				$f['parameter_name'] = 'parent_id';
				$f['parameter_title'] = 'Parent ID';
				$this->fmt_db_fields->insert($f);
			}
		} else if (!empty($list)) {
			$this->fmt_db_fields->delete($list[0]["id"]);
		}

		// Manual Sort
		if ($data["list_type"] == 1) {
			$list2 = $this->fmt_db_fields->select(["db_id", "parameter_name"], [$id, "sort"]);
			if (count($list2) == 0) {
				$f = [];
				$f['db_id'] = $id;
				$f['type'] = 'number';
				$f["length"] = $this->type_length[$f["type"]];
				$f['parameter_name'] = 'sort';
				$f['parameter_title'] = 'Sort';
				$this->fmt_db_fields->insert($f);
			}
			$data["sortkey"] = "sort";
			$data["sort_order"] = 4;
			$this->fmt_db->update($data);
		}

		// Weekly Calendar
		if ($data["list_type"] == 2) {
			$this->ensure_weekly_calendar_fields($id);
			$this->fmt_db->update($data);
		}

		$ctl->close_multi_dialog("edit_db");
		$ctl->reload_menu();
		$ctl->reload_work_area();
	}

	function edit_silent_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);

		//validation
		$id = (int) ($post['id'] ?? 0);
		$data = $this->fmt_db->get($id);
		foreach ($_POST as $key => $value) {
			$data[$key] = $value;
		}

		// Scopeがユーザーの場合、api_user_id を追加する
		if (($post["api_scope"] ?? "") == "user") {
			$f_list = $ctl->db("db_fields")->select("db_id", $id);
			$check = false;
			foreach ($f_list as $f) {
				if ($f["parameter_name"] == "api_user_id") {
					$check = true;
					break;
				}
			}

			if (!$check) {
				$f = [
				    "db_id" => $id,
				    "parameter_name" => "api_user_id",
				    "parameter_title" => "API USER ID",
				    "type" => "number",
				    "length" => 24,
				];
				$ctl->db("db_fields")->insert($f);
			}
		}

		$this->fmt_db->update($data);
	}

	function screen_fields_area(Controller $ctl) {
		$post = $ctl->POST();

		$screen_id = (int) ($post["screen_id"] ?? 0);
		$ctl->assign("screen_id", $screen_id);
		$screen = $this->fmt_screen->get($screen_id);
		if (empty($screen)) {
			$ctl->reload_area("#screen_fields_area", "");
			return;
		}

		// Put to session
		$ctl->set_session("screen_name", $screen["screen_name"]);
		$ctl->set_session("screen_id", $screen["id"]);

		// Screen Fields List
		$screen_list = $this->fmt_screen_fields->select(["tb_name", "screen_name"], [$screen["tb_name"], $screen["screen_name"]], true, "AND", "sort", SORT_ASC);
		$screen_fields_arr = [];
		foreach ($screen_list as $key => $val) {
			// check if there is a row in db_fields
			$check_d = $this->fmt_db_fields->get($val["db_fields_id"]);
			if ($check_d == null) {
				$this->fmt_screen_fields->delete($val["id"]);
			} else {
				$screen_fields_arr[$val["id"]] = $check_d["parameter_title"];
			}
		}
		$ctl->assign("screen_fields_arr", $screen_fields_arr);

		$ctl->reload_area("#screen_fields_area", $ctl->fetch("_screen_fields.tpl"));
	}

	function add_to_screen(Controller $ctl) {

		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$field = $this->fmt_db_fields->get($id);
		if (empty($field)) {
			$ctl->show_notification_text("Field not found.");
			return;
		}

		$arr = [
		    "tb_name" => $post["tb_name"] ?? "",
		    "screen_name" => $ctl->get_session("screen_name"),
		    "parameter_name" => $field["parameter_name"],
		    "db_fields_id" => $field["id"],
		    "sort" => 9999
		];

		//validate
		$vlist = $this->fmt_screen_fields->select(["tb_name", "screen_name", "db_fields_id"], [$arr["tb_name"], $arr["screen_name"], $arr["db_fields_id"]], true, "AND", "sort", SORT_ASC);
		if (count($vlist) > 0) {
			$ctl->show_notification_text($ctl->t("db.notification.parameter_exists", ["parameter_name" => $arr["parameter_name"]]));
			return;
		}

		$this->fmt_screen_fields->insert($arr);

		// renumber sort number
		$list = $this->fmt_screen_fields->select(["tb_name", "screen_name"], [$arr["tb_name"], $arr["screen_name"]], true, "AND", "sort", SORT_ASC);
		$sort = 1;
		foreach ($list as $d) {
			$d["sort"] = $sort;
			$sort++;
			$this->fmt_screen_fields->update($d);
		}
		//var_dump($list);

		$ctl->ajax("db", "screen_fields_area",
			[
			    "screen_id" => $ctl->get_session("screen_id")
			]
		);
	}

	function sort_screen_field(Controller $ctl) {
		$post = $ctl->POST();

		$ex = explode(",", (string) ($post["log"] ?? ""));
		$sort = 0;
		foreach ($ex as $fid) {
			$f = $this->fmt_screen_fields->get($fid);
			$sort++;
			$f["sort"] = $sort;
			$this->fmt_screen_fields->update($f);
		}
	}

	function delete_screen_field(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$this->fmt_screen_fields->delete($id);
		$ctl->ajax("db", "screen_fields_area",
			[
			    "screen_id" => $ctl->get_session("screen_id")
			]
		);
	}

	//view delete page
	function delete(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->fmt_db->get($id);
		$ctl->assign("data", $data);		
		$ctl->show_multi_dialog("delete", "delete.tpl", $ctl->t("db.dialog.delete_table"), 500, true, true);
	}

	//delete data form database
	function delete_exe(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->fmt_db->get($id);
		$tb_name = (string) ($data["tb_name"] ?? "");
		if ($tb_name !== "") {
			$this->archive_table_dat_file($ctl, $tb_name);
		}

		$this->fmt_db->delete($id);

		//delete fields
		$list = $this->fmt_db_fields->select("db_id", $id);
		foreach ($list as $d) {
			$this->fmt_db_fields->delete($d["id"]);
		}

		//delete screen
		$list = $this->fmt_screen->select("tb_name", $data["tb_name"]);
		foreach ($list as $d) {
			$this->fmt_screen->delete($d["id"]);
		}

		//delete screen fields
		$list = $this->fmt_screen_fields->select("tb_name", $data["tb_name"]);
		foreach ($list as $d) {
			$this->fmt_screen_fields->delete($d["id"]);
		}

		//delete db_additionals
		$list = $ctl->db("additionals", "db_additionals")->select("tb_name", $tb_name);
		foreach ($list as $d) {
			$ctl->db("additionals", "db_additionals")->delete($d["id"]);
		}

		// Set 0 parent_tb_id
		$list = $this->fmt_db->select("parent_tb_id", $id);
		foreach ($list as $d) {
			$d["parent_tb_id"] = 0;
			$this->fmt_db->update($d);
		}

		$ctl->invoke("show_menu", [], "base");

		$ctl->close_multi_dialog("delete");
		$this->page($ctl);
	}

	private function archive_table_dat_file(Controller $ctl, string $tb_name): void {
		$dat_path = $ctl->dirs->datadir . "/common/" . $tb_name . ".dat";
		if (!is_file($dat_path)) {
			return;
		}

		$bak_path = $ctl->dirs->datadir . "/common/" . $tb_name . "-" . date("Ymd_His") . ".bak";
		$seq = 2;
		while (is_file($bak_path)) {
			$bak_path = $ctl->dirs->datadir . "/common/" . $tb_name . "-" . date("Ymd_His") . "_" . $seq . ".bak";
			$seq++;
		}

		if (!rename($dat_path, $bak_path)) {
			throw new Exception("Failed to archive dat file: " . $dat_path);
		}
	}

	function sort(Controller $ctl) {
		$post = $ctl->POST();
		$logArr = explode(',', (string) ($post['log'] ?? ''));
		$c = 1;
		foreach ($logArr as $id) {
			$d = $this->fmt_db->get($id);
			$d['sort'] = $c;
			$this->fmt_db->update($d);
			$c++;
		}
	}

	function sort_fields(Controller $ctl) {
		$post = $ctl->POST();
		$logArr = explode(',', (string) ($post['log'] ?? ''));
		$c = 1;
		foreach ($logArr as $id) {
			$d = $this->fmt_db_fields->get($id);
			$d['sort'] = $c;
			$this->fmt_db_fields->update($d);
			$c++;
		}
	}

	//view add page
	function add_fields(Controller $ctl) {
		$post = $ctl->POST();
		$post['db_id'] = $post['id'];
		$post["image_width"] = 200;
		$post["image_width_thumbnail"] = 50;
		$post["title_color"] = $ctl->get_session("title_color");
		$post["display_format"] = 0;
		$ctl->assign('post', $post);
		$ctl->show_multi_dialog("add_db_fields", "add_fields.tpl", $ctl->t("db.dialog.add_parameters"), 1000, true, true);
	}

	//save add data
	function add_fields_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$parameter_name = $post["parameter_name"] ?? "";
		$db_id = $post["db_id"] ?? null;
		$type = $post["type"] ?? "";

		//validation
		if ($parameter_name === "") {
			$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_required"));
		}

		if ($parameter_name !== "" && !preg_match('/^[a-z0-9_]+$/', $parameter_name)) {
			$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_format"));
		}

		// Duplicate error check
		$list = $this->fmt_db_fields->select("db_id", $db_id);
		foreach ($list as $d) {
			if ($parameter_name == ($d["parameter_name"] ?? "")) {
				$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_duplicate"));
			}
		}

		if ($parameter_name == "api_user_id") {
			$ctl->res_error_message("parameter_name", $ctl->t("db.validation.api_user_id_prohibited"));
		}

		// check prohibition_item_name
		foreach (array_merge($this->fmt_db_fields->get_prohibition_items(), ["id"]) as $name) {
			if ($parameter_name == $name) {
				$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_prohibited"));
			}
		}

		// title
		if (empty($post["parameter_title"])) {
			$ctl->res_error_message("parameter_title", $ctl->t("db.validation.parameter_title_required"));
		}

		$constant_array_name = $post["constant_array_name"] ?? "";
		$display_fields_for_dropdown = trim((string) ($post["display_fields_for_dropdown"] ?? ""));
		$is_table_only = false;
		if (startsWith($constant_array_name, "table/")) {
			$table_and_field = substr($constant_array_name, 6);
			$ex = explode("/", $table_and_field);
			$is_table_only = (count($ex) === 1);
		}
		if ($is_table_only && in_array($type, ["dropdown", "checkbox", "radio"], true)) {
			if ($display_fields_for_dropdown === "") {
				$ctl->res_error_message("display_fields_for_dropdown", $ctl->t("db.validation.display_fields_required"));
			}
		}

		if ($ctl->count_res_error_message() > 0) {
			return;
		}


		// Set default length
		$post["length"] = $this->type_length[$type];
		$post["display_format"] = $this->normalize_display_format((string) $type, $post["display_format"] ?? 0);

		// sortを調べる
			$db_id = $post["db_id"] ?? null;
		$list = $this->fmt_db_fields->select("db_id", $db_id);
		$sort = 0;
		foreach ($list as $f) {
			if ($f["sort"] > $sort) {
				$sort = $f["sort"] + 1;
			}
		}
		$post["sort"] = $sort;

		if (!$is_table_only && !startsWith($constant_array_name, "table/")) {
			$post["display_fields_for_dropdown"] = "";
		}

		$this->fmt_db_fields->insert($post);

			$ctl->set_session("title_color", $post["title_color"] ?? null);

		//close adding page
		$ctl->close_multi_dialog("add_db_fields");
		$ctl->ajax("db", "edit", ["id" => $db_id]);
	}

	function add_login_fields(Controller $ctl) {
		$post = $ctl->POST();
		$db_id = $post["id"] ?? null;
		$sort = 100;
		$arr = [];
		$arr[] = [
		    "db_id" => $db_id,
		    "parameter_name" => "password",
		    "parameter_title" => "Password",
		    "type" => "text",
		    "length" => 255,
		    "validation" => 1,
		    "duplicate_check" => 0,
		    "format_check" => "password_easy",
		    "sort" => $sort++
		];
		$arr[] = [
		    "db_id" => $db_id,
		    "parameter_name" => "name",
		    "parameter_title" => "Name",
		    "type" => "text",
		    "length" => 255,
		    "validation" => 1,
		    "duplicate_check" => 0,
		    "sort" => $sort++
		];
		$arr[] = [
		    "db_id" => $db_id,
		    "parameter_name" => "email",
		    "parameter_title" => "Email",
		    "type" => "text",
		    "length" => 255,
		    "validation" => 1,
		    "duplicate_check" => 0,
		    "format_check" => "email",
		    "sort" => $sort++
		];
		$arr[] = [
		    "db_id" => $db_id,
		    "parameter_name" => "status",
		    "parameter_title" => "Status",
		    "type" => "dropdown",
		    "length" => 24,
		    "constant_array_name" => "workflows",
		    "sort" => $sort++
		];

		$sort = 0;
		foreach ($arr as $d) {
			// delete if the field exists
			$list = $this->fmt_db_fields->select(["db_id", "parameter_name"], [$db_id, $d["parameter_name"]]);
			foreach ($list as $c) {
				$this->fmt_db_fields->delete($c["id"]);
			}

			// insert
			$this->fmt_db_fields->insert($d);

			$sort++;
			if ($d["parameter_name"] == "password" || $d["parameter_name"] == "email" || $d["parameter_name"] == "name") {
				$db = $this->fmt_db->get($db_id);
				$sf = [];
				$sf["tb_name"] = $db["tb_name"];
				$sf["screen_name"] = "create_account";
				$sf["parameter_name"] = $d["parameter_name"];
				$sf["db_fields_id"] = $d["id"];
				$sf["sort"] = $sort;
				$this->fmt_screen_fields->insert($sf);
			}
		}

		$ctl->ajax("db", "edit", ["id" => $db_id]);
	}

	//view edit page
	function edit_fields(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign("post", $post);

		$data = $this->fmt_db_fields->get($post['id'] ?? null);

		if ($data["parameter_name"] == "api_user_id") {
			$ctl->show_notification_text($ctl->t("db.notification.api_user_id_edit_forbidden"));
			return;
		}

		$data = array_merge($data, $post);
		$data["display_format"] = $this->normalize_display_format((string) ($data["type"] ?? ""), $data["display_format"] ?? 0);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("edit_db_fields", "edit_fields.tpl", $ctl->t("db.dialog.edit_parameters"), 1000, true, true);
	}

	//save edited data
	function edit_fields_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$parameter_name = $post["parameter_name"] ?? "";
		$db_id = $post["db_id"] ?? null;
		$post_id = $post["id"] ?? null;
		$type = $post["type"] ?? "";

		//validation
		if ($parameter_name === "") {
			$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_required"));
		}

		if ($parameter_name !== "" && !preg_match('/^[a-z0-9_]+$/', $parameter_name)) {
			$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_format"));
		}

		// Duplicate error check
		$list = $this->fmt_db_fields->select("db_id", $db_id);
		foreach ($list as $d) {
			if ($parameter_name == ($d["parameter_name"] ?? "")) {
				if ($post_id != ($d["id"] ?? null)) {
					$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_duplicate"));
				}
			}
		}

		// check prohibition_item_name
		foreach ($this->fmt_db_fields->get_prohibition_items() as $name) {
			if ($parameter_name == $name) {
				$ctl->res_error_message("parameter_name", $ctl->t("db.validation.parameter_name_prohibited"));
			}
		}

		// title
		if (empty($post["parameter_title"])) {
			$ctl->res_error_message("parameter_title", $ctl->t("db.validation.parameter_title_required"));
		}

		$constant_array_name = $post["constant_array_name"] ?? "";
		$display_fields_for_dropdown = trim((string) ($post["display_fields_for_dropdown"] ?? ""));
		$is_table_only = false;
		if (startsWith($constant_array_name, "table/")) {
			$table_and_field = substr($constant_array_name, 6);
			$ex = explode("/", $table_and_field);
			$is_table_only = (count($ex) === 1);
		}
		if ($is_table_only && in_array($type, ["dropdown", "checkbox", "radio"], true)) {
			if ($display_fields_for_dropdown === "") {
				$ctl->res_error_message("display_fields_for_dropdown", $ctl->t("db.validation.display_fields_required"));
			}
		}

		// length
		if (empty($post["length"])) {
			$ctl->res_error_message("length", $ctl->t("db.validation.parameter_length_required"));
		}

		if ($ctl->count_res_error_message() > 0) {
			return;
		}

		$data = $this->fmt_db_fields->get($post_id);
		foreach ($_POST as $key => $value) {
			$data[$key] = $value;
		}
		$data["display_format"] = $this->normalize_display_format((string) ($data["type"] ?? ""), $post["display_format"] ?? 0);

		if (!in_array($type, ["dropdown", "checkbox", "radio"])) {
			$data["constant_array_name"] = "";
		}

		if (!$is_table_only && !startsWith($constant_array_name, "table/")) {
			$data["display_fields_for_dropdown"] = "";
		}

		if (empty($post["api_access_policy"])) {
			$data["api_access_policy"] = [];
		}


		$this->fmt_db_fields->update($data);

		$ctl->close_multi_dialog("edit_db_fields");
		//$this->page($ctl);
		$ctl->ajax("db", "edit", ["id" => $data['db_id']]);
	}

	//view delete page
	function delete_fiedls(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->fmt_db_fields->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("delete_fiedls", "delete_fiedls.tpl", $ctl->t("db.dialog.delete_parameters"), 500, true, true);
	}

	//delete data form database
	function delete_fiedls_exe(Controller $ctl) {
		$id = $ctl->POST("id");
		//file delete
		$data = $this->fmt_db_fields->get($id);

		$table = $this->fmt_db->get($data["db_id"]);
		if ($table["api_scope"] == "user" && $data["parameter_name"] == "api_user_id") {
			$ctl->show_notification_text($ctl->t("db.notification.api_user_id_delete_forbidden"));
			return;
		}

		//deleting child data		
		$this->fmt_db_fields->delete($id);
		$ctl->close_multi_dialog("delete_fiedls");

		$list = $this->fmt_screen_fields->select("db_fields_id", $id);
		foreach ($list as $d) {
			$this->fmt_screen_fields->delete($d["id"]);
		}

		$ctl->ajax("db", "edit", ["id" => $data['db_id']]);
	}

	// make table format
	function make_table_format(Controller $ctl) {

		$dirs = new Dirs();
		$dir = $dirs->get_class_dir("common") . "/fmt/";

		// ディレクトリ作成・既存ファイル削除
		if (is_dir($dir)) {
			$files = glob($dir . '*'); // ディレクトリ内のすべてのファイルを取得
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file); // ファイルを削除
				}
			}
		} else {
			mkdir($dir);
		}

		$tables = $this->fmt_db->getall("sort", SORT_ASC);

		foreach ($tables as $table) {
			$db_id = $table["id"];

			$txt = "id,24,N\n";

			$fields = $this->fmt_db_fields->select("db_id", $db_id, true, "AND", "sort", SORT_ASC);
			foreach ($fields as $field) {
				$t = "";
				if ($field["type"] == "number" || $field["type"] == "dropdown" || $field["type"] == "radio" || $field["type"] == "datetime" || $field["type"] == "date"
				) {
					$t = "N";
				} else if ($field["type"] == "float") {
					$t = "F";
				} else if ($field["type"] == "checkbox") {
					$t = "A";
				} else {
					$t = "T";
				}


				$txt .= $field["parameter_name"] . "," . $field["length"] . "," . $t . "\n";
			}
			file_put_contents($dir . $table["tb_name"] . ".fmt", $txt);
		}
	}

	function view_image(Controller $ctl) {
		$image_file = $ctl->GET("file");
		$ctl->res_saved_image($image_file);
	}

	function download_file(Controller $ctl) {
		$filename = $ctl->POST("filename");
		//var_dump($filename);
		$ctl->res_saved_file($filename);
	}

	function add_screen(Controller $ctl) {
		$post = $ctl->POST();

		if (!empty($post["screen_name"])) {
			$screen_list = $this->fmt_screen->select(["tb_name", "screen_name"], [$post["tb_name"] ?? "", $post["screen_name"] ?? ""]);
			if (count($screen_list) > 0) {
				$ctl->res_error_message("screen_name", $ctl->t("db.validation.screen_name_duplicate"));
			}

			if (!preg_match('/^[a-z0-9_]+$/', $post["screen_name"] ?? "")) {
				$ctl->res_error_message("screen_name", $ctl->t("db.validation.screen_name_format"));
			}

			if ($ctl->count_res_error_message() > 0) {
				return;
			}

			// Add the screen name to the table
			$screen = [];
			$screen["tb_name"] = $post["tb_name"] ?? "";
			$screen["screen_name"] = $post["screen_name"] ?? "";
			$this->fmt_screen->insert($screen);

			// reload
			$arr = [
			    "id" => $post["db_id"] ?? null,
			    "screen_id" => $screen["id"]
			];
			$ctl->ajax("db", "edit", $arr);
		} else {
			$ctl->res_error_message("screen_name", $ctl->t("db.validation.screen_name_required"));
			return;
		}
	}

	function delete_screen(Controller $ctl) {
		$post = $ctl->POST();

		$screen_id = $post["screen_id"] ?? null;

		$d = $this->fmt_screen->get($screen_id);

		$ctl->assign("post", $post);
		$ctl->assign("data", $d);

		$ctl->show_multi_dialog("delete_screen", "delete_screen.tpl", $ctl->t("db.dialog.delete_screen"));
	}

	function delete_screen_exe(Controller $ctl) {
		$post = $ctl->POST();

		$screen_id = $post["screen_id"] ?? null;

		$d = $this->fmt_screen->get($screen_id);
		$screen_name = $d["screen_name"];

		foreach ($this->default_screen_list as $default) {
			if ($screen_name == $default) {
				$ctl->res_error_message("screen_name", $ctl->t("db.validation.default_screen_delete_forbidden"));
			}
		}
		if ($ctl->count_res_error_message() > 0) {
			return;
		}

		$this->fmt_screen->delete($screen_id);
		$list = $this->fmt_screen_fields->select("screen_name", $screen_name);
		foreach ($list as $key => $val) {
			$this->fmt_screen_fields->delete($val["id"]);
		}

		// reload
		$arr = [
		    "id" => $post["db_id"] ?? null,
		    "screen_id" => null
		];
		$ctl->ajax("db", "edit", $arr);
		$ctl->close_multi_dialog("delete_screen");
	}

	function reload_option(Controller $ctl) {
		$data = [];
		$data["constant_array_name"] = $ctl->POST("constant_array_name");
		$ctl->assign("data", $data);
		$ctl->reload_area("#area_option", "_area_option.tpl");
	}

	function open_options_dialog(Controller $ctl) {
		$ctl->invoke("page", [], "constant_array");
	}

	function update_api_access_policy(Controller $ctl) {
		$post = $ctl->POST();

		$id = $post["id"] ?? null;
		$api_access_policy_arr = $post["api_access_policy"] ?? [];

		// ["0"=>"add"] を ["add"] に変換
		$api_access_policy = [];
		if (!empty($api_access_policy_arr)) {
			foreach ($api_access_policy_arr as $a) {
				$api_access_policy[] = $a;
			}
		}

		$f = $ctl->db("db_fields")->get($id);
		$f["api_access_policy"] = $api_access_policy;
		$ctl->db("db_fields")->update($f);
	}

	function set_all_field(Controller $ctl) {
		$post = $ctl->POST();
		$screen_id = $post["screen_id"] ?? null;
		$id = $post["id"] ?? null;

		$db = $ctl->db("db")->get($id);
		$screen = $ctl->db("screen")->get($screen_id);
		$list = $ctl->db("db_fields")->select("db_id", $id, true, "AND", "sort", SORT_ASC);

		$sort = 0;
		foreach ($list as $f) {

			if ($f["parameter_name"] == "parent_id") {
				continue;
			}

			$check = $ctl->db("screen_fields")->select(["tb_name", "screen_name", "parameter_name"],
				[$db["tb_name"], $screen["screen_name"], $f["parameter_name"]]);
			$sort++;
			if (count($check) == 0) {
				$sf = [
				    "tb_name" => $db["tb_name"],
				    "screen_name" => $screen["screen_name"],
				    "parameter_name" => $f["parameter_name"],
				    "db_fields_id" => $f["id"],
				    "sort" => $sort
				];
				$ctl->db("screen_fields")->insert($sf);
			} else {
				$ff = $check[0];
				$ff["sort"] = $sort;
				$ctl->db("screen_fields")->update($ff);
			}
		}

		$ctl->ajax("db", "screen_fields_area",
			[
			    "screen_id" => $screen_id
			]
		);
	}

	function text_fields(Controller $ctl) {
		$post = $ctl->POST();
		$id = $post["id"] ?? null;

		$db = $ctl->db("db")->get($id);

		$field_list = $this->fmt_db_fields->select('db_id', $id, false, "AND", 'sort', SORT_ASC);

		$ctl->assign("db", $db);
		$ctl->assign("field_list", $field_list);

		$ctl->show_multi_dialog("text", "text_fields.tpl", "", 800);
	}

	function pdf_fields(Controller $ctl) {
		$post = $ctl->POST();
		$id = $post["id"] ?? null;

		$db = $ctl->db("db")->get($id);

		$field_list = $this->fmt_db_fields->select('db_id', $id, false, "AND", 'sort', SORT_ASC);

		$ctl->assign("db", $db);
		$ctl->assign("field_list", $field_list);

		$pdf = new pdfmaker_class();
		
		$pdf->setPageLayout(["orientation"=>"L"]);
		
		$pdf->addText($db["tb_name"] . "　" . $db["menu_name"], ["fontsize" => "16","underline"=>true]);

		$table = [];
		
		$table[] = [
		  "Title",
		    "Feild Name",
		    "Type",
		    "Size",
		    "Options",
		];
		
		foreach($field_list as $key=>$f){
			// ドロップダウンなどに選択肢がある場合
			if ($f["type"] === "dropdown" || $f["type"] == "checkbox") {
				
				$option_text = $f["constant_array_name"] . "\n";

				// 選択肢テーブルへアクセス
				$ffm_constant_array = $ctl->db("constant_array", "constant_array");
				$ffm_values = $ctl->db("values", "constant_array");

				// 定義済みの定数配列レコード取得
				$constantRows = $ffm_constant_array->select(['array_name'], [$f["constant_array_name"]]);
				if ($constantRows && isset($constantRows[0])) {
					$constant_array = $constantRows[0];

					// 実際のキー/バリュー一覧を並び順付きで取得
					$value_array = $ffm_values->select(
						'constant_array_id',
						$constant_array['id'],
						true,
						"AND",
						"sort",
						SORT_ASC
					);

					foreach ($value_array as $valRow) {
						$option_text .= $valRow["key"] . ":" . $valRow["value"] . "\n"; 					
					}
				} else {
					// 定義配列が見つからなかった場合
					$options= null;
				}
			}else{
				$option_text = "";
			}
			
			$row = [
			    $f["parameter_title"],
			    $f["parameter_name"],
			    $f["type"],
			    $f["length"],
			    $option_text,
			];
			$table[] = $row;
		}


		$pdf->addTable($table,[
			"margintop"=>10,
		    	"columnsize" => [25, 25, 10, 10, 30],
		    ]);
		$pdf->create_pdf();
	}

	private function ensure_weekly_calendar_fields($db_id) {
		$required = [
			[
				"parameter_name" => "datetime",
				"type" => "datetime",
				"parameter_title" => "Scheduled Date & Time",
				"default_value" => "",
			],
			[
				"parameter_name" => "duration",
				"type" => "number",
				"parameter_title" => "Duration(minutes)",
				"default_value" => 60,
			],
			[
				"parameter_name" => "travel_before",
				"type" => "number",
				"parameter_title" => "Travel Time Before (min)",
				"default_value" => 0,
			],
			[
				"parameter_name" => "travel_after",
				"type" => "number",
				"parameter_title" => "Travel Time After (min)",
				"default_value" => 0,
			],
			[
				"parameter_name" => "status",
				"type" => "dropdown",
				"parameter_title" => "Status",
				"default_value" => 0,
				"constant_array_name" => "workflows",
			],
		];

		foreach ($required as $def) {
			$list = $this->fmt_db_fields->select(["db_id", "parameter_name"], [$db_id, $def["parameter_name"]]);
			if (count($list) > 0) {
				continue;
			}
			$f = [];
			$f['db_id'] = $db_id;
			$f['type'] = $def["type"];
			$f["length"] = $this->type_length[$f["type"]];
			$f['parameter_name'] = $def["parameter_name"];
			$f['parameter_title'] = $def["parameter_title"];
			$f['validation'] = 0;
			$f['default_value'] = $def["default_value"];
			if (!empty($def["constant_array_name"])) {
				$f['constant_array_name'] = $def["constant_array_name"];
			}
			$this->fmt_db_fields->insert($f);
		}
	}
}
