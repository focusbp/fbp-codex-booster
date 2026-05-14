<?php

declare(strict_types=1);

$root = getcwd();
$resetData = true;
$copyCode = true;

foreach (array_slice($argv, 1) as $arg) {
	if (strpos($arg, "--root=") === 0) {
		$root = substr($arg, 7);
	} else if ($arg === "--keep-data") {
		$resetData = false;
	} else if ($arg === "--no-copy") {
		$copyCode = false;
	}
}

$root = rtrim($root, "/");
if ($root === "" || !is_file($root . "/fbp/cli.php")) {
	fwrite(STDERR, "Run from the FBP project root, or pass --root=/path/to/project.\n");
	exit(1);
}
chdir($root);

$skillDir = dirname(__DIR__);
$assetDir = $skillDir . "/assets/event-registration";
$manifestFile = $assetDir . "/event-registration.json";
$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (!is_array($manifest)) {
	fwrite(STDERR, "Invalid manifest: {$manifestFile}\n");
	exit(1);
}

function remove_tree(string $path): void {
	if (!file_exists($path)) {
		return;
	}
	if (is_file($path) || is_link($path)) {
		unlink($path);
		return;
	}
	foreach (scandir($path) ?: [] as $name) {
		if ($name === "." || $name === "..") {
			continue;
		}
		remove_tree($path . "/" . $name);
	}
	rmdir($path);
}

function copy_tree(string $src, string $dst): void {
	if (is_dir($src)) {
		if (!is_dir($dst)) {
			mkdir($dst, 0777, true);
		}
		foreach (scandir($src) ?: [] as $name) {
			if ($name === "." || $name === "..") {
				continue;
			}
			copy_tree($src . "/" . $name, $dst . "/" . $name);
		}
		return;
	}
	if (!is_dir(dirname($dst))) {
		mkdir(dirname($dst), 0777, true);
	}
	copy($src, $dst);
}

function run_cli(string $command, ?array $payload = null): array {
	$args = ["php", "fbp/cli.php", $command];
	$tmp = null;
	if ($payload !== null) {
		$tmp = tempnam(sys_get_temp_dir(), "fbp_event_registration_");
		if ($tmp === false) {
			throw new RuntimeException("Could not create temporary JSON file.");
		}
		file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$args[] = "--json-file";
		$args[] = $tmp;
	}

	$descriptors = [
		1 => ["pipe", "w"],
		2 => ["pipe", "w"],
	];
	$process = proc_open($args, $descriptors, $pipes);
	if (!is_resource($process)) {
		throw new RuntimeException("Could not run cli.php {$command}.");
	}
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$code = proc_close($process);
	if ($tmp !== null) {
		unlink($tmp);
	}
	if ($code !== 0) {
		throw new RuntimeException(trim($err) !== "" ? trim($err) : "cli.php {$command} failed.");
	}
	$data = json_decode((string) $out, true);
	return is_array($data) ? $data : ["raw" => $out];
}

function find_by(array $items, string $key, $value): ?array {
	foreach ($items as $item) {
		if ((string) ($item[$key] ?? "") === (string) $value) {
			return $item;
		}
	}
	return null;
}

function reset_table_data(string $table): void {
	foreach (glob("classes/data/common/{$table}*") ?: [] as $file) {
		if (is_file($file)) {
			unlink($file);
		}
	}
}

function delete_constant_array_if_exists(string $arrayName): bool {
	$existingArray = find_by(run_cli("constant_array_list")["items"] ?? [], "array_name", $arrayName);
	if (!$existingArray) {
		return false;
	}
	run_cli("constant_array_delete", ["id" => (int) $existingArray["id"]]);
	return true;
}

function delete_table_if_sample(string $tbName, array $allowedDescriptions): bool {
	$existingTable = find_by(run_cli("db_tables_list")["items"] ?? [], "tb_name", $tbName);
	if (!$existingTable) {
		return false;
	}
	$description = (string) ($existingTable["description"] ?? "");
	if (!in_array($description, $allowedDescriptions, true)) {
		return false;
	}
	$dbId = (int) $existingTable["id"];
	foreach (run_cli("db_fields_list", ["db_id" => $dbId])["items"] ?? [] as $field) {
		run_cli("db_fields_delete", ["id" => (int) $field["id"]]);
	}
	run_cli("db_tables_delete", ["id" => $dbId]);
	reset_table_data($tbName);
	return true;
}

function cleanup_legacy_appointment_sample(bool $removeCode): array {
	$removed = [
		"code" => [],
		"tables" => [],
		"constant_arrays" => [],
	];

	if ($removeCode) {
		foreach ([
			"classes/app/appointment_booking_public",
			"classes/app/appointment_slot_original_management",
			"classes/app/appointment_slots_original_management",
			"classes/app/registration_slot_original_management",
		] as $path) {
			if (file_exists($path)) {
				remove_tree($path);
				$removed["code"][] = $path;
			}
		}
	}

	if (delete_table_if_sample("appointments", [
		"Appointment requests submitted from the public booking page",
	])) {
		$removed["tables"][] = "appointments";
	}
	if (delete_table_if_sample("appointment_slots", [
		"Bookable appointment slots for the Appointment Booking demo",
	])) {
		$removed["tables"][] = "appointment_slots";
	}

	if ($removed["tables"] !== []) {
		foreach (["appointment_slot_status", "appointment_status"] as $arrayName) {
			if (delete_constant_array_if_exists($arrayName)) {
				$removed["constant_arrays"][] = $arrayName;
			}
		}
	}

	return $removed;
}

$legacyCleanup = cleanup_legacy_appointment_sample($copyCode);

if ($copyCode) {
	foreach (glob($assetDir . "/classes/app/*") ?: [] as $src) {
		$dst = "classes/app/" . basename($src);
		remove_tree($dst);
		copy_tree($src, $dst);
	}
}

$constantArrayIds = [];
foreach ($manifest["constant_arrays"] as $constantArray) {
	$arrayName = (string) $constantArray["array_name"];
	$existingArray = find_by(run_cli("constant_array_list")["items"] ?? [], "array_name", $arrayName);
	if ($existingArray) {
		$constantArray["id"] = (int) $existingArray["id"];
		run_cli("constant_array_edit", $constantArray);
		$constantArrayId = (int) $existingArray["id"];
	} else {
		$constantArrayId = (int) (run_cli("constant_array_add", $constantArray)["id"] ?? 0);
	}
	$constantArrayIds[$arrayName] = $constantArrayId;

	$desiredKeys = [];
	$existingValues = run_cli("constant_values_list", ["constant_array_id" => $constantArrayId])["items"] ?? [];
	foreach ($manifest["constant_values"][$arrayName] ?? [] as $value) {
		$value["constant_array_id"] = $constantArrayId;
		$desiredKeys[(string) $value["key"]] = true;
		$existingValue = find_by($existingValues, "key", $value["key"]);
		if ($existingValue) {
			$value["id"] = (int) $existingValue["id"];
			run_cli("constant_values_edit", $value);
		} else {
			run_cli("constant_values_add", $value);
		}
	}
	foreach ($existingValues as $value) {
		if (!isset($desiredKeys[(string) ($value["key"] ?? "")])) {
			run_cli("constant_values_delete", ["id" => (int) $value["id"]]);
		}
	}
}

$dbIds = [];
foreach ($manifest["db_tables"] as $table) {
	$tbName = (string) $table["tb_name"];
	$existingTable = find_by(run_cli("db_tables_list")["items"] ?? [], "tb_name", $tbName);
	if ($existingTable) {
		$table["id"] = (int) $existingTable["id"];
		run_cli("db_tables_edit", $table);
		$dbId = (int) $existingTable["id"];
	} else {
		$dbId = (int) (run_cli("db_tables_add", $table)["id"] ?? 0);
	}
	$dbIds[$tbName] = $dbId;

	$desiredFields = [];
	foreach ($manifest["db_fields"][$tbName] ?? [] as $field) {
		$field["db_id"] = $dbId;
		$field["upsert"] = 1;
		$desiredFields[(string) $field["parameter_name"]] = true;
		run_cli("db_fields_add", $field);
	}
	$existingFields = run_cli("db_fields_list", ["db_id" => $dbId])["items"] ?? [];
	foreach ($existingFields as $field) {
		if (!isset($desiredFields[(string) ($field["parameter_name"] ?? "")])) {
			run_cli("db_fields_delete", ["id" => (int) $field["id"]]);
		}
	}
}

if ($resetData) {
	reset_table_data("event_registrations");
	reset_table_data("event_sessions");
}

$sessionRows = run_cli("data_list", ["table" => "event_sessions", "max" => 1])["items"] ?? [];
$seedSessionIds = [];
if ($resetData || count($sessionRows) === 0) {
	foreach ($manifest["seed_sessions"] as $seed) {
		$key = (string) ($seed["_key"] ?? "");
		$row = $seed;
		unset($row["_key"]);
		$offsetDays = (int) ($row["date_offset_days"] ?? 2);
		$startTime = (string) ($row["start_time"] ?? "10:00");
		unset($row["date_offset_days"]);
		unset($row["start_time"]);
		$row["starts_at"] = strtotime(date("Y-m-d", strtotime("+{$offsetDays} days")) . " " . $startTime);
		$id = (int) (run_cli("data_add", ["table" => "event_sessions", "data" => $row])["id"] ?? 0);
		if ($key !== "" && $id > 0) {
			$seedSessionIds[$key] = $id;
		}
	}

	foreach ($manifest["seed_event_registrations"] as $seed) {
		$sessionKey = (string) ($seed["_session_key"] ?? "");
		if (!isset($seedSessionIds[$sessionKey])) {
			continue;
		}
		$row = $seed;
		unset($row["_session_key"]);
		$row["session_id"] = $seedSessionIds[$sessionKey];
		$row["created_at"] = time();
		$row["updated_at"] = time();
		run_cli("data_add", ["table" => "event_registrations", "data" => $row]);
	}
}

echo json_encode([
	"ok" => true,
	"copied_code" => $copyCode,
	"reset_data" => $resetData,
	"db_ids" => $dbIds,
	"constant_array_ids" => $constantArrayIds,
	"legacy_cleanup" => $legacyCleanup,
	"public_url_path" => "/event_registration_public*page",
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
