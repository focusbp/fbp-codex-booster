<?php


class db_exe {
	
	private $db_setting_id;
	private $fmt_db;
	private $table_name;
	private $ffm;
	private $db_setting;
	private $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
	private $window_name;
	private $title;
	private $access_denied = false;

	private function original_management_class_name(): string {
		return (string) $this->table_name . "_original_management";
	}

	private function original_management_file_path(): string {
		return dirname(__FILE__) . "/../../../classes/app/" . $this->original_management_class_name() . "/" . $this->original_management_class_name() . ".php";
	}

	private function invoke_original_management(Controller $ctl): bool {
		$class_name = $this->original_management_class_name();
		$file_path = $this->original_management_file_path();
		if (!is_file($file_path)) {
			$ctl->show_notification_text("Original management class not found: " . $class_name);
			return false;
		}
		$ctl->invoke("run", [], $class_name);
		return true;
	}

	private function original_management_has_function(string $function): bool {
		$class_name = $this->original_management_class_name();
		$file_path = $this->original_management_file_path();
		if (!is_file($file_path)) {
			return false;
		}
		require_once $file_path;
		if (!class_exists($class_name) || !method_exists($class_name, $function)) {
			return false;
		}
		$method = new ReflectionMethod($class_name, $function);
		return $method->isPublic();
	}

	private function invoke_original_management_function(Controller $ctl, string $function, array $post = []): bool {
		if (!$this->original_management_has_function($function)) {
			return false;
		}
		if ($function === "rows_child") {
			$post["_second_work_area_default_width"] = $this->get_side_panel_width();
			$ctl->invoke($function, $post, $this->original_management_class_name());
			return true;
		}
		$ctl->invoke($function, $post, $this->original_management_class_name());
		return true;
	}

	private function invoke_post_action_class(Controller $ctl, $data, $post_action_from = "", ?int $source_id = null, ?array $before_data = null){
		if(empty($this->db_setting["post_action_class"])){
			return;
		}
		$enc_id = $ctl->encrypt($data["id"]);
		$post = ["id"=>$enc_id];
		$post["_post_action_table"] = $this->table_name;
		if(!empty($post_action_from)){
			$post["_post_action_from"] = $post_action_from;
		}
		if(!empty($source_id)){
			$post["_post_action_source_id"] = $ctl->encrypt($source_id);
		}
		if(is_array($before_data)){
			$post["_post_action_before"] = json_encode($before_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		if(is_array($data)){
			$post["_post_action_after"] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		$ctl->invoke("run", $post, $this->db_setting["post_action_class"]);
	}

	function __construct(Controller $ctl) {
		$post = $ctl->POST();
		$get = $ctl->GET();
		$post_function = isset($post["function"]) ? $post["function"] : "";
		$get_function = isset($get["function"]) ? $get["function"] : "";
		
		
		// Pass the process making some instances
		if($post_function=="download_file" || $post_function == "view_image"){
			$ctl->set_check_login(false);
			return;
		}
		
		if($get_function=="view_image"){
			$ctl->set_check_login(false);
			return;
		}
		
		if($post_function=="close_second_work_area"){
			return;
		}
		
		$this->window_name = "window_" . $ctl->get_classname();
		
		// Getting db_id and check it
		$this->db_setting_id = !empty($post["db_id"]) ? $post["db_id"] : ($get["db_id"] ?? null);
		$ctl->assign("db_id",$this->db_setting_id);
		if(empty($this->db_setting_id)){
			throw new Exception("db_id is needed");
		}
		
		// Making database instances
		$this->fmt_db = $ctl->db("db","db");
		$db = $this->fmt_db->get($this->db_setting_id);
		$this->db_setting = $db;
		$this->table_name = $db["tb_name"];
		if($this->table_name == null){
			$ctl->stop_executing_function();
			return;
		}
		$this->ffm = $ctl->db($this->table_name);
		
		// Setting
		$this->title = $db["menu_name"];

		$function_name = $post_function !== "" ? $post_function : $get_function;
		if (!$this->check_table_access($ctl, $function_name)) {
			$this->deny_table_access($ctl);
			return;
		}
		
		$ctl->assign("tb_name",$this->table_name);
		
	}
	
	private function check_show_button($ctl,$table_name,$screen_name){
		$fl = $ctl->get_field_list($table_name,$screen_name);
		
		foreach($fl as $key=>$f){
			if($f["parameter_name"] == "parent_id"){
				unset($fl[$key]);
			}
		}
		
		if(count($fl) > 0){
			return true;
		}else{
			false;
		}
	}

	private function assign_properties_management_summary(Controller $ctl): void {
		$ctl->assign("show_properties_management_summary", false);
		if ($this->table_name !== "properties") {
			return;
		}

		$active_owner_ids = null;
		if ((int)($this->db_setting["parent_tb_id"] ?? 0) > 0) {
			$parent_db = $this->fmt_db->get($this->db_setting["parent_tb_id"]);
			if (($parent_db["tb_name"] ?? "") === "owner") {
				$active_owner_ids = [];
				$owner_rows = $ctl->db("owner")->filter([], []);
				foreach ((array)$owner_rows as $owner) {
					if ((string)($owner["sold_excluded"] ?? "0") === "1") {
						continue;
					}
					$owner_id = (string)($owner["id"] ?? "");
					if ($owner_id !== "") {
						$active_owner_ids[$owner_id] = true;
					}
				}
			}
		}

		$property_rows = $ctl->db("properties")->filter([], []);
		$management_count = 0;
		foreach ((array)$property_rows as $property) {
			if ((string)($property["status"] ?? "0") === "1") {
				continue;
			}
			if (is_array($active_owner_ids)) {
				$owner_id = (string)($property["parent_id"] ?? "");
				if ($owner_id === "" || !isset($active_owner_ids[$owner_id])) {
					continue;
				}
			}
			$management_count++;
		}

		$ctl->assign("show_properties_management_summary", true);
		$ctl->assign("properties_management_count", $management_count);
	}

	private function get_side_panel_list_type(): int {
		$side_type = isset($this->db_setting["side_list_type"]) ? (int)$this->db_setting["side_list_type"] : 0;
		if ($side_type === 0) {
			$main_type = isset($this->db_setting["list_type"]) ? (int)$this->db_setting["list_type"] : 0;
			return ($main_type === 0) ? 1 : 2;
		}
		return ($side_type === 1) ? 1 : 2;
	}

	private function get_side_panel_width(): int {
		$width = isset($this->db_setting["list_width"]) ? (int)$this->db_setting["list_width"] : 0;
		return $width > 0 ? $width : 400;
	}

	private function get_table_field_names(Controller $ctl): array {
		$fields = $ctl->db("db_fields", "db")->select("db_id", $this->db_setting_id, true, "AND", "sort", SORT_ASC);
		$names = ["id" => true];
		foreach ($fields as $field) {
			$name = (string)($field["parameter_name"] ?? "");
			if ($name !== "") {
				$names[$name] = true;
			}
		}
		return $names;
	}

	private function get_child_tables_for_parent_list(Controller $ctl, ?int $parent_db_id = null): array {
		$parent_db_id = $parent_db_id ?? (int) $this->db_setting_id;
		$child_tables = [];

		$legacy_children = $this->fmt_db->select("parent_tb_id", $parent_db_id, true, "AND", "sort", SORT_ASC);
		foreach ($legacy_children as $child) {
			$child["_parent_field_name"] = "parent_id";
			$child["_parent_db_id"] = $parent_db_id;
			$child["_parent_relation_type"] = "parent_id";
			$child_tables[] = $child;
		}

		$fields = $ctl->db("db_fields", "db")->select(
			["parent_relation_flag", "parent_db_id"],
			[1, $parent_db_id],
			true,
			"AND",
			"sort",
			SORT_ASC
		);
		foreach ($fields as $field) {
			$child_db_id = (int) ($field["db_id"] ?? 0);
			$parent_field = (string) ($field["parameter_name"] ?? "");
			if ($child_db_id <= 0 || $parent_field === "") {
				continue;
			}
			$child = $this->fmt_db->get($child_db_id);
			if (!is_array($child) || empty($child["tb_name"])) {
				continue;
			}
			$child["_parent_field_name"] = $parent_field;
			$child["_parent_db_id"] = $parent_db_id;
			$child["_parent_relation_field_id"] = $field["id"] ?? "";
			$child["_parent_relation_type"] = "dropdown_fk";
			$child["_parent_relation_title"] = $field["parameter_title"] ?: $parent_field;
			$child_tables[] = $child;
		}

		usort($child_tables, function ($a, $b) {
			$sort_a = (int) ($a["sort"] ?? 0);
			$sort_b = (int) ($b["sort"] ?? 0);
			if ($sort_a === $sort_b) {
				return strcmp((string) ($a["_parent_field_name"] ?? ""), (string) ($b["_parent_field_name"] ?? ""));
			}
			return $sort_a <=> $sort_b;
		});

		return $child_tables;
	}

	private function find_parent_relation_field(Controller $ctl, string $parent_field, int $parent_db_id = 0): ?array {
		if ($parent_field === "") {
			return null;
		}
		$rows = $ctl->db("db_fields", "db")->select(
			["db_id", "parameter_name"],
			[$this->db_setting_id, $parent_field],
			true,
			"AND",
			"id",
			SORT_ASC
		);
		foreach ($rows as $field) {
			if ((int) ($field["parent_relation_flag"] ?? 0) !== 1) {
				continue;
			}
			$field_parent_db_id = (int) ($field["parent_db_id"] ?? 0);
			if ($parent_db_id > 0 && $field_parent_db_id !== $parent_db_id) {
				continue;
			}
			return $field;
		}
		return null;
	}

	private function get_child_relation_context(Controller $ctl, array $post): ?array {
		$parent_id = isset($post["parent_id"]) ? (int) $post["parent_id"] : 0;
		if ($parent_id <= 0) {
			$ctl->res_error_message("parent_id", $ctl->t("db_exe.validation.parent_id_missing"));
			return null;
		}

		$parent_field = trim((string) ($post["parent_field"] ?? ""));
		if ($parent_field === "") {
			$parent_field = "parent_id";
		}

		$relation_field = null;
		if ($parent_field === "parent_id") {
			$db_parent = $this->fmt_db->get($this->db_setting["parent_tb_id"]);
		} else {
			$relation_field = $this->find_parent_relation_field($ctl, $parent_field, (int) ($post["parent_db_id"] ?? 0));
			if ($relation_field === null) {
				$ctl->show_notification_text($ctl->t("db_exe.validation.child_table_required"));
				return null;
			}
			$db_parent = $this->fmt_db->get((int) ($relation_field["parent_db_id"] ?? 0));
		}

		if (!is_array($db_parent) || empty($db_parent["tb_name"])) {
			$ctl->set_session("_side_panel", null);
			$ctl->show_notification_text($ctl->t("db_exe.validation.child_table_required"));
			return null;
		}

		return [
			"parent_id" => $parent_id,
			"parent_field" => $parent_field,
			"parent_db_id" => (int) ($db_parent["id"] ?? 0),
			"db_parent" => $db_parent,
			"relation_field" => $relation_field,
		];
	}

	private function get_manual_sort_fallback_fields(Controller $ctl): array {
		$field_names = $this->get_table_field_names($ctl);
		$candidates = [];
		$sortkey = trim((string)($this->db_setting["sortkey"] ?? ""));
		if ($sortkey !== "" && $sortkey !== "sort") {
			$candidates[] = $sortkey;
		}
		foreach ([
			"location_name_yomi",
			"name_yomi",
			"customer_name_yomi",
			"factory_name_yomi",
			"title_yomi",
			"location_name",
			"name",
			"customer_name",
			"factory_name",
			"title",
			"id",
		] as $candidate) {
			$candidates[] = $candidate;
		}

		$fields = [];
		$seen = [];
		foreach ($candidates as $candidate) {
			if (!isset($field_names[$candidate]) || isset($seen[$candidate])) {
				continue;
			}
			$fields[] = $candidate;
			$seen[$candidate] = true;
		}
		if (count($fields) === 0) {
			$fields[] = "id";
		}
		return $fields;
	}

	private function manual_sort_value($value): int {
		if ($value === null || $value === "") {
			return PHP_INT_MAX;
		}
		$sort = (int)$value;
		return $sort > 0 ? $sort : PHP_INT_MAX;
	}

	private function compare_manual_sort_fallback_value($a, $b): int {
		$a = trim((string)$a);
		$b = trim((string)$b);
		if ($a === "" && $b === "") {
			return 0;
		}
		if ($a === "") {
			return 1;
		}
		if ($b === "") {
			return -1;
		}
		if (is_numeric($a) && is_numeric($b)) {
			return ((float)$a) <=> ((float)$b);
		}
		$cmp = strnatcasecmp($a, $b);
		if ($cmp !== 0) {
			return $cmp;
		}
		return strcmp($a, $b);
	}

	private function sort_rows_for_manual_side_panel(Controller $ctl, array $rows): array {
		$fallback_fields = $this->get_manual_sort_fallback_fields($ctl);
		$fallback_direction = ((int)($this->db_setting["sort_order"] ?? SORT_ASC) === SORT_DESC) ? -1 : 1;
		$indexed_rows = [];
		foreach ($rows as $index => $row) {
			$indexed_rows[] = [
				"index" => $index,
				"row" => $row,
			];
		}
		usort($indexed_rows, function ($a, $b) use ($fallback_fields, $fallback_direction) {
			$sort_a = $this->manual_sort_value($a["row"]["sort"] ?? null);
			$sort_b = $this->manual_sort_value($b["row"]["sort"] ?? null);
			if ($sort_a !== $sort_b) {
				return $sort_a <=> $sort_b;
			}
			foreach ($fallback_fields as $field) {
				$cmp = $this->compare_manual_sort_fallback_value($a["row"][$field] ?? "", $b["row"][$field] ?? "");
				if ($cmp !== 0) {
					$value_a = trim((string)($a["row"][$field] ?? ""));
					$value_b = trim((string)($b["row"][$field] ?? ""));
					if ($value_a === "" || $value_b === "") {
						return $cmp;
					}
					return $cmp * $fallback_direction;
				}
			}
			return $a["index"] <=> $b["index"];
		});

		$sorted = [];
		foreach ($indexed_rows as $indexed_row) {
			$sorted[] = $indexed_row["row"];
		}
		return $sorted;
	}

	private function search_session_has_values($session): bool {
		if (!is_array($session)) {
			return false;
		}
		foreach ($session as $value) {
			if (is_array($value)) {
				foreach ($value as $nested_value) {
					if (trim((string)$nested_value) !== "") {
						return true;
					}
				}
				continue;
			}
			if (trim((string)$value) !== "") {
				return true;
			}
		}
		return false;
	}

	private function is_show_search_id_enabled(): bool {
		return (int) ($this->db_setting["show_search_id"] ?? 0) === 1;
	}

	private function build_search_id_field(): array {
		return [
			"parameter_name" => "id",
			"parameter_title" => "ID",
			"type" => "number",
			"length" => 24,
			"display_format" => 0,
		];
	}

	private function get_search_fields(Controller $ctl): array {
		$fields = $ctl->get_field_list($this->table_name, "search");
		if ($this->is_show_search_id_enabled()) {
			array_unshift($fields, $this->build_search_id_field());
		}
		return $fields;
	}

	private function get_visibility_filter(Controller $ctl) {
		if (!isset($ctl->dirs) || !is_object($ctl->dirs)) {
			return null;
		}
		$class_name = $this->table_name . "_visibility_filter";
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

	private function check_table_access(Controller $ctl, string $function_name): bool {
		$filter = $this->get_visibility_filter($ctl);
		if ($filter === null || !method_exists($filter, "can_access")) {
			return true;
		}
		return (bool) $filter->can_access($ctl, $function_name, $this->table_name, $this->db_setting);
	}

	private function deny_table_access(Controller $ctl): void {
		$this->access_denied = true;
		$ctl->show_notification_text("この画面の閲覧権限がありません。", 4, "#B42318", "#FFF", 18, 620);
		$ctl->stop_executing_function();
	}

	private function can_execute(Controller $ctl): bool {
		if (!$this->access_denied) {
			return true;
		}
		$ctl->stop_executing_function();
		return false;
	}

	private function append_visibility_filter_conditions(Controller $ctl, array &$search_field_list, array &$search_values, array &$search_match_patterns, string $context): void {
		$filter = $this->get_visibility_filter($ctl);
		if ($filter === null || !method_exists($filter, "get_filter_conditions")) {
			return;
		}
		$conditions = $filter->get_filter_conditions($ctl, $context);
		if (!is_array($conditions)) {
			return;
		}
		foreach ($conditions as $condition) {
			if (!is_array($condition) || empty($condition["field"])) {
				continue;
			}
			$search_field_list[] = (string)$condition["field"];
			$search_values[] = $condition["value"] ?? "";
			$search_match_patterns[] = (string)($condition["match_pattern"] ?? "=");
		}
	}

	private function normalize_timestamp_search_value($value) {
		if ($value === null) {
			return "";
		}
		if (is_numeric($value)) {
			$value = (string) $value;
			if (strlen($value) >= 13) {
				return (string) ((int) floor(((float) $value) / 1000));
			}
			return (string) ((int) $value);
		}
		$value = trim((string) $value);
		if ($value === "") {
			return "";
		}
		if (preg_match('/^\d{13,}$/', $value)) {
			return (string) ((int) floor(((float) $value) / 1000));
		}
		if (preg_match('/^\d+$/', $value)) {
			return (string) ((int) $value);
		}
		return "";
	}

	private function collect_search_session_from_post(Controller $ctl, array $post, bool $exclude_parent_id = false, string $exclude_field_name = ""): array {
		$fields = $this->get_search_fields($ctl);
		$search = [];
		foreach ($fields as $field) {
			$name = $field["parameter_name"] ?? "";
			if ($name === "") {
				continue;
			}
			if (($exclude_parent_id && $name === "parent_id") || ($exclude_field_name !== "" && $name === $exclude_field_name)) {
				continue;
			}
			$type = $field["type"] ?? "";
			if ($type === "datetime" || $type === "date") {
				$search[$name . "_from"] = $this->normalize_timestamp_search_value($post[$name . "_from"] ?? "");
				$search[$name . "_to"] = $this->normalize_timestamp_search_value($post[$name . "_to"] ?? "");
				continue;
			}
			if ($type === "year_month") {
				$search[$name] = $this->normalize_year_month_value($post[$name] ?? "");
				continue;
			}
			$search[$name] = $post[$name] ?? "";
		}
		return $search;
	}

	private function assign_search_group(Controller $ctl, string $group_name, bool $option_emptydata, bool $add_parent_dropdown): array {
		$ctl->assign_field_settings($group_name, $this->table_name, "search", $option_emptydata, $add_parent_dropdown);
		$assigned = $ctl->smarty->getTemplateVars($group_name);
		if (!is_array($assigned)) {
			$assigned = [];
		}
		if ($this->is_show_search_id_enabled()) {
			array_unshift($assigned, $this->build_search_id_field());
			$ctl->assign($group_name, $assigned);
		}
		return $assigned;
	}

	private function build_search_filter_parts(array $fields, $session): array {
		$search_field_list = [];
		$search_values = [];
		$search_match_patterns = [];
		foreach ($fields as $field) {
			$name = $field["parameter_name"] ?? "";
			if ($name === "") {
				continue;
			}
			$type = $field["type"] ?? "";
			if ($type === "datetime" || $type === "date") {
				$from_value = $this->normalize_timestamp_search_value($session[$name . "_from"] ?? "");
				if ($from_value !== "") {
					$search_field_list[] = $name;
					$search_values[] = $from_value;
					$search_match_patterns[] = ">=";
				}
				$to_value = $this->normalize_timestamp_search_value($session[$name . "_to"] ?? "");
				if ($to_value !== "") {
					$search_field_list[] = $name;
					$search_values[] = $to_value;
					$search_match_patterns[] = "<=";
				}
				continue;
			}
			$search_field_list[] = $name;
			$search_values[] = $session[$name] ?? "";
			$search_match_patterns[] = "=";
		}
		return [$search_field_list, $search_values, $search_match_patterns];
	}

	
	function page(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		if ((int) ($this->db_setting["screen_build_type"] ?? 0) === 1) {
			$this->invoke_original_management($ctl);
			return;
		}
		
		if($this->db_setting["list_type"] == 0){
			//List Type is "Search and Table"
			$search = $ctl->get_session("search_" . $this->table_name);
			$search_fields = $this->assign_search_group($ctl, "group1", true, true);
			if(count($search_fields)>0){
				$ctl->assign("show_search_box",true);
			}
			$ctl->assign("row",$search);
			$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
			
		}else if($this->db_setting["list_type"] == 1){
			//List Type is "Manual Sort"
			$search = $ctl->get_session("search_" . $this->table_name);
			$search_fields = $this->assign_search_group($ctl, "group1", true, true);
			if(count($search_fields)>0){
				$ctl->assign("show_search_box",true);
			}
			$ctl->assign("row",$search);
			$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
			
		}else if($this->db_setting["list_type"] == 2){
			// List Type is "Weekly Calendar"
			$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
			
		}
		
		if($this->check_show_button($ctl,$this->table_name,"add")){
			$flg_add_button=true;
		}else{
			$flg_add_button=false;
		}
		$ctl->assign("flg_add_button",$flg_add_button);
		
		// Additional Features
		$ffm_additionals =  $ctl->db("additionals","db_additionals");
		$additional_list = $ffm_additionals->select(["tb_name","place"],[$this->table_name,0],true,"AND","sort",SORT_DESC);
		$this->add_show_button_class($ctl,$additional_list);
		$ctl->assign("additionals",$additional_list);
		$this->assign_properties_management_summary($ctl);
		
		// Show HTML
		$ctl->show_main_area("index.tpl", $this->title);
	}

	function search(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		// Getting post data
		$post = $ctl->POST();
		
		// Putting search fields and search values
		$ctl->set_session("search_" . $this->table_name, $this->collect_search_session_from_post($ctl, $post));
		
		// Call the function "rows"
		$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
	}

	private function get_side_search_session_key(): string {
		return "search_side_" . $this->db_setting_id;
	}

	private function get_side_search_field_names(Controller $ctl, string $parent_field = "parent_id"): array {
		$fields = $this->get_search_fields($ctl);
		$names = [];
		foreach ($fields as $field) {
			$name = $field["parameter_name"] ?? "";
			if ($name === "" || $name === "parent_id" || $name === $parent_field) {
				continue;
			}
			$names[] = $name;
		}
		return $names;
	}

	private function get_side_search_fields(Controller $ctl, string $parent_field = "parent_id"): array {
		$tmp_group_name = "__tmp_side_search_group_" . uniqid();
		$fields = $this->assign_search_group($ctl, $tmp_group_name, true, false);
		$list = [];
		foreach ($fields as $field) {
			$name = $field["parameter_name"] ?? "";
			if ($name === "" || $name === "parent_id" || $name === $parent_field) {
				continue;
			}
			$list[] = $field;
		}
		return $list;
	}

	private function normalize_year_month_value($value) {
		$value = trim((string) $value);
		if ($value === "") {
			return "";
		}

		if (preg_match('/^(\d{4})[\/-](\d{1,2})$/', $value, $m)) {
			$month = (int) $m[2];
			if ($month >= 1 && $month <= 12) {
				return sprintf('%s/%02d', $m[1], $month);
			}
			return $value;
		}

		if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $value, $m)) {
			$month = (int) $m[2];
			if ($month >= 1 && $month <= 12) {
				return sprintf('%s/%02d', $m[1], $month);
			}
			return $value;
		}

		$normalized = preg_replace('/[^0-9]/', '', $value);
		if (strlen($normalized) >= 6) {
			$year = substr($normalized, 0, 4);
			$month = (int) substr($normalized, 4, 2);
			if ($month >= 1 && $month <= 12) {
				return sprintf('%s/%02d', $year, $month);
			}
		}

		return $value;
	}

	private function normalize_year_month_post_fields(Controller $ctl, string $screen_name, array $post): array {
		$fields = $ctl->get_field_list($this->table_name, $screen_name);
		foreach ($fields as $field) {
			if (($field["type"] ?? "") !== "year_month") {
				continue;
			}
			$parameter_name = $field["parameter_name"] ?? "";
			if ($parameter_name === "" || !array_key_exists($parameter_name, $post)) {
				continue;
			}
			$post[$parameter_name] = $this->normalize_year_month_value($post[$parameter_name]);
		}
		return $post;
	}

	function search_child(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}

		$ctl->set_session($this->get_side_search_session_key(), $this->collect_search_session_from_post($ctl, $post, true, $context["parent_field"]));
		$ctl->invoke("rows_child",[
			"db_id"=>$this->db_setting_id,
			"parent_id"=>$context["parent_id"],
			"parent_field"=>$context["parent_field"],
			"parent_db_id"=>$context["parent_db_id"],
		]);
	}
	
	function search_weekly_calendar(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		// Getting post data
		$post = $ctl->POST();
		
		// Putting search fields and search values
		$ctl->set_session("search_" . $this->table_name, $this->collect_search_session_from_post($ctl, $post));
		
		// Call the function "rows"
		$ctl->invoke("rows_weekly_calendar",["max"=>0,"db_id"=>$this->db_setting_id]);
	}
	
	
	function rows(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting search fields and search values
		$session = $ctl->get_session("search_" . $this->table_name);
		$fields = $this->get_search_fields($ctl);
		[$search_field_list, $search_values, $search_match_patterns] = $this->build_search_filter_parts($fields, $session);
		$this->append_visibility_filter_conditions($ctl, $search_field_list, $search_values, $search_match_patterns, "list");
		$ctl->assign("manual_sort_search_active", $this->search_session_has_values($session));
		
		// Getting data from DB
		$max = $ctl->increment_post_value('max', 10);
		$this->ffm->set_flg_filter_zero(true); // ""で全検索 0のvalueを有効にする
		$rows = $this->ffm->filter($search_field_list, $search_values, false, 'AND', $this->db_setting["sortkey"], $this->db_setting["sort_order"], $max, $is_last, $search_match_patterns);

		// Encrypt ID and change data
		$ctl->assign_field_settings("group1",$this->table_name, 'list', false,true,true);
		$ctl->assign("rows",$rows);
		
		// Checking child tables
		$ctl->assign("child_tables", $this->get_child_tables_for_parent_list($ctl));
		
		// Assign data
		$ctl->assign("max", $max);
		$ctl->assign("is_last", $is_last);
		
		// Additional Features
		$ffm_additionals =  $ctl->db("additionals","db_additionals");
		$additional_list = $ffm_additionals->select(["tb_name","place"],[$this->table_name,1],true,"AND","sort",SORT_DESC);
		$this->add_show_button_class($ctl,$additional_list);
		$ctl->assign("additionals",$additional_list);
		
		if($this->check_show_button($ctl,$this->table_name,"edit")){
			$flg_edit_button=true;
		}else{
			$flg_edit_button=false;
		}
		$ctl->assign("flg_edit_button",$flg_edit_button);
		
		if($this->check_show_button($ctl,$this->table_name,"delete")){
			$flg_delete_button=true;
		}else{
			$flg_delete_button=false;
		}
		$ctl->assign("flg_delete_button",$flg_delete_button);
		
		// ID
		if($this->db_setting["show_id"]==1){
			$ctl->assign("show_id",true);
		}
		
		// Duplicate icon
		if($this->db_setting["show_duplicate"]==1){
			$ctl->assign("flg_duplicate_button",true);
		}
		
		// Making tbody
		if($this->db_setting["list_type"] == 0){
			$html = $ctl->fetch("rows.tpl");
		}else{
			$html = $ctl->fetch("rows_manual_sort.tpl");
		}
		$ctl->reload_area("#main_table", $html);
	}
	
	function add(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		$ctl->assign_field_settings("group1",$this->table_name, "add", false,true);
		$row = $ctl->get_default_values($this->table_name);
		$ctl->assign("row",$row);
		
		if($this->db_setting["edit_width"] == 0){
			$width=800;
		}else{
			$width=$this->db_setting["edit_width"];
		}
		$ctl->show_multi_dialog($this->window_name, "add.tpl", $ctl->t("common.add"),$width,"_add_button.tpl");
	}
	
	function add_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		$post = $this->normalize_year_month_post_fields($ctl, "add", $post);
		
		// Validate
		$ctl->validate($this->table_name, "add", $post);
		
		// Insert a new row
		if($ctl->count_res_error_message()==0){
			// Insert data
			$this->ffm->insert($post);
			
			// Save files posted
			$ctl->save_posted_files($this->table_name, $post);
			$data = $this->ffm->get($post["id"] ?? null);
			
			// Refresh the table and close the window
			if($this->db_setting["list_type"]==0 || $this->db_setting["list_type"] == 1){
				$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
			}else if($this->db_setting["list_type"] == 2){
				$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
			}
			$ctl->close_multi_dialog($this->window_name);
			$ctl->close_second_work_area();
			
			// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "add");
		}
	}
	
	function edit(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();

		$id = $ctl->decrypt_post("id");
		$row = $this->ffm->get($id);
		$row["id"] = $ctl->encrypt($id);
		
		$ctl->assign_field_settings("group1",$this->table_name, "edit", false,true);
		$ctl->assign("row",$row);
		
		if($this->db_setting["edit_width"] == 0){
			$width=800;
		}else{
			$width=$this->db_setting["edit_width"];
		}
		$ctl->show_multi_dialog($this->window_name . "_" . $this->table_name . "_$id", "edit.tpl", $ctl->t("common.edit"),$width,"_update_button.tpl");
	}
	
	function edit_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		$post = $this->normalize_year_month_post_fields($ctl, "edit", $post);
		$fields = $ctl->get_field_list($this->table_name, "edit");
		foreach($fields as $field){
			$parameter_name = $field["parameter_name"] ?? "";
			if(($field["type"] ?? "") === "checkbox" && $parameter_name !== "" && !array_key_exists($parameter_name, $post)){
				$post[$parameter_name] = [];
			}
		}
		
		// Validate
		$ctl->validate($this->table_name, "edit", $post,false);
		
		// Update
		if($ctl->count_res_error_message()==0){
			// Getting row
			$id = $ctl->decrypt_post("id");
			$before_data = $this->ffm->get($id);
			$post["id"] = $id;
			
			$this->ffm->update($post);
			$data = $this->ffm->get($id);
			$ctl->save_posted_files($this->table_name, $data);
			
			// Update the table
			if($this->db_setting["list_type"]==0 || $this->db_setting["list_type"] == 1){
				$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
			}else if($this->db_setting["list_type"] == 2){
				$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
				$ctl->invoke("unassigned_tasks",["db_id"=>$this->db_setting_id]);
			}
			$ctl->close_multi_dialog($this->window_name . "_" . $this->table_name . "_$id");
			if($this->db_setting["list_type"]!=2){
				$ctl->close_second_work_area();
			}
			
			// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "edit", null, is_array($before_data) ? $before_data : null);
		}		
	}
	
	function duplicate(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$id = $ctl->decrypt_post("id");
		
		$new_id = $ctl->duplicate_rows($this->table_name, $id);
		if (!$new_id) {
			$ctl->show_notification_text($ctl->t("db_exe.validation.target_data_not_found"), 4, "#B42318", "#FFF", 18, 620);
			return;
		}
		$data = $ctl->db($this->table_name)->get($new_id);
		
		// Update the table
		if($this->db_setting["list_type"]==0 || $this->db_setting["list_type"] == 1){
			$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
		}else if($this->db_setting["list_type"] == 2){
			$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
		}
		
		// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "duplicate", $id);
	}
	
	function edit_datetime_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		
		// Getting row
		$id = $ctl->decrypt_post("id");
		$row = $this->ffm->get($id);

		// Update row
		$row["datetime"] = $post["datetime"];
		$this->ffm->update($row);

		// Update the table
		$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
	}
	
	function delete(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Assign the post data
		$id = $ctl->decrypt_post("id");
		$row = $this->ffm->get($id);
		$row["id"] = $ctl->encrypt($id);

		$ctl->assign_field_settings("group1",$this->table_name, "delete", false,false);
		$ctl->assign("row",$row);
		
		$ctl->show_multi_dialog($this->window_name, "delete.tpl", $ctl->t("common.delete"),600,"_delete_button.tpl");
	}
	
	private function delete_recurring(Controller $ctl,$tb_name,$id){
		
		$dblist = $ctl->db("db","db")->getall();
		$mydb = $ctl->db("db","db")->select("tb_name",$tb_name)[0];
		
		// 削除
		$ctl->db($tb_name)->delete($id);
		$ctl->delete_files($tb_name, $id);
		
		foreach($dblist as $cdb){
			if($cdb["parent_tb_id"] > 0){
				if($cdb["parent_tb_id"] == $mydb["id"]){
					// 子供だ！
					if($cdb["cascade_delete_flag"] == 1){
						$clist = $ctl->db($cdb["tb_name"])->select("parent_id",$id);
						foreach($clist as $c){
							$this->delete_recurring($ctl, $cdb["tb_name"], $c["id"]);
						}
					}
				}
			}
		}
	}
	
	
	function delete_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$id = $ctl->decrypt_post("id");
		$data = $this->ffm->get($id);
		
		$this->delete_recurring($ctl, $this->table_name, $id);
		
		$ctl->close_multi_dialog($this->window_name);
		if($this->db_setting["list_type"]==0 || $this->db_setting["list_type"] == 1){
			$ctl->close_second_work_area();
			$ctl->invoke("rows",["max"=>0,"db_id"=>$this->db_setting_id]);
		}else if($this->db_setting["list_type"] == 2){
			$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
			$ctl->invoke("unassigned_tasks",["db_id"=>$this->db_setting_id]);
		}
		
		// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "delete");
	} 
	
	
	function rows_child(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$post = $ctl->POST();
		if ((int) ($this->db_setting["screen_build_type"] ?? 0) === 1
				&& $this->invoke_original_management_function($ctl, "rows_child", $post)) {
			return;
		}

		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		$parent_id = $context["parent_id"];
		$parent_field = $context["parent_field"];
		$ctl->assign("parent_id",$parent_id);
		$ctl->assign("parent_field",$parent_field);
		$ctl->assign("parent_db_id",$context["parent_db_id"]);
		
		// Create a link to display the previous table
		$db_parent = $context["db_parent"];
		$fmt_parent = $ctl->db($db_parent["tb_name"]);
		$parent = $fmt_parent->get($parent_id);
		$ctl->assign("db_parent",$db_parent);
		$ctl->assign("parent",$parent);	
		
		// Table Title
		$ctl->assign("table_title",$this->db_setting["menu_name"]);
		
		$side_panel_list_type = $this->get_side_panel_list_type();
		$search_fields = $this->get_side_search_fields($ctl, $parent_field);
		$ctl->assign("search_group", $search_fields);
		$search_row = $ctl->get_session($this->get_side_search_session_key());
		if(!is_array($search_row)){
			$search_row = [];
		}
		$ctl->assign("row", $search_row);
		if(count($search_fields) > 0){
			$ctl->assign("show_search_box", true);
		}

		[$search_field_list, $search_values, $search_match_patterns] = $this->build_search_filter_parts($search_fields, $search_row);
		array_unshift($search_field_list, $parent_field);
		array_unshift($search_values, $parent_id);
		array_unshift($search_match_patterns, "=");
		$this->append_visibility_filter_conditions($ctl, $search_field_list, $search_values, $search_match_patterns, "list_on_side");
		$ctl->assign("manual_sort_search_active", $this->search_session_has_values($search_row));

		if($side_panel_list_type == 1){

			$max = $ctl->increment_post_value('max', 10);
			$this->ffm->set_flg_filter_zero(true);
			$rows = $this->ffm->filter($search_field_list, $search_values, false, 'AND', $this->db_setting["sortkey"], $this->db_setting["sort_order"], $max, $is_last, $search_match_patterns);
			$ctl->assign("max", $max);
			$ctl->assign("is_last", $is_last);
		}else{
			// Getting data from DB
			$this->ffm->set_flg_filter_zero(true);
			$rows = $this->ffm->filter($search_field_list, $search_values, false, 'AND', $this->db_setting["sortkey"], $this->db_setting["sort_order"], null, $is_last, $search_match_patterns);
			$rows = $this->sort_rows_for_manual_side_panel($ctl, $rows);
		}

		// Encrypt ID and change data
		$ctl->assign_field_settings("group1",$this->table_name, 'list_on_side', true,false,true);
		$ctl->assign("rows",$rows);
		
		// Checking child tables
		$ctl->assign("child_tables", $this->get_child_tables_for_parent_list($ctl));
		
		// Additional Features
		$ffm_additionals =  $ctl->db("additionals","db_additionals");
		$additional_list = $ffm_additionals->select(["tb_name","place"],[$this->table_name,2],true,"AND","sort",SORT_DESC);
		$this->add_show_button_class($ctl,$additional_list);
		$ctl->assign("additionals",$additional_list);
		
		$additional_list_row = $ffm_additionals->select(["tb_name","place"],[$this->table_name,3],true,"AND","sort",SORT_DESC);
		$this->add_show_button_class($ctl,$additional_list_row);
		$ctl->assign("additionals_row",$additional_list_row);
		
		
		// reload_side_panel()用にセッションに保存
		$sidepanel= [
		    "db_id" => $post["db_id"] ?? null,
		    "parent_id" => $parent_id,
		    "parent_field" => $parent_field,
		    "parent_db_id" => $context["parent_db_id"]
		];
		$ctl->set_session("_side_panel", $sidepanel);
		
		if($this->check_show_button($ctl,$this->table_name,"add")){
			$flg_add_button=true;
		}else{
			$flg_add_button=false;
		}
		$ctl->assign("flg_add_button",$flg_add_button);
		
		if($this->check_show_button($ctl,$this->table_name,"edit")){
			$flg_edit_button=true;
		}else{
			$flg_edit_button=false;
		}
		$ctl->assign("flg_edit_button",$flg_edit_button);
		
		if($this->check_show_button($ctl,$this->table_name,"delete")){
			$flg_delete_button=true;
		}else{
			$flg_delete_button=false;
		}
		$ctl->assign("flg_delete_button",$flg_delete_button);

		
		// show html into the second work area
		if($this->db_setting["list_width"] == 0){
			$width=400;
		}else{
			$width=$this->db_setting["list_width"];
		}
		if($side_panel_list_type == 1){
			$ctl->show_second_work_area("rows_child.tpl",$width);
		}else{
			$ctl->show_second_work_area("rows_child_manual_sort.tpl",$width);
		}
	}
	
	
	function add_child(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		$ctl->assign_field_settings("group1",$this->table_name, "add", false,false);
		$row = $ctl->get_default_values($this->table_name);
		$row[$context["parent_field"]] = $context["parent_id"];
		$ctl->assign("row",$row);
		$ctl->assign("parent_id",$context["parent_id"]);
		$ctl->assign("parent_field",$context["parent_field"]);
		$ctl->assign("parent_db_id",$context["parent_db_id"]);

		if($this->db_setting["edit_width"] == 0){
			$width=800;
		}else{
			$width=$this->db_setting["edit_width"];
		}
		$ctl->show_multi_dialog($this->window_name, "add_child.tpl", $ctl->t("common.add"),$width,"_add_button_child.tpl");
	}
	
	function add_child_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		$parent_id = $context["parent_id"];
		$post[$context["parent_field"]] = $parent_id;
		
		// Validate
		$ctl->validate($this->table_name, "add", $post);
		
		// Insert a new row
		if($ctl->count_res_error_message()==0){
			// Insert data
			$this->ffm->insert($post);
			
			$this->ffm->update($post);
			$ctl->save_posted_files($this->table_name, $post);
			
			$data = $this->ffm->get($post["id"] ?? null);
			
			// Refresh the table and close the window
			$ctl->invoke("rows_child",[
				"db_id"=>$this->db_setting_id,
				"parent_id"=>$parent_id,
				"parent_field"=>$context["parent_field"],
				"parent_db_id"=>$context["parent_db_id"],
			]);
			$ctl->close_multi_dialog($this->window_name);
			
			// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "add");
		}
	}
	
	function edit_child(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}

		$id = $ctl->decrypt_post("id");
		$row = $this->ffm->get($id);
		$row["id"] = $ctl->encrypt($id);
		
		$ctl->assign_field_settings("group1",$this->table_name, "edit", false,false);
		$ctl->assign("row",$row);
		$ctl->assign("parent_id",$context["parent_id"]);
		$ctl->assign("parent_field",$context["parent_field"]);
		$ctl->assign("parent_db_id",$context["parent_db_id"]);
		
		if($this->db_setting["edit_width"] == 0){
			$width=800;
		}else{
			$width=$this->db_setting["edit_width"];
		}
		$ctl->show_multi_dialog($this->window_name . "_" . $id, "edit_child.tpl", $ctl->t("common.edit"),$width,"_update_button_child.tpl");
	}
	
	function edit_child_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// Getting Post data
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		$parent_id = $context["parent_id"];
		$post[$context["parent_field"]] = $parent_id;
		
		// field
		$fields = $ctl->get_field_list($this->table_name, "edit");
		foreach($fields as $field){
			$parameter_name = $field["parameter_name"] ?? "";
			if(($field["type"] ?? "") === "checkbox" && $parameter_name !== "" && !array_key_exists($parameter_name, $post)){
				$post[$parameter_name] = [];
			}
		}
		
		// Validate
		$ctl->validate($this->table_name, "Edit", $post,false);
		
		// Update
		if($ctl->count_res_error_message()==0){
			// Getting row
			$id = $ctl->decrypt_post("id");
			$before_data = $this->ffm->get($id);
			$post["id"] = $id;
			
			$this->ffm->update($post);
			$data = $this->ffm->get($id);
			$ctl->save_posted_files($this->table_name, $data);
			
			// Update the table
			$ctl->invoke("rows_child",[
				"db_id"=>$this->db_setting_id,
				"parent_id"=>$parent_id,
				"parent_field"=>$context["parent_field"],
				"parent_db_id"=>$context["parent_db_id"],
			]);
			$ctl->close_multi_dialog($this->window_name . "_" . $id);
			
			// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "edit", null, is_array($before_data) ? $before_data : null);
		}		
	}
	
	function delete_child(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		
		// Assign the post data
		$id = $ctl->decrypt_post("id");
		$row = $this->ffm->get($id);
		$row["id"] = $ctl->encrypt($id);

		$ctl->assign_field_settings("group1",$this->table_name, "delete", false,false);
		$ctl->assign("row",$row);
		$ctl->assign("parent_id",$context["parent_id"]);
		$ctl->assign("parent_field",$context["parent_field"]);
		$ctl->assign("parent_db_id",$context["parent_db_id"]);
		
		$ctl->show_multi_dialog($this->window_name, "delete_child.tpl", $ctl->t("common.delete"),600,"_delete_button_child.tpl");
	}
	
	function delete_child_exe(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$post = $ctl->POST();
		$context = $this->get_child_relation_context($ctl, $post);
		if ($context === null) {
			return;
		}
		$parent_id = $context["parent_id"];
		$id = $ctl->decrypt_post("id");
		$data = $this->ffm->get($id);
		
		$this->delete_recurring($ctl, $this->table_name, $id);		
		
		$ctl->close_multi_dialog($this->window_name);
		$ctl->invoke("rows_child",[
			"db_id"=>$this->db_setting_id,
			"parent_id"=>$parent_id,
			"parent_field"=>$context["parent_field"],
			"parent_db_id"=>$context["parent_db_id"],
		]);
		
		// Post Action Class
				$this->invoke_post_action_class($ctl, $data, "delete");
	}
	
	function manual_sort(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$post = $ctl->POST();
		$ex = explode(",", (string) ($post["log"] ?? ""));
		$c=1;
		foreach($ex as $id){
			$d = $this->ffm->get($id);
			if (!$d) {
				continue;
			}
			$d["sort"] = $c++;
			$this->ffm->update($d);
		}
	}
	
	function rows_weekly_calendar(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// SET BROWSER TIMEZONE
		date_default_timezone_set($ctl->POST("_timezone"));
		$ctl->assign("timezone",$ctl->POST("_timezone"));
		
		// set start hour and end hour
		if(!empty($this->db_setting["start_hour"])){
			$START_HOUR=$this->db_setting["start_hour"];
		}else{
			$START_HOUR=6;
		}
		if(!empty($this->db_setting["end_hour"])){
			$END_HOUR=$this->db_setting["end_hour"];
		}else{
			$END_HOUR=22;
		}
		
		// field of each task
		$ctl->assign_field_settings("group1",$this->table_name, 'list', false,true);
		
		$search = $ctl->get_session("search_" . $this->table_name);
		$search_fields = $this->assign_search_group($ctl, "search_group", true, true);
		if(count($search_fields)>0){
			$ctl->assign("show_search_box",true);
		}
		
		// Set date
		$d = $ctl->get_session("YMD-time");
		if(empty($d)){
			$d = time();
		}
		$d = $this->get_beginning_week_date($d); //Change to monday of the week
		$ctl->assign("time_previous",strtotime("previous week",$d));
		$ctl->assign("time_next",strtotime("next week",$d));
		$ctl->assign("time_today",time());
		$time_from = $d;
		$time_end = $d + (60*60*24*7);
		
		// filter data
		$session = $ctl->get_session("search_" . $this->table_name);
		$fields = $this->get_search_fields($ctl);
		[$search_field_list, $search_values, $search_match_patterns] = $this->build_search_filter_parts($fields, $session);
		$ctl->assign("row",$session);
		
		// Getting data to show in the time cells
		$occupied=[];
		$occupied_travel=[];
		$assigned=[];
		$assigned_travel=[];
		//$list = $this->ffm->select(["datetime","datetime"],[$time_from,$time_end],[">=","<="]);
		$this->ffm->set_flg_filter_zero(true);
		$list = $this->ffm->filter($search_field_list, $search_values, false, 'AND', null, SORT_DESC, null, $is_last, $search_match_patterns);
		// Ensure stable display order inside each hour cell (e.g. 10:00 before 10:30).
		usort($list, function ($a, $b) {
			$adt = (int) ($a["datetime"] ?? 0);
			$bdt = (int) ($b["datetime"] ?? 0);
			if ($adt !== $bdt) {
				return $adt <=> $bdt;
			}
			$aid = (int) ($a["id"] ?? 0);
			$bid = (int) ($b["id"] ?? 0);
			return $aid <=> $bid;
		});
		foreach($list as &$row){
			$start_ts = (int)($row["datetime"] ?? 0);
			$duration = max(0, (int)($row["duration"] ?? 0));
			$travel_before = max(0, (int)($row["travel_before"] ?? 0));
			$travel_after = max(0, (int)($row["travel_after"] ?? 0));
			$end_ts = $start_ts + ($duration * 60);
			$travel_start_ts = $start_ts - ($travel_before * 60);
			$travel_end_ts = $end_ts + ($travel_after * 60);
			if($start_ts >= $time_from && $start_ts <= $time_end){
				$row["start_time"] = date("H:i",$start_ts);
				$row["end_time"] = date("H:i",$end_ts);
				$row["travel_start_time"] = $travel_before > 0 ? date("H:i",$travel_start_ts) : "";
				$row["travel_end_time"] = $travel_after > 0 ? date("H:i",$travel_end_ts) : "";
				// Round down the Unix timestamp in $start_ts to the nearest hour
				$target_time = $start_ts - ($start_ts % 3600);
				$assigned[$target_time][]=$row;

				// occupied (task body)
				$start_hour = $target_time;
				$end_hour = ceil($end_ts / 3600) * 3600;
				for($i=$start_hour;$i<$end_hour;$i=$i+(60*60)){
					$occupied[$i] = "occupied";
				}

				// occupied (travel time, non-interactive background only)
				if($travel_before > 0){
					$travel_before_start_hour = $travel_start_ts - ($travel_start_ts % 3600);
					$travel_before_end_hour = ceil($start_ts / 3600) * 3600;
					for($i=$travel_before_start_hour;$i<$travel_before_end_hour;$i=$i+(60*60)){
						$occupied_travel[$i] = "occupied_travel";
					}
					$assigned_travel[$travel_before_start_hour][] = [
						"type" => "before",
						"time" => date("H:i",$travel_start_ts),
						"_id_enc" => $row["_id_enc"] ?? ""
					];
				}
				if($travel_after > 0){
					$travel_after_start_hour = $end_ts - ($end_ts % 3600);
					$travel_after_end_hour = ceil($travel_end_ts / 3600) * 3600;
					for($i=$travel_after_start_hour;$i<$travel_after_end_hour;$i=$i+(60*60)){
						$occupied_travel[$i] = "occupied_travel";
					}
					$assigned_travel[$travel_after_start_hour][] = [
						"type" => "after",
						"time" => date("H:i",$travel_end_ts),
						"_id_enc" => $row["_id_enc"] ?? ""
					];
				}

				// check the start hour (include travel span)
				$visible_start_ts = $travel_before > 0 ? $travel_start_ts : $start_ts;
				$visible_end_ts = $travel_after > 0 ? $travel_end_ts : $end_ts;
				if(date("H",$visible_start_ts)<$START_HOUR){
					$START_HOUR = (int)date("H",$visible_start_ts);
				}
				if(date("H",$visible_end_ts)>$END_HOUR){
					$END_HOUR = (int)date("H",$visible_end_ts);
				}
			}
		}
		$ctl->assign("rows",$list);
		$ctl->assign("occupied",$occupied);
		$ctl->assign("occupied_travel",$occupied_travel);
		$ctl->assign("assigned",$assigned);
		$ctl->assign("assigned_travel",$assigned_travel);

		// Creating the weekly calendar
		$calendar_arr = array();
		for($i=0;$i<7;$i++){
			$target_time = strtotime($i . " day",$d);
			$w = date('w',$target_time);
			$dateObj   = DateTime::createFromFormat('!m', date('m',$target_time));
			
			$hours=[];
			for($h=$START_HOUR;$h<=$END_HOUR;$h++){
				$hours[] = [
				    "h"=>$h,
				    "target_time"=>$target_time + $h*60*60
				];
			}
			
			$calendar_arr[] = [
			    "year"=>date("Y",$target_time),
			    "month"=>$dateObj->format('F'),
			    "date"=>date("d",$target_time),
			    "day"=>$this->days[$w],
			    "w" => $w,
			    "hours"=>$hours
			];
		}

		// Assign datas
		$ctl->assign("calendar_arr",$calendar_arr);
		
		// Additional Features
		$ffm_additionals =  $ctl->db("additionals","db_additionals");
		$additional_list = $ffm_additionals->select(["tb_name","place"],[$this->table_name,0],true,"AND","sort",SORT_DESC);
		$this->add_show_button_class($ctl,$additional_list);
		$ctl->assign("additionals",$additional_list);
		
		// Checking child tables
		$ctl->assign("child_tables", $this->get_child_tables_for_parent_list($ctl));
		
		// Show 
		$ctl->show_main_area("rows_weekly.tpl", $this->db_setting["menu_name"]);
		
		// Unassigned tasks
		//$ctl->invoke("unassigned_tasks",["db_id"=>$this->db_setting_id]);
	}
	
	function unassigned_tasks(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		
		// field of each task
		$ctl->assign_field_settings("group1",$this->table_name, 'list', false,true);
		
		// Checking child tables (for _row_for_weekly.tpl small table links)
		$ctl->assign("child_tables", $this->get_child_tables_for_parent_list($ctl));
		
		// filter data
		$session = $ctl->get_session("search_" . $this->table_name);
		$fields = $this->get_search_fields($ctl);
		[$search_field_list, $search_values, $search_match_patterns] = $this->build_search_filter_parts($fields, $session);
		
		$unassigned = [];
		$this->ffm->set_flg_filter_zero(true);
		$list = $this->ffm->filter($search_field_list, $search_values, false, 'AND', null, SORT_DESC, null, $is_last, $search_match_patterns);
		
		// Getting unassigned data and show it on the side panel
		//$list = $this->ffm->select("datetime","","=");
		foreach($list as &$row){
			$status = (string)($row["status"] ?? "");
			if($row["datetime"] == "" && $status === "0"){
				$unassigned[]=$row;
			}
		}
		$ctl->assign("unassigned",$unassigned);
		$ctl->show_second_work_area("unassigned_tasks.tpl");	
	}

	function set_datetime(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$d = $ctl->POST("d");
		if(!is_numeric($d)){
			// from the "Jump"
			$r = strtotime($d);
		}else{
			// from the buttons
			$r = $d;
		}
		$ctl->set_session("YMD-time",$r);
		$ctl->invoke("rows_weekly_calendar",["db_id"=>$this->db_setting_id]);
	}
	
	function download_file(Controller $ctl){
		$post = $ctl->POST();
		$path = $ctl->decrypt((string) ($post["path"] ?? ""));
		$ctl->res_saved_file($path);
	}
	
	function view_image(Controller $ctl){
		$get = $ctl->GET();
		$path = $ctl->decrypt((string) ($get["path"] ?? ""));
		$ctl->res_saved_image($path,true,3600,true);
	}
	
	// 月曜日を取得
	private function get_beginning_week_date($timestamp) {
		$w = date("w", $timestamp);
		if ($w == 0) {
			$rd = 6;
		} else {
			$rd = $w - 1;
		}
		$d = strtotime(date("Y/m/d", strtotime("-{$rd} day", $timestamp))); // 丁度にするために必要
		return $d;
	}
	
	function reload(Controller $ctl){
		if (!$this->can_execute($ctl)) {
			return;
		}
		$ctl->reload_work_area();
		$ctl->reload_side_panel();
		$ctl->invoke("show_menu",[],"base");
		$ctl->close_all_dialog();
	}
	
	private function add_show_button_class(Controller $ctl,&$additionals){
		$setting = $ctl->get_setting();
		if($ctl->testserver() || $setting["show_developer_panel"] == 1){
			$style_class = "hide_button50";
		}else{
			$style_class = "hide_button";
		}
		foreach($additionals as $key=>$a){
			if($a["show_button"] == 1){
				$additionals[$key]["show_button_class"] = $style_class;
			}
		}
	}
	
	function close_second_work_area(Controller $ctl){
		$ctl->set_session("_side_panel", null);
		$ctl->close_second_work_area();
	}
}
