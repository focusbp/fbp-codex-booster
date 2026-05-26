<?php

class panel_constants {

	private $fmt_constant_array;
	private $fmt_values;

	function __construct(Controller $ctl) {
		$this->fmt_constant_array = $ctl->db("constant_array", "constant_array");
		$this->fmt_values = $ctl->db("values", "constant_array");
	}

	function page(Controller $ctl) {
		$items = $this->fmt_constant_array->getall("array_name", SORT_ASC);
		foreach ($items as &$item) {
			$rows = $this->fmt_values->select("constant_array_id", (int) ($item["id"] ?? 0), true, "AND", "sort", SORT_ASC);
			$sets = [];
			foreach ($rows as $row) {
				$sets[] = [
					"key" => (string) ($row["key"] ?? ""),
					"title" => (string) ($row["value"] ?? ""),
					"color" => (string) (($row["color"] ?? "") !== "" ? $row["color"] : "#FFF"),
				];
			}
			$item["value_sets"] = $sets;
		}
		unset($item);
		$ctl->assign("items", $items);
		$ctl->reload_area("#tabs-constants", "index.tpl");
	}

	function add(Controller $ctl) {
		$post = $ctl->POST();
		$ctl->assign("post", $post);
		$ctl->show_multi_dialog("panel_constants_add", "add.tpl", $ctl->t("panel_constants.dialog.add"), 700, true, true);
	}

	function add_exe(Controller $ctl) {
		$post = $ctl->POST();

		$errors = $this->validate_array($ctl, $post, "add");
		if (count($errors)) {
			$ctl->clear_error_message();
			foreach ($errors as $field => $message) {
				$ctl->res_error_message($field, $message);
			}
			return;
		}

		$post["array_name"] = trim((string) ($post["array_name"] ?? ""));
		$post["updated_at"] = time();
		$this->fmt_constant_array->insert($post);

		$ctl->close_multi_dialog("panel_constants_add");
		$this->page($ctl);
	}

	function edit(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$post = $ctl->POST();
		$data = $this->fmt_constant_array->get($id);
		$values = $this->fmt_values->select("constant_array_id", $id, true, "AND", "sort", SORT_ASC);

		$ctl->assign("post", $post);
		$ctl->assign("data", $data);
		$ctl->assign("values", $values);
		$ctl->show_multi_dialog("panel_constants_edit_" . $id, "edit.tpl", $ctl->t("panel_constants.dialog.edit"), 1000, "_edit_button.tpl", true);
	}

	function edit_exe(Controller $ctl) {
		$post = $ctl->POST();
		$id = (int) ($post["id"] ?? 0);
		$data = $this->fmt_constant_array->get($id);
		$rows = $this->extract_rows($post);

		$errors = $this->validate_array($ctl, $post, "edit");
		$row_errors = $this->validate_rows($ctl, $rows);
		foreach ($row_errors as $key => $value) {
			$errors[$key] = $value;
		}

		if (count($errors)) {
			if (!empty($errors["rows"])) {
				$ctl->show_notification_text($errors["rows"], 3, "#950000", "#FFF");
				return;
			}
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

		$ctl->close_multi_dialog("panel_constants_edit_" . $id);
		$this->page($ctl);
	}

	function delete(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$data = $this->fmt_constant_array->get($id);
		$ctl->assign("data", $data);
		$ctl->show_multi_dialog("panel_constants_delete_" . $id, "delete.tpl", $ctl->t("panel_constants.dialog.delete"), 500, true, true);
	}

	function delete_exe(Controller $ctl) {
		$id = (int) $ctl->POST("id");
		$values = $this->fmt_values->select("constant_array_id", $id);
		foreach ($values as $value) {
			$this->fmt_values->delete((int) $value["id"]);
		}
		$this->fmt_constant_array->delete($id);
		$ctl->close_multi_dialog("panel_constants_delete_" . $id);
		$this->page($ctl);
	}

	private function validate_array(Controller $ctl, $post, $mode) {
		$errors = [];
		$array_name = trim((string) ($post["array_name"] ?? ""));
		$id = (int) ($post["id"] ?? 0);

		if ($array_name === "") {
			$errors["array_name"] = $ctl->t("panel_constants.validation.array_name_required");
			return $errors;
		}
		if (substr($array_name, -4) !== "_opt") {
			$errors["array_name"] = $ctl->t("panel_constants.validation.array_name_suffix");
			return $errors;
		}
		if (strpos($array_name, "table_") === 0) {
			$errors["array_name"] = $ctl->t("panel_constants.validation.array_name_prefix");
			return $errors;
		}

		$is_unique = $ctl->validate_duplicate("constant_array", "array_name", $array_name, $mode === "edit" ? $id : null, "constant_array");
		if (!$is_unique) {
			$errors["array_name"] = $ctl->t("panel_constants.validation.array_name_exists", ["name" => $array_name]);
		}

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

	private function is_plain_integer_key($key): bool {
		return preg_match('/^(0|[1-9][0-9]*)$/', (string) $key) === 1;
	}

	private function validate_rows(Controller $ctl, $rows) {
		$errors = [];
		$seen = [];

		foreach ($rows as $row) {
			$key = $row["key"];
			$value = $row["value"];

			if ($key === "") {
				$errors["rows"] = $ctl->t("panel_constants.validation.row_value_required");
				continue;
			}
			if (!$this->is_plain_integer_key($key)) {
				$errors["rows"] = $ctl->t("panel_constants.validation.row_value_numeric");
				continue;
			}
			if ($value === "") {
				$errors["rows"] = $ctl->t("panel_constants.validation.row_label_required");
				continue;
			}
			if (isset($seen[$key])) {
				$errors["rows"] = $ctl->t("panel_constants.validation.row_value_duplicate", ["value" => $key]);
				continue;
			}
			$seen[$key] = true;
		}

		return $errors;
	}
}
