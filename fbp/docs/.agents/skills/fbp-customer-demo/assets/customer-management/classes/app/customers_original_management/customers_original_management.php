<?php

class customers_original_management
{
	private function tableName(): string {
		return "customers";
	}

	private function listAreaId(): string {
		return "#customers_original_management_list_area";
	}

	private function formFields(): array {
		return [
			"company_name",
			"contact_name",
			"email",
			"phone",
			"postal_code",
			"address",
			"status",
			"memo",
		];
	}

	private function listFields(): array {
		return [
			"id",
			"company_name",
			"contact_name",
			"email",
			"phone",
			"status",
		];
	}

	private function rememberMainArea(Controller $ctl): void {
		$ctl->set_session("__AUTO_LOAD_MAIN_AREA", [
			"class" => __CLASS__,
			"function" => "run",
			"parameters" => [],
		]);
	}

	private function emptyRow(): array {
		return [
			"company_name" => "",
			"contact_name" => "",
			"email" => "",
			"phone" => "",
			"postal_code" => "",
			"address" => "",
			"status" => "prospect",
			"memo" => "",
			"created_at" => "",
			"updated_at" => "",
		];
	}

	private function statusOptions(Controller $ctl): array {
		return $ctl->get_constant_array("customer_status", false);
	}

	private function assignRows(Controller $ctl): void {
		$isSearchRequest = ((string) ($ctl->POST("_customers_search") ?? "") === "1");
		$search = [
			"keyword" => $isSearchRequest ? trim((string) ($ctl->POST("keyword") ?? "")) : "",
			"status" => $isSearchRequest ? trim((string) ($ctl->POST("status") ?? "")) : "",
		];
		$rows = $this->filteredRows($ctl, $search);
		$ctl->assign("rows", $rows);
		$ctl->assign("count", count($rows));
		$ctl->assign("search", $search);
		$ctl->assign("list_fields", $this->listFields());
		$ctl->assign("status_options", $this->statusOptions($ctl));
		$ctl->assign("status_filter_options", ["" => "All Statuses"] + $this->statusOptions($ctl));
	}

	private function filteredRows(Controller $ctl, array $search): array {
		$rows = $ctl->db($this->tableName())->getall("id", SORT_DESC);
		$keyword = mb_strtolower((string) ($search["keyword"] ?? ""));
		$status = (string) ($search["status"] ?? "");

		if ($keyword === "" && $status === "") {
			return $rows;
		}

		$searchFields = ["id", "company_name", "contact_name", "email", "phone"];
		return array_values(array_filter($rows, function ($row) use ($keyword, $status, $searchFields) {
			if ($status !== "" && (string) ($row["status"] ?? "") !== $status) {
				return false;
			}
			if ($keyword === "") {
				return true;
			}
			foreach ($searchFields as $field) {
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
		if ($row["status"] === "") {
			$row["status"] = "prospect";
		}
		return $row;
	}

	private function validateRow(Controller $ctl, array $row, int $id = 0): bool {
		$ctl->validate($this->tableName(), $this->formFields(), $row);
		$statusOptions = $this->statusOptions($ctl);
		if (!isset($statusOptions[$row["status"]])) {
			$ctl->res_error_message("status", "Select a valid status.");
		}

		return $ctl->count_res_error_message() === 0;
	}

	function run(Controller $ctl): void {
		$this->rememberMainArea($ctl);
		$this->assignRows($ctl);
		$ctl->show_main_area("list.tpl", "Customers");
	}

	function reload_list(Controller $ctl): void {
		$this->assignRows($ctl);
		$ctl->reload_area($this->listAreaId(), "list_area.tpl");
	}

	function add_dialog(Controller $ctl): void {
		$ctl->assign("row", $this->emptyRow());
		$ctl->assign("form_fields", $this->formFields());
		$ctl->show_multi_dialog("customers_original_management_add", "add.tpl", "Add Customer", 800);
	}

	function add_save(Controller $ctl): void {
		$row = $this->rowFromPost($ctl);
		if (!$this->validateRow($ctl, $row)) {
			return;
		}

		$now = time();
		$row["created_at"] = $now;
		$row["updated_at"] = $now;
		$ctl->db($this->tableName())->insert($row);
		$ctl->close_multi_dialog("customers_original_management_add");
		$this->reload_list($ctl);
	}

	function edit_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Customer not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->assign("form_fields", $this->formFields());
		$ctl->show_multi_dialog("customers_original_management_edit", "edit.tpl", "Edit Customer", 800);
	}

	function edit_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$existing = $ctl->db($this->tableName())->get($id);
		if (empty($existing)) {
			$ctl->show_notification_text("Customer not found.");
			return;
		}

		$row = $this->rowFromPost($ctl, $existing);
		$row["id"] = $id;
		if (!$this->validateRow($ctl, $row, $id)) {
			return;
		}

		$row["created_at"] = $existing["created_at"] ?? "";
		$row["updated_at"] = time();
		$ctl->db($this->tableName())->update($row);
		$ctl->close_multi_dialog("customers_original_management_edit");
		$this->reload_list($ctl);
	}

	function detail_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Customer not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->assign("detail_fields", [
			"id",
			"company_name",
			"contact_name",
			"email",
			"phone",
			"postal_code",
			"address",
			"status",
			"memo",
			"created_at",
			"updated_at",
		]);
		$ctl->show_multi_dialog("customers_original_management_detail", "detail.tpl", "Customer Detail", 760);
	}

	function delete_dialog(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Customer not found.");
			return;
		}
		$ctl->assign("row", $row);
		$ctl->show_multi_dialog("customers_original_management_delete", "delete_confirm.tpl", "Delete Customer", 520);
	}

	function delete_save(Controller $ctl): void {
		$id = (int) ($ctl->POST("id") ?? 0);
		$row = $ctl->db($this->tableName())->get($id);
		if (empty($row)) {
			$ctl->show_notification_text("Customer not found.");
			return;
		}
		$ctl->db($this->tableName())->delete($id);
		$ctl->close_multi_dialog("customers_original_management_delete");
		$this->reload_list($ctl);
	}
}
