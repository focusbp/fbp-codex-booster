<?php

class constant_array {

	private $fmt_constant_array;
	private $fmt_values;

	function __construct(Controller $ctl) {
		$this->fmt_constant_array = $ctl->db("constant_array");
		$this->fmt_values = $ctl->db("values");
	}

	//index page

	function page(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$search_name = trim((string) ($post["search_name"] ?? ""));
		$max = $ctl->increment_post_value('max', 10);
		$items = $this->fmt_constant_array->filter(["array_name"], [$search_name], false, 'AND', 'id', SORT_DESC, $max, $is_last);

		$ctl->assign("items", $items);
		$ctl->show_multi_dialog("constant_array", "index.tpl", "Manage Dropdown Options",800);
	}

	function add(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		$ctl->show_multi_dialog("add_constant_array", "add.tpl", "Add Option", 800, true, true);
	}

	//save add data

	function add_exe(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign('post', $post);
		//validation
		$errors = $this->validate_array_data($ctl, $post, "add");
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->add($ctl);
			return;
		}
		$post['updated_at'] = time();
		$id = $this->fmt_constant_array->insert($post);
		//close adding page
		$ctl->close_multi_dialog("add_constant_array");
		$this->page($ctl);
	}

	//validation

	function validate_array_data(Controller $ctl, $post, $page) {
		$errors = [];
		$array_name = trim((string) ($post["array_name"] ?? ""));
		$id = (int) ($post["id"] ?? 0);
		if ($array_name === ""){
			$errors["array_name"] = "Array name is required!";
		}else{
			if (!preg_match('/^[a-z][a-z0-9_]*$/', $array_name)) {
				$errors["array_name"] = "Please use a lowercase identifier.";
			}
			
			if (startsWith($array_name,"table_")){
				$errors["array_name"] = "Starting with table_ is not accetable.";
			}

			$validate_duplicate = $ctl->validate_duplicate('constant_array', 'array_name', $array_name, $id, 'constant_array');
			if (!$validate_duplicate) {
				$errors["array_name"] = $array_name . " is already exist!";
			}
		}
		return $errors;
	}

	function endsWith($haystack, $needle) {
		$length = strlen($needle);
		if (!$length) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}

	//view edit page

	function edit(Controller $ctl, ?int $id = null) {
		if ($id == null)
			$id = $ctl->POST("id");

		$post = $ctl->POST();
		$ctl->assign("post", $post);

		$data = $this->fmt_constant_array->get($id);

		//filter related values to the post (use post_id field)
		$values = $this->fmt_values->select(['constant_array_id'], [$id],true,"AND","sort",SORT_ASC);
		$ctl->assign("values", $values);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("edit_array_" . $id, "edit.tpl", "Edit Option", 800, true, true);
	}

	//save edited data

	function edit_exe(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$data = $this->fmt_constant_array->get($id);
		$rows = $this->extract_rows($post);

		$errors = $this->validate_array_data($ctl, $post, "edit");
		$row_errors = $this->validate_rows($rows);
		foreach ($row_errors as $k => $v) {
			$errors[$k] = $v;
		}
		if (count($errors)) {
			$ctl->clear_error_message();
			foreach ($errors as $field => $message) {
				$ctl->res_error_message($field, $message);
			}
			return;
		}

		$data["array_name"] = trim((string) ($post["array_name"] ?? ""));
		$data["updated_at"] = time();
		$this->fmt_constant_array->update($data);

		$existing = $this->fmt_values->select("constant_array_id", $id);
		$existing_ids = [];
		foreach ($existing as $e) {
			$existing_ids[(int) $e["id"]] = true;
		}

		$keep_ids = [];
		$sort = 0;
		foreach ($rows as $row) {
			$payload = [
				"constant_array_id" => $id,
				"key" => $row["key"],
				"value" => $row["value"],
				"color" => $row["color"] === "" ? "#FFF" : $row["color"],
				"sort" => $sort,
				"updated_at" => time(),
			];
			$sort++;

			if ($row["id"] > 0 && isset($existing_ids[$row["id"]])) {
				$payload["id"] = $row["id"];
				$this->fmt_values->update($payload);
				$keep_ids[$row["id"]] = true;
				continue;
			}
			$new_id = $this->fmt_values->insert($payload);
			$keep_ids[(int) $new_id] = true;
		}

		foreach ($existing as $e) {
			$existing_id = (int) $e["id"];
			if (!isset($keep_ids[$existing_id])) {
				$this->fmt_values->delete($existing_id);
			}
		}

		$ctl->close_multi_dialog("edit_array_" . $id);
		$this->page($ctl);
	}

	//view delete page

	function delete(Controller $ctl) {
		$id = $ctl->POST("id");
		$data = $this->fmt_constant_array->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("delete", "delete.tpl", "Delete Projects", 500, true, true);
	}

	//delete data form database

	function delete_exe(Controller $ctl) {
		$id = $ctl->POST("id");
		//file delete
		$data = $this->fmt_constant_array->get($id);
		//deleting child data
		$values = $this->fmt_values->select(['constant_array_id'], [$id]);
		foreach ($values as $key => $value) {
			$this->fmt_values->delete($value['id']);
		}
		$this->fmt_constant_array->delete($id);
		$ctl->close_multi_dialog("delete");
		$this->page($ctl);
	}

	function sort(Controller $ctl) {
		$post = $ctl->POST();
		$logArr = explode(',', (string) ($post['log'] ?? ''));
		$c = 0;
		foreach ($logArr as $id) {
			$d = $this->fmt_values->get($id);
			$d['sort'] = $c;
			$this->fmt_values->update($d);
			$c++;
		}
	}

	function add_values(Controller $ctl) {
		$constant_array_id = $ctl->POST('constant_array_id');
		$ctl->assign("constant_array_id", $constant_array_id);
		$data = $ctl->POST();
		$ctl->assign("data", $data);
		//var_dump($constant_array_id);
		$ctl->show_multi_dialog("add_values" . $constant_array_id, "add_values.tpl", "Add values", 500, true, true);
	}

	//save values
	function insert_values(Controller $ctl) {
		$data = $ctl->POST();
		//validation
		$errors = $this->validate_values_form($ctl, $data);
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->add_values($ctl);
			return;
		}
		$ctl->assign('data', $data);
		$data['updated_at'] = time();
		$this->fmt_values->insert($data);

		$constant_array_id = $data['constant_array_id'] ?? null;
		$ctl->close_multi_dialog("add_values" . $constant_array_id);
		$this->edit($ctl, $constant_array_id);
	}

	function edit_values(Controller $ctl) {
		$id = $ctl->POST('id');
		$constant_array_id = $ctl->POST('constant_array_id');

		$values = $this->fmt_values->get($id);

		$ctl->assign("id", $id);
		$ctl->assign("values", $values);
		$ctl->assign("constant_array_id", $constant_array_id);
		$ctl->show_multi_dialog("edit_values" . $id, "edit_values.tpl", "Edit values", 500, true, true);
	}

	function edit_values_exe(Controller $ctl) {
		$data = $ctl->POST();
		$constant_array_id = $data['constant_array_id'] ?? null;
		$data['updated_at'] = time();
		//validation
		$errors = $this->validate_values_form($ctl, $data);
		if (count($errors)) {
			$ctl->assign('errors', $errors);
			$this->edit_values($ctl);
			return;
		}

		$this->fmt_values->update($data);

		$ctl->close_multi_dialog("edit_values" . ($data['id'] ?? ""));
		$this->edit($ctl, $constant_array_id);
	}

	function delete_values(Controller $ctl) {
		$id = $ctl->POST("id");
		$values = $this->fmt_values->get($id);
		$ctl->assign("values", $values);
		$ctl->show_multi_dialog("delete_confirmation" . $id, "delete_confirmation.tpl", "Delete values", 1000, true, true);
	}

	function delete_values_exe(Controller $ctl) {

		$id = $ctl->POST('values_id');
		$constant_array_id = $ctl->POST('constant_array_id');
		$this->fmt_values->delete($id);
		$ctl->close_multi_dialog("delete_confirmation" . $id);
		$this->edit($ctl, $constant_array_id);
	}

	//validation values adding function
	function validate_values_form(Controller $ctl, $data) {
		$errors = [];
		$key = trim((string) ($data['key'] ?? ''));
		$constant_array_id = (int) ($data["constant_array_id"] ?? 0);
		$id = (int) ($data["id"] ?? 0);
		$value = trim((string) ($data['value'] ?? ''));

		if ($key === '')
			$errors['key'] = "Key is required!";

		if ($key !== '') {
			$validate_duplicate = $ctl->validate_duplicate('values', ['key', "constant_array_id"], [$key, $constant_array_id], $id, 'constant_array');
			//var_dump($is_duplicate);
			if (!$validate_duplicate) {
				$errors["key"] = $key . " is already exist!";
			}
		}
		//die();
		if ($value === '')
			$errors['value'] = "Value is required!";

		return $errors;
	}

	private function extract_rows($post) {
		$rows = [];
		$ids = $post["value_id"] ?? [];
		$keys = $post["value_key"] ?? [];
		$values = $post["value_label"] ?? [];
		$colors = $post["value_color"] ?? [];
		$count = max(count($ids), count($keys), count($values), count($colors));

		for ($i = 0; $i < $count; $i++) {
			$row_id = isset($ids[$i]) ? (int) $ids[$i] : 0;
			$row_key = trim((string) ($keys[$i] ?? ""));
			$row_value = trim((string) ($values[$i] ?? ""));
			$row_color = trim((string) ($colors[$i] ?? ""));

			if ($row_key === "" && $row_value === "") {
				continue;
			}
			$rows[] = [
				"id" => $row_id,
				"key" => $row_key,
				"value" => $row_value,
				"color" => $row_color,
			];
		}

		return $rows;
	}

	private function validate_rows($rows) {
		$errors = [];
		$seen = [];

		foreach ($rows as $row) {
			$key = $row["key"];
			$value = $row["value"];

			if ($key === "") {
				$errors["rows"] = "Each row needs a value.";
				continue;
			}
			if (!preg_match('/^\d+$/', $key)) {
				$errors["rows"] = "Each value must be numeric.";
				continue;
			}
			if ($value === "") {
				$errors["rows"] = "Each row needs a label.";
				continue;
			}
			if (isset($seen[$key])) {
				$errors["rows"] = "Duplicate value " . $key . " is not allowed.";
				continue;
			}
			$seen[$key] = true;
		}

		return $errors;
	}
	
}
