<?php

if (PHP_SAPI !== "cli") {
	http_response_code(404);
	exit;
}

class Controller_class {
	public static function getInstance() { return null; }
}

putenv("FBP_FFM_LOG_DISABLE=1");
require_once dirname(__DIR__) . "/fixed_file_manager.php";

function text_perf_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? text_perf_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function text_perf_open(string $root, array $options = []): fixed_file_manager {
	$GLOBALS["lock_class_arr"] = [];
	return new fixed_file_manager("customer", $root . "/data", $root . "/fmt", $options);
}

function text_perf_timed(callable $callback): array {
	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	$value = $callback();
	return [
		"value" => $value,
		"seconds" => microtime(true) - $started,
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
}

function text_perf_query(string $root, bool $legacy, string $needle, $sortitem, $max): array {
	$options = ["read_only" => true];
	if ($legacy) $options["text_search_disabled"] = true;
	$ffm = text_perf_open($root, $options);
	try {
		$is_last = null;
		$rows = $ffm->filter("memo", $needle, false, "AND", $sortitem, SORT_ASC, $max, $is_last);
		return [
			"count" => count($rows),
			"ids" => array_column($rows, "id"),
			"hash" => hash("sha256", serialize($rows)),
			"is_last" => $is_last,
		];
	} finally {
		$ffm->close();
	}
}

$counts = [100000];
if ($argc > 1) {
	$counts = [];
	foreach (array_slice($argv, 1) as $arg) {
		if (preg_match('/^[1-9][0-9]*$/', $arg) !== 1) {
			fwrite(STDERR, "Row counts must be positive integers.\n");
			exit(2);
		}
		$counts[] = (int) $arg;
	}
}

$results = [];
foreach ($counts as $count) {
	$root = sys_get_temp_dir() . "/ffm-text-performance-" . $count . "-" . bin2hex(random_bytes(4));
	$ffm = null;
	try {
		mkdir($root . "/data", 0777, true);
		mkdir($root . "/fmt", 0777, true);
		file_put_contents($root . "/fmt/customer.fmt", implode("\n", [
			"id,24,N",
			"name,90,T",
			"address,150,T",
			"memo,240,T",
			"status,24,N",
		]) . "\n");

		$ffm = text_perf_open($root, ["text_search_disabled" => true]);
		$insert = text_perf_timed(static function () use ($ffm, $count): void {
			for ($i = 1; $i <= $count; $i++) {
				$row = [
					"name" => "顧客" . sprintf("%08d", $i),
					"address" => "東京都中央区サンプル町" . ($i % 10000) . "番地",
					"memo" => ($i % 1000 === 777 ? "Needle-Token " : "通常メモ ") . "管理番号" . $i,
					"status" => $i % 5,
				];
				$ffm->insert($row);
			}
		});
		$ffm->close();
		$ffm = null;

		$cases = [
			"full_sorted" => ["needle" => "NEEDLE-TOKEN", "sortitem" => "id", "max" => null],
			"limited_desc" => ["needle" => "NEEDLE-TOKEN", "sortitem" => null, "max" => 10],
			"not_found" => ["needle" => "存在しない検索語", "sortitem" => null, "max" => 10],
		];
		if ($count <= 100000) {
			// More than 50,000 candidates must abandon the block result and use the legacy scan.
			$cases["candidate_limit_fallback"] = ["needle" => "管理番号", "sortitem" => "id", "max" => 10];
		}
		$case_results = [];
		foreach ($cases as $name => $case) {
			$legacy = text_perf_timed(static fn(): array => text_perf_query($root, true, $case["needle"], $case["sortitem"], $case["max"]));
			$block = text_perf_timed(static fn(): array => text_perf_query($root, false, $case["needle"], $case["sortitem"], $case["max"]));
			if ($legacy["value"] !== $block["value"]) {
				throw new RuntimeException("text search result mismatch: " . $name . " at " . $count);
			}
			$case_results[$name] = [
				"count" => $block["value"]["count"],
				"is_last" => $block["value"]["is_last"],
				"legacy_seconds" => round($legacy["seconds"], 6),
				"block_seconds" => round($block["seconds"], 6),
				"legacy_peak_memory_bytes" => $legacy["peak_memory_bytes"],
				"block_peak_memory_bytes" => $block["peak_memory_bytes"],
			];
		}

		$results[] = [
			"rows" => $count,
			"dat_bytes" => filesize($root . "/data/customer.dat"),
			"insert_seconds" => round($insert["seconds"], 6),
			"insert_peak_memory_bytes" => $insert["peak_memory_bytes"],
			"cases" => $case_results,
		];
	} finally {
		if ($ffm instanceof fixed_file_manager) $ffm->close();
		text_perf_remove($root);
	}
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
