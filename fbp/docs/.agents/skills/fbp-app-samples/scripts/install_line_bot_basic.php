<?php

declare(strict_types=1);

$root = getcwd();
$copyCode = true;

foreach (array_slice($argv, 1) as $arg) {
	if (strpos($arg, "--root=") === 0) {
		$root = substr($arg, 7);
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
$assetDir = $skillDir . "/assets/line-bot-basic";
$manifestFile = $assetDir . "/line-bot-basic.json";
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
		$tmp = tempnam(sys_get_temp_dir(), "fbp_line_bot_basic_");
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

function find_webhook_rule(array $items, string $channel, string $keyword): ?array {
	foreach ($items as $item) {
		if ((string) ($item["channel"] ?? "") === $channel && (string) ($item["keyword"] ?? "") === $keyword) {
			return $item;
		}
	}
	return null;
}

if ($copyCode) {
	foreach (glob($assetDir . "/classes/app/*") ?: [] as $src) {
		$dst = "classes/app/" . basename($src);
		remove_tree($dst);
		copy_tree($src, $dst);
	}
}

$constantArray = $manifest["constant_array"];
$arrayName = (string) $constantArray["array_name"];
$existingArray = find_by(run_cli("constant_array_list")["items"] ?? [], "array_name", $arrayName);
if ($existingArray) {
	$constantArray["id"] = (int) $existingArray["id"];
	run_cli("constant_array_edit", $constantArray);
	$constantArrayId = (int) $existingArray["id"];
} else {
	$constantArrayId = (int) (run_cli("constant_array_add", $constantArray)["id"] ?? 0);
}

$existingValues = run_cli("constant_values_list", ["constant_array_id" => $constantArrayId])["items"] ?? [];
foreach ($manifest["constant_values"] as $value) {
	$value["constant_array_id"] = $constantArrayId;
	$existingValue = find_by($existingValues, "key", $value["key"]);
	if ($existingValue) {
		$value["id"] = (int) $existingValue["id"];
		run_cli("constant_values_edit", $value);
	} else {
		run_cli("constant_values_add", $value);
	}
}

$table = $manifest["db_table"];
$existingTable = find_by(run_cli("db_tables_list")["items"] ?? [], "tb_name", $table["tb_name"]);
if ($existingTable) {
	$table["id"] = (int) $existingTable["id"];
	run_cli("db_tables_edit", $table);
	$dbId = (int) $existingTable["id"];
} else {
	$dbId = (int) (run_cli("db_tables_add", $table)["id"] ?? 0);
}

foreach ($manifest["db_fields"] as $field) {
	$field["db_id"] = $dbId;
	$field["upsert"] = 1;
	run_cli("db_fields_add", $field);
}

$existingRules = run_cli("webhook_rule_list")["items"] ?? [];
foreach ($manifest["webhook_rules"] as $rule) {
	$channel = (string) $rule["channel"];
	$keyword = (string) $rule["keyword"];
	$existingRule = find_webhook_rule($existingRules, $channel, $keyword);
	if ($existingRule) {
		$rule["id"] = (int) $existingRule["id"];
		run_cli("webhook_rule_edit", $rule);
	} else {
		run_cli("webhook_rule_add", $rule);
	}
}

echo json_encode([
	"ok" => true,
	"copied_code" => $copyCode,
	"db_id" => $dbId,
	"constant_array_id" => $constantArrayId,
	"webhook_rules" => count($manifest["webhook_rules"]),
	"line_webhook_url_path" => "/line_webhook*receive",
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
