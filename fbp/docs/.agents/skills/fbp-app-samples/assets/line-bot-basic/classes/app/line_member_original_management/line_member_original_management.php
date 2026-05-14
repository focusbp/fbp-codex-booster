<?php

class line_member_original_management {

	private function tableName(): string {
		return "line_member";
	}

	private function listAreaId(): string {
		return "#line_member_original_management_list_area";
	}

	private function formFields(): array {
		return [
			"line_name",
			"userid",
			"name",
			"member_type",
		];
	}

	private function emptyRow(): array {
		return [
			"line_name" => "",
			"userid" => "",
			"name" => "",
			"member_type" => 0,
		];
	}

	private function memberTypeOptions(Controller $ctl): array {
		return $ctl->get_constant_array("member_type_opt", false);
	}

	private function assignRows(Controller $ctl): void {
		$is_search = ((string) ($ctl->POST("_line_member_search") ?? "") === "1");
		$search = [
			"keyword" => $is_search ? trim((string) ($ctl->POST("keyword") ?? "")) : "",
			"member_type" => $is_search ? trim((string) ($ctl->POST("member_type") ?? "")) : "",
		];
		$rows = $this->filteredRows($ctl, $search);
		$member_type_options = $this->memberTypeOptions($ctl);

		$ctl->assign("rows", $rows);
		$ctl->assign("count", count($rows));
		$ctl->assign("search", $search);
		$ctl->assign("member_type_options", $member_type_options);
		$ctl->assign("member_type_filter_options", ["" => "All Types"] + $member_type_options);
	}

	private function filteredRows(Controller $ctl, array $search): array {
		$rows = $ctl->db($this->tableName())->getall("id", SORT_DESC);
		$keyword = mb_strtolower((string) ($search["keyword"] ?? ""));
		$member_type = (string) ($search["member_type"] ?? "");

		if ($keyword === "" && $member_type === "") {
			return $rows;
		}

		return array_values(array_filter($rows, function ($row) use ($keyword, $member_type) {
			if ($member_type !== "" && (string) ($row["member_type"] ?? "") !== $member_type) {
				return false;
			}
			if ($keyword === "") {
				return true;
			}
			foreach (["id", "line_name", "userid", "name"] as $field) {
				if (mb_stripos((string) ($row[$field] ?? ""), $keyword) !== false) {
					return true;
				}
			}
			return false;
		}));
	}

	private function rowFromPost(Controller $ctl, array $base = []): array {
		$row = array_merge($this->emptyRow(), $base);
		foreach ($this->formFields() as $field) {
			$row[$field] = trim((string) ($ctl->POST($field) ?? ""));
		}
		if ($row["member_type"] === "") {
			$row["member_type"] = 0;
		}
		return $row;
	}

	private function validateRow(Controller $ctl, array $row): bool {
		$ctl->validate($this->tableName(), $this->formFields(), $row);
		$options = $this->memberTypeOptions($ctl);
		if (!isset($options[$row["member_type"]])) {
			$ctl->res_error_message("member_type", "Select a valid member type.");
		}
		return $ctl->count_res_error_message() === 0;
	}

	function run(Controller $ctl): void {
		$ctl->set_session("__AUTO_LOAD_MAIN_AREA", [
			"class" => __CLASS__,
			"function" => "run",
			"parameters" => [],
		]);
		$this->assignRows($ctl);
		$ctl->show_main_area("list.tpl", "LINE Members");
	}

	function reload_list(Controller $ctl): void {
		$this->assignRows($ctl);
		$ctl->reload_area($this->listAreaId(), "list_area.tpl");
	}

	function add_dialog(Controller $ctl): void {
		$ctl->assign("row", $this->emptyRow());
		$ctl->assign("form_fields", $this->formFields());
		$ctl->show_multi_dialog("line_member_original_management_add", "add.tpl", "Add LINE Member", 700);
	}

	function add_save(Controller $ctl): void {
		$row = $this->rowFromPost($ctl);
		if (!$this->validateRow($ctl, $row)) {
			return;
		}
		$ctl->db($this->tableName())->insert($row);
		$ctl->close_multi_dialog("line_member_original_management_add");
		$this->reload_list($ctl);
	}

	function edit_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("LINE member not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->assign("form_fields", $this->formFields());
		$ctl->show_multi_dialog("line_member_original_management_edit", "edit.tpl", "Edit LINE Member", 700);
	}

	function edit_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$existing = $ctl->db($this->tableName())->get($id);
		if (empty($existing)) {
			$ctl->show_notification_text("LINE member not found.");
			return;
		}
		$row = $this->rowFromPost($ctl, $existing);
		$row["id"] = $id;
		if (!$this->validateRow($ctl, $row)) {
			return;
		}
		$ctl->db($this->tableName())->update($row);
		$ctl->close_multi_dialog("line_member_original_management_edit");
		$this->reload_list($ctl);
	}

	function delete_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("LINE member not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->show_multi_dialog("line_member_original_management_delete", "delete_confirm.tpl", "Delete LINE Member", 520);
	}

	function delete_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("LINE member not found.");
			return;
		}
		$ctl->db($this->tableName())->delete($id);
		$ctl->close_multi_dialog("line_member_original_management_delete");
		$this->reload_list($ctl);
	}
}
