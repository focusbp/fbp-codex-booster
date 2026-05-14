<?php

class customers_csv
{
	private function tableName(): string {
		return "customers";
	}

	private function columns(): array {
		return [
			"id",
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

	private function statusOptions(Controller $ctl): array {
		return $ctl->get_constant_array("customer_status", false);
	}

	function download(Controller $ctl): void {
		header("Content-Type: text/csv; charset=UTF-8");
		$ctl->res_csv($this->columns(), "UTF-8");
		$rows = $ctl->db($this->tableName())->getall("id", SORT_ASC);
		foreach ($rows as $row) {
			$line = [];
			foreach ($this->columns() as $column) {
				$line[] = (string) ($row[$column] ?? "");
			}
			$ctl->res_csv($line, "UTF-8");
		}
	}

	function upload_form(Controller $ctl): void {
		$ctl->show_multi_dialog("customers_csv_upload", "upload.tpl", "Import Customers CSV", 640);
	}

	function upload_exe(Controller $ctl): void {
		$file = $_FILES["file"] ?? null;
		if (!$this->isValidUploadedFile($file)) {
			$ctl->res_error_message("file", "Select a CSV file.");
			return;
		}

		$rows = $this->readCsvRows($ctl, (string) $file["tmp_name"]);
		if ($ctl->count_res_error_message() > 0) {
			return;
		}

		$ffm = $ctl->db($this->tableName());
		$now = time();
		foreach ($rows as $row) {
			$id = (int) ($row["id"] ?? 0);
			$existing = $id > 0 ? $ffm->get($id) : [];
			if (!empty($existing)) {
				foreach ($this->columns() as $column) {
					if ($column === "id") {
						continue;
					}
					$existing[$column] = $row[$column];
				}
				$existing["updated_at"] = $now;
				$ffm->update($existing);
				continue;
			}

			unset($row["id"]);
			$row["created_at"] = $now;
			$row["updated_at"] = $now;
			$ffm->insert($row);
		}

		$ctl->close_multi_dialog("customers_csv_upload");
		$ctl->invoke("reload_list", [], "customers_original_management");
	}

	private function isValidUploadedFile($file): bool {
		if (!is_array($file) || (int) ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return false;
		}
		$tmpName = (string) ($file["tmp_name"] ?? "");
		if ($tmpName === "") {
			return false;
		}
		if (defined("CLI_APP_CALL")) {
			return is_file($tmpName);
		}
		return is_uploaded_file($tmpName);
	}

	private function readCsvRows(Controller $ctl, string $path): array {
		$handle = fopen($path, "rb");
		if ($handle === false) {
			$ctl->res_error_message("file", "Could not open CSV file.");
			return [];
		}

		$header = fgetcsv($handle);
		if ($header === false) {
			fclose($handle);
			$ctl->res_error_message("file", "CSV file is empty.");
			return [];
		}
		if (isset($header[0])) {
			$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
		}
		if ($header !== $this->columns()) {
			fclose($handle);
			$ctl->res_error_message("file", "CSV header must be: " . implode(",", $this->columns()));
			return [];
		}

		$rows = [];
		$lineNumber = 1;
		while (($data = fgetcsv($handle)) !== false) {
			$lineNumber++;
			if ($this->isEmptyCsvLine($data)) {
				continue;
			}
			if (count($data) !== count($this->columns())) {
				fclose($handle);
				$ctl->res_error_message("file", "Line {$lineNumber}: column count does not match.");
				return [];
			}
			$row = array_combine($this->columns(), array_map(function ($value) {
				return trim((string) $value);
			}, $data));
			if (!$this->validateImportRow($ctl, $row, $lineNumber)) {
				fclose($handle);
				return [];
			}
			$rows[] = $row;
		}

		fclose($handle);
		return $rows;
	}

	private function isEmptyCsvLine(array $data): bool {
		foreach ($data as $value) {
			if (trim((string) $value) !== "") {
				return false;
			}
		}
		return true;
	}

	private function validateImportRow(Controller $ctl, array $row, int $lineNumber): bool {
		$id = (string) ($row["id"] ?? "");
		if ($id !== "" && (!ctype_digit($id) || (int) $id <= 0)) {
			$ctl->res_error_message("file", "Line {$lineNumber}: id must be empty or a positive number.");
			return false;
		}
		foreach (["company_name", "status"] as $required) {
			if ((string) ($row[$required] ?? "") === "") {
				$ctl->res_error_message("file", "Line {$lineNumber}: {$required} is required.");
				return false;
			}
		}
		if (!isset($this->statusOptions($ctl)[$row["status"]])) {
			$ctl->res_error_message("file", "Line {$lineNumber}: status must be prospect, active, or inactive.");
			return false;
		}
		return true;
	}
}
