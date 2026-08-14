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

$counts = [1000, 10000, 100000];
$results = [];
foreach ($counts as $count) {
	$root = sys_get_temp_dir() . "/ffm-index-performance-" . $count . "-" . bin2hex(random_bytes(4));
	try {
		mkdir($root . "/data", 0777, true);
		mkdir($root . "/fmt", 0777, true);
		file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N\nvalue,40,T\n");
		$GLOBALS["lock_class_arr"] = [];
		$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt", ["index_disabled" => true]);
		$started = microtime(true);
		for ($i = 1; $i <= $count; $i++) {
			$row = ["parent_id" => $i % 1000, "value" => "row-" . $i];
			$ffm->insert($row);
		}
		$insert_seconds = microtime(true) - $started;
		$started = microtime(true);
		$legacy = $ffm->select("parent_id", 777);
		$legacy_seconds = microtime(true) - $started;
		$ffm->close();

		file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N,IDX\nvalue,40,T\n");
		$started = microtime(true);
		$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");
		$build_seconds = microtime(true) - $started;
		$started = microtime(true);
		$indexed = $ffm->select("parent_id", 777);
		$indexed_seconds = microtime(true) - $started;
		$index_size = filesize($root . "/data/sample.dat.parent_id.idx");
		$ffm->close();
		if ($legacy !== $indexed) throw new RuntimeException("performance result mismatch at " . $count);
		$results[] = [
			"rows" => $count,
			"matches" => count($indexed),
			"insert_seconds" => round($insert_seconds, 6),
			"legacy_search_seconds" => round($legacy_seconds, 6),
			"index_build_seconds" => round($build_seconds, 6),
			"indexed_search_seconds" => round($indexed_seconds, 6),
			"index_bytes" => $index_size,
			"peak_memory_bytes" => memory_get_peak_usage(true),
		];
	} finally {
		if (isset($ffm) && $ffm instanceof fixed_file_manager) $ffm->close();
		perf_remove($root);
	}
}

echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
