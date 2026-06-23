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
$assetDir = $skillDir . "/assets/service-user-management";
$manifestFile = $assetDir . "/service-user-management.json";
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
		$tmp = tempnam(sys_get_temp_dir(), "fbp_service_user_management_");
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
}

foreach ($manifest["db_fields"] as $tbName => $fields) {
	$dbId = $dbIds[$tbName] ?? 0;
	if ($dbId <= 0) {
		continue;
	}
	$desiredFields = [];
	foreach ($fields as $field) {
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

foreach ($manifest["screen_fields"] ?? [] as $tbName => $screens) {
	foreach ($screens as $screenName => $parameterNames) {
		$desiredFields = [];
		$sort = 1;
		foreach ($parameterNames as $parameterName) {
			$parameterName = (string) $parameterName;
			$desiredFields[$parameterName] = true;
			run_cli("screen_fields_add", [
				"tb_name" => $tbName,
				"screen_name" => $screenName,
				"parameter_name" => $parameterName,
				"sort" => $sort++,
				"upsert" => 1,
			]);
		}
		$existingFields = run_cli("screen_fields_list", [
			"tb_name" => $tbName,
			"screen_name" => $screenName,
		])["items"] ?? [];
		foreach ($existingFields as $field) {
			if (!isset($desiredFields[(string) ($field["parameter_name"] ?? "")])) {
				run_cli("screen_fields_delete", [
					"tb_name" => $tbName,
					"screen_name" => $screenName,
					"id" => (int) $field["id"],
				]);
			}
		}
	}
}

foreach ($manifest["db_additionals"] ?? [] as $additional) {
	$existingItems = run_cli("db_additionals_list")["items"] ?? [];
	$existing = null;
	foreach ($existingItems as $item) {
		if ((string) ($item["tb_name"] ?? "") === (string) ($additional["tb_name"] ?? "")
				&& (string) ($item["class_name"] ?? "") === (string) ($additional["class_name"] ?? "")
				&& (string) ($item["function_name"] ?? "") === (string) ($additional["function_name"] ?? "")) {
			$existing = $item;
			break;
		}
	}
	if ($existing) {
		$additional["id"] = (int) $existing["id"];
		run_cli("db_additionals_edit", $additional);
	} else {
		run_cli("db_additionals_add", $additional);
	}
}

$sampleTables = [
	"service_password_reset",
	"service_payment",
	"service_subscription",
	"service_plan",
	"service_member",
];
if ($resetData) {
	foreach ($sampleTables as $table) {
		reset_table_data($table);
	}
}

$memberRows = run_cli("data_list", ["table" => "service_member", "max" => 1])["items"] ?? [];
if ($resetData || count($memberRows) === 0) {
	foreach ($manifest["seed_members"] as $seed) {
		$row = $seed;
		$password = (string) ($row["password"] ?? "password123");
		unset($row["password"]);
		$row["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
		$row["square_customer_id"] = "";
		$row["square_card_id"] = "";
		$row["created_at"] = time();
		$row["updated_at"] = time();
		run_cli("data_add", ["table" => "service_member", "data" => $row]);
	}
}

$planRows = run_cli("data_list", ["table" => "service_plan", "max" => 1])["items"] ?? [];
if ($resetData || count($planRows) === 0) {
	foreach ($manifest["seed_plans"] as $seed) {
		$seed["created_at"] = time();
		$seed["updated_at"] = time();
		run_cli("data_add", ["table" => "service_plan", "data" => $seed]);
	}
}

echo json_encode([
	"ok" => true,
	"copied_code" => $copyCode,
	"reset_data" => $resetData,
	"db_ids" => $dbIds,
	"constant_array_ids" => $constantArrayIds,
	"public_url_path" => "/public_service*plans",
	"sample_login" => [
		"email" => "member@example.com",
		"password" => "password123"
	]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
