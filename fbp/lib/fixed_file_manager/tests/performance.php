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

function perf_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? perf_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function perf_path_size(string $path): int {
	if (is_file($path)) return (int) filesize($path);
	$total = 0;
	foreach (array_diff(scandir($path), [".", ".."]) as $name) $total += perf_path_size($path . "/" . $name);
	return $total;
}

$counts = [1000, 10000, 100000];
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
	$root = sys_get_temp_dir() . "/ffm-index-performance-" . $count . "-" . bin2hex(random_bytes(4));
	try {
		mkdir($root . "/data", 0777, true);
		mkdir($root . "/fmt", 0777, true);
		file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N\nvalue,40,T\n");
		$GLOBALS["lock_class_arr"] = [];
		$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt", ["index_disabled" => true]);
		memory_reset_peak_usage();
		$started = microtime(true);
		for ($i = 1; $i <= $count; $i++) {
			$row = ["parent_id" => $i % 1000, "value" => "row-" . $i];
			$ffm->insert($row);
		}
		$insert_seconds = microtime(true) - $started;
		$insert_peak_memory = memory_get_peak_usage(true);
		memory_reset_peak_usage();
		$started = microtime(true);
		$legacy = $ffm->select("parent_id", 777);
		$legacy_seconds = microtime(true) - $started;
		$legacy_peak_memory = memory_get_peak_usage(true);
		$ffm->close();

		file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N,IDX\nvalue,40,T\n");
		memory_reset_peak_usage();
		$started = microtime(true);
		$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");
		$build_seconds = microtime(true) - $started;
		$build_peak_memory = memory_get_peak_usage(true);
		$index_loaded_memory = memory_get_usage(true);
		memory_reset_peak_usage();
		$started = microtime(true);
		$indexed = $ffm->select("parent_id", 777);
		$indexed_seconds = microtime(true) - $started;
		$indexed_peak_memory = memory_get_peak_usage(true);
		memory_reset_peak_usage();
		$started = microtime(true);
		$standard_filter = $ffm->filter("parent_id", 777, false, "AND", null, SORT_DESC, null, $is_last, ["="]);
		$standard_filter_seconds = microtime(true) - $started;
		$standard_filter_peak_memory = memory_get_peak_usage(true);
		$index_size = perf_path_size($root . "/data/sample.dat.parent_id.idx");
		$dat_size = filesize($root . "/data/sample.dat");
		$ffm->close();
		if ($legacy !== $indexed) throw new RuntimeException("performance result mismatch at " . $count);
		if ($legacy !== $standard_filter) throw new RuntimeException("Standard Screen filter result mismatch at " . $count);
		$results[] = [
			"rows" => $count,
			"matches" => count($indexed),
			"insert_seconds" => round($insert_seconds, 6),
			"legacy_search_seconds" => round($legacy_seconds, 6),
			"index_build_seconds" => round($build_seconds, 6),
			"indexed_search_seconds" => round($indexed_seconds, 6),
			"standard_filter_seconds" => round($standard_filter_seconds, 6),
			"dat_bytes" => $dat_size,
			"index_bytes" => $index_size,
			"insert_peak_memory_bytes" => $insert_peak_memory,
			"legacy_search_peak_memory_bytes" => $legacy_peak_memory,
			"index_build_peak_memory_bytes" => $build_peak_memory,
			"index_loaded_memory_bytes" => $index_loaded_memory,
			"indexed_search_peak_memory_bytes" => $indexed_peak_memory,
			"standard_filter_peak_memory_bytes" => $standard_filter_peak_memory,
		];
	} finally {
		if (isset($ffm) && $ffm instanceof fixed_file_manager) $ffm->close();
		perf_remove($root);
	}
}

echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
