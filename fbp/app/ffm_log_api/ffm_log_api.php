<?php

class ffm_log_api {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function list(Controller $ctl) {
		if ($ctl->verify_release_api_request() !== true) {
			exit;
		}

		$include_data = $this->normalize_bool($ctl->GET("include_data"));
		$limit = $this->normalize_limit($ctl->GET("limit"), 100);
		$filters = [
			"date" => $this->normalize_date($ctl->GET("date"), false),
			"txid" => $this->normalize_optional_string($ctl->GET("txid")),
			"table" => $this->normalize_name($ctl->GET("table"), "table", false),
			"class" => $this->normalize_name($ctl->GET("class_name") ?: $ctl->GET("dbclass"), "class", false),
			"operation" => $this->normalize_optional_string($ctl->GET("operation")),
			"id" => $this->normalize_optional_id($ctl->GET("id")),
		];

		$items = $this->read_log_entries($ctl, $filters, $include_data, $limit);
		$this->respond_json([
			"ok" => true,
			"count" => count($items),
			"include_data" => $include_data,
			"items" => $items,
		]);
	}

	function get(Controller $ctl) {
		if ($ctl->verify_release_api_request() !== true) {
			exit;
		}

		$txid = $this->normalize_required_txid($ctl->GET("txid"));
		$date = $this->normalize_date($ctl->GET("date"), false);
		$entry = $this->find_log_entry($ctl, $txid, $date);
		if ($entry === null) {
			$this->respond_error(404, "not_found", "ffm log entry was not found");
		}
		$this->respond_json([
			"ok" => true,
			"item" => $entry,
		]);
	}

	function restore(Controller $ctl) {
		if ($ctl->verify_release_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$txid = $this->normalize_required_txid($payload["txid"] ?? "");
		$date = $this->normalize_date($payload["date"] ?? "", false);
		$mode = $this->normalize_optional_string($payload["mode"] ?? "auto") ?: "auto";
		$fields = $this->normalize_fields($payload["fields"] ?? []);
		$conflict_check = array_key_exists("conflict_check", $payload) ? $this->normalize_bool($payload["conflict_check"]) : true;

		$entry = $this->find_log_entry($ctl, $txid, $date);
		if ($entry === null) {
			$this->respond_error(404, "not_found", "ffm log entry was not found");
		}

		$result = $this->restore_entry($ctl, $entry, $mode, $fields, $conflict_check);
		$this->respond_json(array_merge([
			"ok" => true,
			"txid" => $txid,
		], $result));
	}

	private function restore_entry(Controller $ctl, array $entry, string $mode, array $fields, bool $conflict_check): array {
		$operation = (string) ($entry["operation"] ?? "");
		if ($mode === "auto") {
			if ($operation === "insert") {
				$mode = "insert_undo";
			} else if ($operation === "update") {
				$mode = count($fields) > 0 ? "update_partial" : "update_full";
			} else if ($operation === "delete") {
				$mode = "delete_restore";
			} else {
				$this->respond_error(400, "unsupported_operation", "operation cannot be restored automatically", [
					"operation" => $operation,
				]);
			}
		}

		$table = $this->normalize_name((string) ($entry["table"] ?? ""), "table", true);
		$class = $this->normalize_name((string) ($entry["class"] ?? "common"), "class", false) ?: "common";
		$id = $this->normalize_optional_id($entry["id"] ?? null);
		if ($id === null) {
			$this->respond_error(400, "id_required", "log entry id is required");
		}

		$before = $this->normalize_row($entry["before"] ?? null);
		$after = $this->normalize_row($entry["after"] ?? null);
		$ffm = $ctl->db($table, $class);

		if ($mode === "insert_undo") {
			$current = $this->normalize_row($ffm->get($id));
			if ($current === null) {
				return [
					"mode" => $mode,
					"table" => $table,
					"class" => $class,
					"id" => $id,
					"status" => "already_deleted",
					"item" => null,
				];
			}
			if ($conflict_check && $after !== null) {
				$this->assert_rows_match($current, $after, array_keys($after), "current row differs from logged insert data");
			}
			$ffm->delete($id);
			return [
				"mode" => $mode,
				"table" => $table,
				"class" => $class,
				"id" => $id,
				"status" => "deleted",
				"item" => null,
			];
		}

		if ($mode === "update_full" || $mode === "update_partial") {
			if ($before === null) {
				$this->respond_error(400, "before_required", "before data is required for update restore");
			}
			$current = $this->normalize_row($ffm->get($id));
			if ($current === null) {
				$this->respond_error(404, "item_not_found", "current row was not found", [
					"table" => $table,
					"id" => $id,
				]);
			}

			if ($mode === "update_full") {
				$restore_fields = array_keys($before);
				$next = $before;
			} else {
				if (count($fields) === 0) {
					$this->respond_error(400, "fields_required", "fields are required for update_partial");
				}
				$restore_fields = $fields;
				$next = $current;
				foreach ($fields as $field) {
					if (!array_key_exists($field, $before)) {
						$this->respond_error(400, "field_not_in_before", "field is not included in before data", [
							"field" => $field,
						]);
					}
					$next[$field] = $before[$field];
				}
			}
			$next["id"] = $id;
			if ($conflict_check && $after !== null) {
				$this->assert_rows_match($current, $after, $restore_fields, "current row differs from logged after data");
			}
			$ffm->update($next);
			return [
				"mode" => $mode,
				"table" => $table,
				"class" => $class,
				"id" => $id,
				"status" => "updated",
				"fields" => array_values($restore_fields),
				"item" => $ffm->get($id),
			];
		}

		if ($mode === "delete_restore") {
			if ($before === null) {
				$this->respond_error(400, "before_required", "before data is required for delete restore");
			}
			$before["id"] = $id;
			$current = $this->normalize_row($ffm->get($id));
			if ($current !== null) {
				$this->respond_error(409, "restore_conflict", "record is already active", [
					"table" => $table,
					"id" => $id,
				]);
			}
			try {
				$ffm->restore_deleted_record($before);
			} catch (Throwable $e) {
				$this->respond_error(409, "restore_failed", $e->getMessage(), [
					"table" => $table,
					"id" => $id,
				]);
			}
			return [
				"mode" => $mode,
				"table" => $table,
				"class" => $class,
				"id" => $id,
				"status" => "restored",
				"item" => $ffm->get($id),
			];
		}

		$this->respond_error(400, "invalid_mode", "mode is invalid", [
			"mode" => $mode,
		]);
	}

	private function assert_rows_match(array $current, array $expected, array $fields, string $message): void {
		foreach ($fields as $field) {
			if ($field === "_id_enc") {
				continue;
			}
			if (($current[$field] ?? null) != ($expected[$field] ?? null)) {
				$this->respond_error(409, "restore_conflict", $message, [
					"field" => $field,
					"current" => $current[$field] ?? null,
					"expected" => $expected[$field] ?? null,
				]);
			}
		}
	}

	private function read_log_entries(Controller $ctl, array $filters, bool $include_data, int $limit): array {
		$items = [];
		foreach ($this->get_log_files($ctl, $filters["date"] ?? null) as $file) {
			$lines = file($file, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				continue;
			}
			for ($i = count($lines) - 1; $i >= 0; $i--) {
				$entry = json_decode((string) $lines[$i], true);
				if (!is_array($entry) || !$this->matches_filters($entry, $filters)) {
					continue;
				}
				$entry["_date"] = pathinfo($file, PATHINFO_FILENAME);
				$entry["_line"] = $i + 1;
				if (!$include_data) {
					$entry["has_before"] = array_key_exists("before", $entry) && $entry["before"] !== null;
					$entry["has_after"] = array_key_exists("after", $entry) && $entry["after"] !== null;
					unset($entry["before"], $entry["after"]);
				}
				$items[] = $entry;
				if (count($items) >= $limit) {
					return $items;
				}
			}
		}
		return $items;
	}

	private function find_log_entry(Controller $ctl, string $txid, ?string $date): ?array {
		foreach ($this->get_log_files($ctl, $date) as $file) {
			$lines = file($file, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				continue;
			}
			for ($i = count($lines) - 1; $i >= 0; $i--) {
				$entry = json_decode((string) $lines[$i], true);
				if (is_array($entry) && (string) ($entry["txid"] ?? "") === $txid) {
					$entry["_date"] = pathinfo($file, PATHINFO_FILENAME);
					$entry["_line"] = $i + 1;
					return $entry;
				}
			}
		}
		return null;
	}

	private function get_log_files(Controller $ctl, ?string $date): array {
		$log_dir = rtrim($ctl->dirs->logdir, "/") . "/ffm";
		if (!is_dir($log_dir)) {
			return [];
		}
		if ($date !== null) {
			$file = $log_dir . "/" . $date . ".jsonl";
			return is_file($file) ? [$file] : [];
		}
		$files = glob($log_dir . "/*.jsonl");
		if ($files === false) {
			return [];
		}
		rsort($files, SORT_STRING);
		return $files;
	}

	private function matches_filters(array $entry, array $filters): bool {
		if (($filters["txid"] ?? null) !== null && (string) ($entry["txid"] ?? "") !== (string) $filters["txid"]) {
			return false;
		}
		if (($filters["table"] ?? null) !== null && (string) ($entry["table"] ?? "") !== (string) $filters["table"]) {
			return false;
		}
		if (($filters["class"] ?? null) !== null && (string) ($entry["class"] ?? "") !== (string) $filters["class"]) {
			return false;
		}
		if (($filters["operation"] ?? null) !== null && (string) ($entry["operation"] ?? "") !== (string) $filters["operation"]) {
			return false;
		}
		if (($filters["id"] ?? null) !== null && (int) ($entry["id"] ?? 0) !== (int) $filters["id"]) {
			return false;
		}
		return true;
	}

	private function read_json_request(): array {
		$raw = file_get_contents("php://input");
		if (!is_string($raw) || trim($raw) === "") {
			$this->respond_error(400, "invalid_arguments", "json body is required");
		}
		$payload = json_decode($raw, true);
		if (!is_array($payload)) {
			$this->respond_error(400, "invalid_arguments", "json body must be an object");
		}
		return $payload;
	}

	private function normalize_row($value): ?array {
		if (!is_array($value)) {
			return null;
		}
		unset($value["_id_enc"]);
		return $value;
	}

	private function normalize_fields($value): array {
		if (is_string($value)) {
			$value = array_filter(array_map("trim", explode(",", $value)), static function ($v) {
				return $v !== "";
			});
		}
		if (!is_array($value)) {
			$this->respond_error(400, "invalid_fields", "fields must be array or comma-separated string");
		}
		$fields = [];
		foreach ($value as $field) {
			$field = $this->normalize_name($field, "field", true);
			if ($field === "id") {
				continue;
			}
			$fields[] = $field;
		}
		return array_values(array_unique($fields));
	}

	private function normalize_name($value, string $name, bool $required): ?string {
		$value = trim((string) $value);
		if ($value === "") {
			if ($required) {
				$this->respond_error(400, $name . "_required", $name . " is required");
			}
			return null;
		}
		if (!preg_match('/^[a-z0-9_]+$/', $value)) {
			$this->respond_error(400, "invalid_" . $name, $name . " must match ^[a-z0-9_]+$");
		}
		return $value;
	}

	private function normalize_required_txid($value): string {
		$value = trim((string) $value);
		if ($value === "") {
			$this->respond_error(400, "txid_required", "txid is required");
		}
		if (!preg_match('/^FFM-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}$/', $value)) {
			$this->respond_error(400, "invalid_txid", "txid is invalid");
		}
		return $value;
	}

	private function normalize_date($value, bool $required): ?string {
		$value = trim((string) $value);
		if ($value === "") {
			if ($required) {
				$this->respond_error(400, "date_required", "date is required");
			}
			return null;
		}
		if (!preg_match('/^[0-9]{8}$/', $value)) {
			$this->respond_error(400, "invalid_date", "date must be yyyymmdd");
		}
		return $value;
	}

	private function normalize_optional_string($value): ?string {
		$value = trim((string) $value);
		return $value === "" ? null : $value;
	}

	private function normalize_optional_id($value): ?int {
		if ($value === null || $value === "") {
			return null;
		}
		$value = trim((string) $value);
		if (!ctype_digit($value) || (int) $value <= 0) {
			$this->respond_error(400, "invalid_id", "id must be positive numeric");
		}
		return (int) $value;
	}

	private function normalize_limit($value, int $default): int {
		if ($value === null || $value === "") {
			return $default;
		}
		$value = trim((string) $value);
		if (!ctype_digit($value) || (int) $value <= 0) {
			$this->respond_error(400, "invalid_limit", "limit must be positive numeric");
		}
		return min((int) $value, 1000);
	}

	private function normalize_bool($value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value !== 0;
		}
		$value = strtolower(trim((string) $value));
		return in_array($value, ["1", "true", "yes", "on"], true);
	}

	private function respond_json(array $payload): void {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	private function respond_error(int $http_code, string $error_code, string $error, array $extra = []): void {
		http_response_code($http_code);
		$payload = array_merge([
			"ok" => false,
			"error_code" => $error_code,
			"error" => $error,
		], $extra);
		$this->respond_json($payload);
	}
}
