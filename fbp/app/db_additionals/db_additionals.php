<?php

require_once __DIR__ . "/../../lib/SingleRecordScreen.php";


class db_additionals {
	
	private $place_opt = [0 => "Top Section", 1 => "Each Row", 2=>"Bottom Section of side table", 3=>"Each Row of side table"];
	private $button_type_opt = [0=>"Text",1=>"Icon(Google Material Icons)"];
	private $show_button_opt = [0=>"Show",1=>"Hide"];
	private $window="db_additionals";

    private function table_setting(Controller $ctl, string $name): array {
        return $ctl->db('db', 'db')->select('tb_name', $name)[0] ?? [];
    }

    private function validate_place(Controller $ctl, array $button): bool {
        if (!SingleRecordScreen::allows_button($this->table_setting($ctl, (string) ($button['tb_name'] ?? '')), $button['place'] ?? 0)) {
            $ctl->res_error_message('place', $ctl->t('db.single.top_buttons_only'));
            return false;
        }
        return true;
    }

    private function assign_single_tables(Controller $ctl, array $tables): void {
        $ctl->assign('single_record_tables_json', json_encode(array_values(array_map(
            fn($table) => (string) $table['tb_name'], array_filter($tables, [SingleRecordScreen::class, 'is_single'])
        )), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }

	private function get_target_area(Controller $ctl): string {
		$post = $ctl->POST();
		$target_area = trim((string) ($post["target_area"] ?? ""));
		if ($target_area === "") {
			$target_area = "#tabs-buttons";
		}
		return $target_area;
	}

	private function reload_list_area(Controller $ctl, string $tb_name = "", string $target_area = "#tabs-buttons", int $reload_db_id = 0): void {
		$post = [
			"tb_name" => $tb_name,
			"target_area" => $target_area,
		];
		if ($reload_db_id > 0) {
			$post["reload_db_id"] = $reload_db_id;
		}
		$ctl->ajax("db_additionals", "list", $post);
	}

	function __construct(Controller $ctl) {
		$ctl->assign("place_opt", $this->place_opt);
		$ctl->assign("button_type_opt",$this->button_type_opt);
		$ctl->assign("show_button_opt",$this->show_button_opt);
	}

	function list(Controller $ctl) {
		$post = $ctl->POST();
		$tb_name = trim((string) ($post["tb_name"] ?? ""));
		$target_area = $this->get_target_area($ctl);
        if (SingleRecordScreen::is_single($this->table_setting($ctl, $tb_name))) {
            $ctl->assign('place_opt', [0 => $this->place_opt[0]]);
        }
		$reload_db_id = (int) ($post["reload_db_id"] ?? 0);

		if ($tb_name !== "") {
			$items = $ctl->db("additionals")->select("tb_name", $tb_name, true, "AND", "sort", SORT_ASC);
		} else {
			$items = $ctl->db("additionals")->getall("id",SORT_DESC);
		}
		
		foreach($items as $key=>$item){
			if(($item["class_name"] ?? "") == "admin"){
				unset($items[$key]);
				continue;
			}
		}

		$ctl->assign("items", $items);
		$ctl->assign("tb_name", $tb_name);
		$ctl->assign("target_area", $target_area);
		$ctl->assign("reload_db_id", $reload_db_id);
		$place_counts = [];
		$grouped_items = [0 => [], 1 => [], 2 => [], 3 => []];
		foreach ($items as $item) {
			$place = (string) ($item["place"] ?? "");
			if ($place === "") {
				continue;
			}
			$place_counts[$place] = (int) ($place_counts[$place] ?? 0) + 1;
			$place_int = (int) $place;
			if (!isset($grouped_items[$place_int])) {
				$grouped_items[$place_int] = [];
			}
			$grouped_items[$place_int][] = $item;
		}
		$ctl->assign("place_counts", $place_counts);
		$ctl->assign("grouped_items", $grouped_items);

		$ctl->reload_area($target_area, "list.tpl");
	}

	function add(Controller $ctl) {
		
		$post = $ctl->POST();
		$post["function_name"] = "run";
		$post["button_type"] = (int) ($post["button_type"] ?? 0);
		
		$db_id = (int) ($post["id"] ?? 0);
		
		if(!empty($db_id)){
			$db = $ctl->db("db","db")->get($db_id);
			$post["tb_name"] = (string) ($db["tb_name"] ?? "");
			$ctl->assign("reflesh_db",$db_id);
		}
		$post["target_area"] = $post["target_area"] ?? $this->get_target_area($ctl);
		$post["reload_db_id"] = (int) ($post["reload_db_id"] ?? 0);
		
		$database_list = $ctl->db("db", "db")->getall("sort",SORT_ASC);
		$database_names = [];
		foreach ($database_list as $d) {
			$tb_name = (string) ($d["tb_name"] ?? "");
			if ($tb_name === "") {
				continue;
			}
			$database_names[$tb_name] = $tb_name;
		}
		$this->assign_single_tables($ctl, $database_list);
		$ctl->assign("database_names", $database_names);
		$ctl->assign("post",$post);


		$ctl->show_multi_dialog($this->window . "edit", "add.tpl", $ctl->t("db_additionals.dialog.add"), 1000);
	}

	function add_exe(Controller $ctl) {

		$post = $ctl->POST();
		$this->validate_place($ctl, $post);
		$class_name = trim((string)($post["class_name"] ?? ""));
		$function_name = trim((string)($post["function_name"] ?? ""));

		if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $class_name)) {
			$ctl->res_error_message("class_name", $ctl->t("db_additionals.validation.class_name_format"));
		}
		if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $function_name)) {
			$ctl->res_error_message("function_name", $ctl->t("db_additionals.validation.function_name_format"));
		}
		if (empty($post["button_title"])) {
			$ctl->res_error_message("button_title", $ctl->t("validation.required"));
		}
		if (empty($post["tb_name"])) {
			$ctl->res_error_message("place", $ctl->t("validation.required"));
		}

		$exists = $ctl->db("additionals")->select("class_name", $class_name);
		if (count($exists) > 0) {
			$ctl->res_error_message("class_name", $ctl->t("db_additionals.validation.class_name_exists"));
		}
		


		if ($ctl->count_res_error_message() == 0) {
			$save = [];
			$save["tb_name"] = trim((string)$post["tb_name"]);
			$save["button_title"] = trim((string)$post["button_title"]);
			$save["class_name"] = $class_name;
			$save["function_name"] = $function_name;
			$save["place"] = (int)($post["place"] ?? 0);
			$save["button_type"] = (int)($post["button_type"] ?? 0);
			$save["code_type"] = [];
			$save["reload"] = 0;
			$save["user_request"] = "";
			$save["databases"] = [];
			$save["close_button"] = 2;
			$save["ui_mode"] = 0;
			$save["show_button"] = 0;
			$save["dialog_width"] = 800;

			if($save["button_type"]==1){
				$save["button_title"] = strtolower($save["button_title"]);
				$save["button_title"] = str_replace(" ", "_", $save["button_title"]);
			}

			$ctl->db("additionals")->insert($save);
			$save["sort"] = (int)($save["id"] ?? 0);
			$ctl->db("additionals")->update($save);

			$ctl->close_multi_dialog($this->window . "edit");
			$this->reload_list_area(
				$ctl,
				trim((string) ($post["tb_name"] ?? "")),
				trim((string) ($post["target_area"] ?? "#tabs-buttons")),
				(int) ($post["reload_db_id"] ?? 0)
			);
		} else {
			//$ctl->show_notification_text("There are errors. Please correct them and try again.", 2, "#950000", "#FFF");
		}
	}

	function edit(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		
		$ctl->assign("reload_db_id", (int) ($post["reload_db_id"] ?? 0));
		$ctl->assign("target_area", $post["target_area"] ?? $this->get_target_area($ctl));

		$row = $ctl->db("additionals")->get($id);
		if (!is_array($row)) {
			$row = [];
		}
		
		$ctl->assign("post", $row);

		$database_list = $ctl->db("db", "db")->getall("sort",SORT_ASC);
		$database_names = [];
		foreach ($database_list as $d) {
			$tb_name = (string) ($d["tb_name"] ?? "");
			if ($tb_name === "") {
				continue;
			}
			$database_names[$tb_name] = $tb_name;
		}
		$this->assign_single_tables($ctl, $database_list);
		$ctl->assign("database_names", $database_names);

		$ctl->show_multi_dialog($this->window . "edit", "edit.tpl", $ctl->t("db_additionals.dialog.edit"), 1000);
	}

	function edit_exe(Controller $ctl) {

		$post = $ctl->POST();
		$current = $ctl->db("additionals")->get((int) ($post["id"] ?? 0));
		if (!is_array($current)) {
			$current = [];
		}
		$this->validate_place($ctl, $post);
		$class_name = trim((string)($post["class_name"] ?? ""));
		$function_name = trim((string)($post["function_name"] ?? ""));

		if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $class_name)) {
			$ctl->res_error_message("class_name", $ctl->t("db_additionals.validation.class_name_format"));
		}
		if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $function_name)) {
			$ctl->res_error_message("function_name", $ctl->t("db_additionals.validation.function_name_format"));
		}
		if (empty($post["button_title"])) {
			$ctl->res_error_message("button_title", $ctl->t("validation.required"));
		}
		if (empty($post["tb_name"])) {
			$ctl->res_error_message("place", $ctl->t("validation.required"));
		}

		if ($ctl->count_res_error_message() == 0) {

			$save = $current;
			$save["tb_name"] = trim((string)$post["tb_name"]);
			$save["button_title"] = trim((string)$post["button_title"]);
			$save["class_name"] = $class_name;
				$save["function_name"] = $function_name;
				$save["place"] = (int)($post["place"] ?? 0);
				$save["button_type"] = (int)($post["button_type"] ?? 0);
				$save["show_button"] = (int)($post["show_button"] ?? 0);
				if($save["button_type"]==1){
					$save["button_title"] = strtolower($save["button_title"]);
					$save["button_title"] = str_replace(" ", "_", $save["button_title"]);
				}
			$ctl->db("additionals")->update($save);

			if(($post["close"] ?? null) != "false"){
				$ctl->close_multi_dialog($this->window . "edit");
			}else{
				$ctl->invoke("edit",["id"=>(int) ($post["id"] ?? 0)]);
			}
			
			$ctl->close_multi_dialog($this->window . "edit");
			$this->reload_list_area(
				$ctl,
				trim((string) ($save["tb_name"] ?? "")),
				trim((string) ($post["target_area"] ?? "#tabs-buttons")),
				(int) ($post["reload_db_id"] ?? 0)
			);
		} else {
			$ctl->show_notification_text($ctl->t("db_additionals.validation.fix_errors"), 2, "#950000", "#FFF");
		}
	}

	function delete(Controller $ctl){
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		
		$ctl->assign("reload_db_id", (int) ($post["reload_db_id"] ?? 0));
		$ctl->assign("target_area", $post["target_area"] ?? $this->get_target_area($ctl));
		
		$data = $ctl->db("additionals")->get($id);
		if (!is_array($data)) {
			$data = [];
		}
		$ctl->assign("data",$data);
		
		$ctl->close_multi_dialog("edit");
		
		$ctl->show_multi_dialog($this->window . "delete", "delete.tpl", $ctl->t("db_additionals.dialog.delete"));
	}
	
	function delete_exe(Controller $ctl){
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		
		$data = $ctl->db("additionals")->get($id);
		if (!is_array($data)) {
			$data = [];
		}
		$ctl->db("additionals")->delete($id);
		
		$ctl->close_multi_dialog($this->window . "delete");
		$ctl->close_multi_dialog($this->window . "edit");
		
		if(($post["reload_db_id"] ?? "") != ""){
			if($data["place"]==2 || $data["place"] ==3){
				$ctl->reload_side_panel();
			}else{
				$ctl->reload_work_area();			
			}
		}
		$this->reload_list_area(
			$ctl,
			trim((string) ($data["tb_name"] ?? ($post["tb_name"] ?? ""))),
			trim((string) ($post["target_area"] ?? "#tabs-buttons")),
			(int) ($post["reload_db_id"] ?? 0)
		);
	}

	private function delete_dir_contents(string $dir): bool {
		if (!is_dir($dir)) {
			return false;
		}
		if ($dir === "/" || $dir === "") {
			throw new Exception("Refusing to delete root directory.");
		}
		if (strpos($dir, "/classes/app/") === false) {
			throw new Exception("Refusing to delete outside classes/app.");
		}

		$items = scandir($dir);
		if ($items === false) {
			return false;
		}
		foreach ($items as $item) {
			if ($item === "." || $item === "..") {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) {
				$this->delete_dir_contents($path);
				rmdir($path);
			} else {
				unlink($path);
			}
		}
		return true;
	}

	function button_sort(Controller $ctl){
		$post = $ctl->POST();
		$tb_name = $post["tb_name"] ?? "";
		$place = $post["place"] ?? "";
		$target_area = $this->get_target_area($ctl);
		$reload_db_id = (int) ($post["reload_db_id"] ?? 0);
		
		$list = $ctl->db("additionals")->select(["tb_name","place"],[$tb_name,$place],true,"AND","sort",SORT_ASC);
		$ctl->assign("additionals",$list);
		$ctl->assign("place",$place);
		$ctl->assign("tb_name",$tb_name);
		$ctl->assign("target_area",$target_area);
		$ctl->assign("reload_db_id",$reload_db_id);
		$ctl->show_multi_dialog("button_sort", "sort.tpl", $ctl->t("db_additionals.dialog.sort"));
	}
	
	function button_sort_exe(Controller $ctl){
		$post = $ctl->POST();
		$groups_json = (string) ($post["groups_json"] ?? "");
		if ($groups_json !== "") {
			$groups = json_decode($groups_json, true);
			if (is_array($groups)) {
                foreach ($groups as $place => $ids) {
                    if (!is_array($ids)) { continue; }
                    foreach ($ids as $id) {
                        $button = $ctl->db('additionals')->get((int) $id);
                        if (!$button) { continue; }
                        $button['place'] = $place;
                        if (!$this->validate_place($ctl, $button)) {
                            $ctl->show_notification_text($ctl->t('db.single.top_buttons_only'));
                            return;
                        }
                    }
                }
				foreach ($groups as $place => $ids) {
					if (!is_array($ids)) {
						continue;
					}
					$sort = 0;
					foreach ($ids as $id) {
						$id = (int) $id;
						if ($id <= 0) {
							continue;
						}
						$a = $ctl->db("additionals")->get($id);
						if (!is_array($a) || count($a) === 0) {
							continue;
						}
						$a["place"] = (int) $place;
						$a["sort"] = $sort;
						$ctl->db("additionals")->update($a);
						$sort++;
					}
				}
			}
			$ctl->reload_work_area();
			$ctl->reload_side_panel();
			$this->reload_list_area(
				$ctl,
				trim((string) ($post["tb_name"] ?? "")),
				trim((string) ($post["target_area"] ?? "#tabs-buttons")),
				(int) ($post["reload_db_id"] ?? 0)
			);
			return;
		}
		
		$log = $post["log"] ?? "";
		$ex = explode(",",$log);
		$s = 0;
		foreach($ex as $id){
			$a = $ctl->db("additionals")->get($id);
			$a["sort"] = $s;
			$ctl->db("additionals")->update($a);
			$s++;
		}
		
		if(($post["place"] ?? null) == 0 || ($post["place"] ?? null) == 1){
			$ctl->reload_work_area();
		}else{
			$ctl->reload_side_panel();
		}
		$this->reload_list_area(
			$ctl,
			trim((string) ($post["tb_name"] ?? "")),
			trim((string) ($post["target_area"] ?? "#tabs-buttons")),
			(int) ($post["reload_db_id"] ?? 0)
		);
	}
}
