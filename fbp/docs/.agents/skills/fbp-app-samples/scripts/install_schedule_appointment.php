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
$assetDir = $skillDir . "/assets/schedule-appointment";
$manifestFile = $assetDir . "/schedule-appointment.json";
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
		$tmp = tempnam(sys_get_temp_dir(), "fbp_schedule_appointment_");
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
	reset_table_data("schedule_appointment_slots");
}

$existingSlots = run_cli("data_list", ["table" => "schedule_appointment_slots", "max" => 1])["items"] ?? [];
if ($resetData || count($existingSlots) === 0) {
	$users = run_cli("data_list", ["table" => "user", "max" => 20])["items"] ?? [];
	$userIds = [];
	foreach ($users as $user) {
		if ((int) ($user["id"] ?? 0) > 0 && (string) ($user["status"] ?? "0") === "0") {
			$userIds[] = (int) $user["id"];
		}
	}
	if ($userIds === []) {
		$userIds[] = 1;
	}

	foreach ($manifest["seed_slots"] as $seed) {
		$targetUserIds = $userIds;
		if (isset($seed["_user_index"])) {
			$userIndex = (int) $seed["_user_index"];
			$targetUserIds = [$userIds[$userIndex] ?? $userIds[0]];
		}
		$row = $seed;
		unset($row["_user_index"]);
		$offsetDays = (int) ($row["date_offset_days"] ?? 2);
		$startTime = (string) ($row["start_time"] ?? "10:00");
		unset($row["date_offset_days"]);
		unset($row["start_time"]);
		$row["starts_at"] = strtotime(date("Y-m-d", strtotime("+{$offsetDays} days")) . " " . $startTime);
		if (($row["booked_at"] ?? "") === "now") {
			$row["booked_at"] = time();
		}
		foreach ($targetUserIds as $userId) {
			$row["user_id"] = $userId;
			run_cli("data_add", ["table" => "schedule_appointment_slots", "data" => $row]);
		}
	}
}

echo json_encode([
	"ok" => true,
	"copied_code" => $copyCode,
	"reset_data" => $resetData,
	"db_ids" => $dbIds,
	"constant_array_ids" => $constantArrayIds,
	"public_url_path" => "/schedule_appointment_public*calendar?user=<encrypted-user-id>",
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
