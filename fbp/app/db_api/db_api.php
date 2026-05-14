<?php

class db_api {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function tables(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$items = [];
		$items = array_merge($items, $this->collect_fmt_tables($ctl->dirs->appdir_user, "user"));
		$items = array_merge($items, $this->collect_fmt_tables($ctl->dirs->appdir_fw, "framework"));
		$items = array_merge($items, $this->collect_db_tables($ctl));

		$this->respond_json([
			"ok" => true,
			"count" => count($items),
			"items" => array_values($items),
		]);
	}

	function describe(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$table = trim((string) $ctl->GET("table"));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $ctl->GET("dbclass"));
		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"fmt_path" => $resolved["fmt_path"],
			"dat_path" => $resolved["dat_path"],
			"exists_fmt" => true,
			"exists_dat" => true,
			"fields" => $this->parse_fmt_fields($resolved["fmt_path"]),
		]);
	}

	function get(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$table = trim((string) $ctl->GET("table"));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$id_raw = trim((string) $ctl->GET("id"));
		if ($id_raw === "" || !ctype_digit($id_raw)) {
			$this->respond_error(400, "id_required", "id must be numeric");
		}

		$resolved = $this->resolve_table($ctl, $table, $ctl->GET("dbclass"));
		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$item = $ffm->get((int) $id_raw);
		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"id" => (int) $id_raw,
			"item" => $item,
		]);
	}

	function getall(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$table = trim((string) $ctl->GET("table"));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $ctl->GET("dbclass"));
		$sortitem = $this->normalize_optional_string($ctl->GET("sort"));
		$sort_order = $this->normalize_sort_order($ctl->GET("order"), $ctl->GET("sort_order"));
		$limit = $this->normalize_optional_int($ctl->GET("limit"), "limit");

		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$items = $ffm->getall($sortitem, $sort_order);
		if ($limit !== null) {
			$items = array_slice($items, 0, $limit);
		}

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"sort" => $sortitem,
			"sort_order" => $sort_order,
			"limit" => $limit,
			"count" => count($items),
			"items" => $items,
		]);
	}

	function select(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$table = trim((string) ($payload["table"] ?? $ctl->GET("table")));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $payload["dbclass"] ?? $ctl->GET("dbclass"));
		$itemname = $payload["itemname"] ?? null;
		$value = $payload["value"] ?? null;
		if ($this->is_empty_itemname($itemname)) {
			$this->respond_error(400, "invalid_arguments", "itemname is required");
		}

		$match_patterns = $payload["match_patterns"] ?? true;
		$and_or = $this->normalize_and_or($payload["and_or"] ?? "AND");
		$sortitem = $this->normalize_optional_string($payload["sortitem"] ?? null);
		$sort_order = $this->normalize_sort_order(null, $payload["sort_order"] ?? null);
		$max = $this->normalize_optional_int($payload["max"] ?? null, "max");
		$is_last = null;

		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$items = $ffm->select($itemname, $value, $match_patterns, $and_or, $sortitem, $sort_order, $max, $is_last);

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"count" => count($items),
			"is_last" => $is_last,
			"items" => $items,
		]);
	}

	function filter(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$table = trim((string) ($payload["table"] ?? $ctl->GET("table")));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $payload["dbclass"] ?? $ctl->GET("dbclass"));
		$itemname = $payload["itemname"] ?? null;
		$value = $payload["value"] ?? null;
		if ($this->is_empty_itemname($itemname)) {
			$this->respond_error(400, "invalid_arguments", "itemname is required");
		}

		$exact_match = $this->normalize_bool($payload["exact_match"] ?? false);
		$and_or = $this->normalize_and_or($payload["and_or"] ?? "AND");
		$sortitem = $this->normalize_optional_string($payload["sortitem"] ?? null);
		$sort_order = $this->normalize_sort_order(null, $payload["sort_order"] ?? null);
		$max = $this->normalize_optional_int($payload["max"] ?? null, "max");
		$is_last = null;

		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$items = $ffm->filter($itemname, $value, $exact_match, $and_or, $sortitem, $sort_order, $max, $is_last);

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"count" => count($items),
			"is_last" => $is_last,
			"items" => $items,
		]);
	}

	function add(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$table = trim((string) ($payload["table"] ?? $ctl->GET("table")));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $payload["dbclass"] ?? $ctl->GET("dbclass"));
		$data = $payload["data"] ?? null;
		if (!is_array($data)) {
			$this->respond_error(400, "invalid_arguments", "data must be an object");
		}
		unset($data["id"]);

		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$id = $ffm->insert($data);
		$item = $ffm->get((int) $id);

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"id" => (int) $id,
			"item" => $item,
		]);
	}

	function edit(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$table = trim((string) ($payload["table"] ?? $ctl->GET("table")));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $payload["dbclass"] ?? $ctl->GET("dbclass"));
		$data = $payload["data"] ?? null;
		if (!is_array($data)) {
			$this->respond_error(400, "invalid_arguments", "data must be an object");
		}
		if (!isset($data["id"]) || !$this->is_numeric_id($data["id"])) {
			$this->respond_error(400, "id_required", "data.id must be numeric");
		}

		$data["id"] = (int) $data["id"];
		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$before = $ffm->get($data["id"]);
		if ($before === null) {
			$this->respond_error(404, "item_not_found", "item was not found", [
				"table" => $table,
				"id" => $data["id"],
			]);
		}
		$ffm->update($data);
		$item = $ffm->get($data["id"]);

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"id" => $data["id"],
			"item" => $item,
		]);
	}

	function delete(Controller $ctl) {
		if ($ctl->verify_api_request() !== true) {
			exit;
		}

		$payload = $this->read_json_request();
		$table = trim((string) ($payload["table"] ?? $ctl->GET("table")));
		if ($table === "") {
			$this->respond_error(400, "table_required", "table is required");
		}

		$resolved = $this->resolve_table($ctl, $table, $payload["dbclass"] ?? $ctl->GET("dbclass"));
		$id = $payload["id"] ?? ($payload["data"]["id"] ?? null);
		if (!$this->is_numeric_id($id)) {
			$this->respond_error(400, "id_required", "id must be numeric");
		}

		$id = (int) $id;
		$ffm = $ctl->db($table, $resolved["resolved_class"]);
		$item = $ffm->get($id);
		if ($item === null) {
			$this->respond_error(404, "item_not_found", "item was not found", [
				"table" => $table,
				"id" => $id,
			]);
		}
		$ffm->delete($id);

		$this->respond_json([
			"ok" => true,
			"table" => $table,
			"dbclass" => $resolved["dbclass"],
			"resolved_class" => $resolved["resolved_class"],
			"id" => $id,
			"deleted_item" => $item,
		]);
	}

	private function resolve_table(Controller $ctl, string $table, $dbclass): array {
		if (!preg_match('/^[a-z0-9_]+$/', $table)) {
			$this->respond_error(400, "invalid_table", "table must match ^[a-z0-9_]+$");
		}

		$dbclass = $this->normalize_optional_string($dbclass);
		$resolved_class = $dbclass;
		if ($resolved_class === null) {
			$resolved_class = "common";
		} elseif (!preg_match('/^[a-z0-9_]+$/', $resolved_class)) {
			$this->respond_error(400, "invalid_dbclass", "dbclass must match ^[a-z0-9_]+$");
		}

		try {
			$fmt_dir = $ctl->dirs->get_class_dir($resolved_class) . "/fmt";
		} catch (Throwable $e) {
			$this->respond_error(404, "dbclass_not_found", "dbclass was not found", [
				"table" => $table,
				"dbclass" => $dbclass,
				"resolved_class" => $resolved_class,
			]);
		}
		$dat_dir = $ctl->dirs->datadir . "/" . $resolved_class;
		$fmt_path = $fmt_dir . "/" . $table . ".fmt";
		$dat_path = $dat_dir . "/" . $table . ".dat";

		if (!is_file($fmt_path)) {
			$this->respond_error(404, "fmt_not_found", "fmt file not found", [
				"table" => $table,
				"dbclass" => $dbclass,
				"resolved_class" => $resolved_class,
				"fmt_path" => $fmt_path,
			]);
		}
		if (!is_file($dat_path)) {
			$this->respond_error(404, "dat_not_found", "dat file not found", [
				"table" => $table,
				"dbclass" => $dbclass,
				"resolved_class" => $resolved_class,
				"dat_path" => $dat_path,
			]);
		}

		return [
			"dbclass" => $dbclass,
			"resolved_class" => $resolved_class,
			"fmt_path" => $fmt_path,
			"dat_path" => $dat_path,
		];
	}

	private function collect_fmt_tables(string $app_root, string $source): array {
		if (!is_dir($app_root)) {
			return [];
		}

		$items = [];
		$class_dirs = glob($app_root . "/*", GLOB_ONLYDIR);
		if ($class_dirs === false) {
			return [];
		}

		foreach ($class_dirs as $class_dir) {
			$dbclass = basename($class_dir);
			$fmt_files = glob($class_dir . "/fmt/*.fmt");
			if ($fmt_files === false) {
				continue;
			}
			sort($fmt_files, SORT_STRING);
			foreach ($fmt_files as $fmt_path) {
				$table = pathinfo($fmt_path, PATHINFO_FILENAME);
				$items[] = [
					"table" => $table,
					"dbclass" => $dbclass,
					"source" => $source,
					"fmt_path" => $fmt_path,
				];
			}
		}

		return $items;
	}

	private function collect_db_tables(Controller $ctl): array {
		$items = [];
		$ffm = $ctl->db("db", "db");
		$rows = $ffm->getall("sort", SORT_ASC);
		foreach ($rows as $row) {
			$table = trim((string) ($row["tb_name"] ?? ""));
			if ($table === "") {
				continue;
			}
			$items[] = [
				"table" => $table,
				"dbclass" => "common",
				"source" => "db",
				"db_id" => (int) ($row["id"] ?? 0),
			];
		}
		return $items;
	}

	private function parse_fmt_fields(string $fmt_path): array {
		$txt = file_get_contents($fmt_path);
		if ($txt === false) {
			$this->respond_error(500, "fmt_read_failed", "failed to read fmt file", [
				"fmt_path" => $fmt_path,
			]);
		}

		$fields = [];
		foreach (explode("\n", $txt) as $line) {
			$line = trim($line);
			if ($line === "") {
				continue;
			}
			$parts = explode(",", $line);
			if (count($parts) !== 3) {
				$this->respond_error(500, "invalid_fmt", "fmt file is invalid", [
					"fmt_path" => $fmt_path,
					"line" => $line,
				]);
			}
			$fields[] = [
				"name" => (string) $parts[0],
				"size" => (int) $parts[1],
				"type" => (string) $parts[2],
			];
		}
		return $fields;
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

	private function normalize_optional_string($value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim((string) $value);
		return $value === "" ? null : $value;
	}

	private function normalize_optional_int($value, string $name): ?int {
		if ($value === null || $value === "") {
			return null;
		}
		if (is_int($value)) {
			return $value;
		}
		$value = trim((string) $value);
		if (!ctype_digit($value)) {
			$this->respond_error(400, "invalid_arguments", $name . " must be numeric");
		}
		return (int) $value;
	}

	private function normalize_sort_order($order, $sort_order): int {
		$order = strtolower(trim((string) $order));
		if ($order !== "") {
			if ($order === "asc") {
				return SORT_ASC;
			}
			if ($order === "desc") {
				return SORT_DESC;
			}
			$this->respond_error(400, "invalid_sort_order", "order must be asc or desc");
		}

		if ($sort_order === null || $sort_order === "") {
			return SORT_DESC;
		}
		if (is_int($sort_order)) {
			if ($sort_order === SORT_ASC || $sort_order === SORT_DESC) {
				return $sort_order;
			}
		}

		$sort_order = trim((string) $sort_order);
		if ($sort_order === (string) SORT_ASC) {
			return SORT_ASC;
		}
		if ($sort_order === (string) SORT_DESC) {
			return SORT_DESC;
		}
		$this->respond_error(400, "invalid_sort_order", "sort_order must be 4 or 3");
	}

	private function normalize_and_or($value): string {
		$value = strtoupper(trim((string) $value));
		if ($value === "") {
			return "AND";
		}
		if (!in_array($value, ["AND", "OR"], true)) {
			$this->respond_error(400, "invalid_arguments", "and_or must be AND or OR");
		}
		return $value;
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

	private function is_numeric_id($value): bool {
		if (is_int($value)) {
			return $value > 0;
		}
		if (!is_string($value) && !is_float($value)) {
			return false;
		}
		$value = trim((string) $value);
		return ctype_digit($value) && (int) $value > 0;
	}

	private function is_empty_itemname($itemname): bool {
		if (is_array($itemname)) {
			foreach ($itemname as $name) {
				if (!$this->is_empty_itemname($name)) {
					return false;
				}
			}
			return true;
		}
		return trim((string) $itemname) === "";
	}

	private function respond_json(array $payload): void {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
