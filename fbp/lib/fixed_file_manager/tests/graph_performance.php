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

function graph_perf_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? graph_perf_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function graph_perf_size(string $path): int {
	if (is_file($path)) return (int) filesize($path);
	$total = 0;
	foreach (array_diff(scandir($path), [".", ".."]) as $name) $total += graph_perf_size($path . "/" . $name);
	return $total;
}

function graph_perf_time(callable $callback): array {
	$started = hrtime(true);
	$result = $callback();
	return [$result, (hrtime(true) - $started) / 1000000];
}

$count = isset($argv[1]) ? (int) $argv[1] : 100000;
if ($count < 1) {
	fwrite(STDERR, "edge count must be positive\n");
	exit(2);
}

$root = sys_get_temp_dir() . "/ffm-graph-performance-" . $count . "-" . bin2hex(random_bytes(4));
$fmt_plain = "id,12,N\nfrom_node_id,12,N\nto_node_id,12,N\nrelation_type,32,T\nweight,8,F\nenabled,1,N\n";
$fmt_indexed = "id,12,N\nfrom_node_id,12,N,IDX\nto_node_id,12,N,IDX\nrelation_type,32,T,IDX\nweight,8,F\nenabled,1,N,IDX\n";
$node_space = max(1000, min(50000, intdiv($count, 10)));
$active_nodes = [];
for ($i = 0; $i < 64; $i++) $active_nodes[] = 1 + (($i * 7919) % $node_space);

try {
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	file_put_contents($root . "/fmt/edge.fmt", $fmt_plain);
	$GLOBALS["lock_class_arr"] = [];
	$ffm = new fixed_file_manager("edge", $root . "/data", $root . "/fmt", ["index_disabled" => true]);
	$relations = ["requires", "alternative", "similar_to"];
	$insert_started = hrtime(true);
	for ($i = 1; $i <= $count; $i++) {
		$row = [
			"from_node_id" => 1 + (($i * 37) % $node_space),
			"to_node_id" => 1 + (($i * 97 + 13) % $node_space),
			"relation_type" => $relations[$i % count($relations)],
			"weight" => ($i % 100) / 100,
			"enabled" => $i % 17 === 0 ? 0 : 1,
		];
		$ffm->insert($row);
	}
	$insert_ms = (hrtime(true) - $insert_started) / 1000000;
	$ffm->close();

	file_put_contents($root . "/fmt/edge.fmt", $fmt_indexed);
	$GLOBALS["lock_class_arr"] = [];
	$build_started = hrtime(true);
	$ffm = new fixed_file_manager("edge", $root . "/data", $root . "/fmt");
	$build_ms = (hrtime(true) - $build_started) / 1000000;
	$ffm->close();

	$GLOBALS["lock_class_arr"] = [];
	$legacy = new fixed_file_manager("edge", $root . "/data", $root . "/fmt", ["read_only" => true, "index_disabled" => true]);
	[$legacy_rows, $legacy_ms] = graph_perf_time(fn() => $legacy->neighbors_many($active_nodes, ["requires", "alternative"], 50, "both"));
	$legacy->close();

	$GLOBALS["lock_class_arr"] = [];
	$indexed = new fixed_file_manager("edge", $root . "/data", $root . "/fmt", ["read_only" => true]);
	[$indexed_rows, $indexed_cold_ms] = graph_perf_time(fn() => $indexed->neighbors_many($active_nodes, ["requires", "alternative"], 50, "both"));
	if ($legacy_rows !== $indexed_rows) throw new RuntimeException("indexed and legacy graph results differ");
	$warm_times = [];
	for ($i = 0; $i < 10; $i++) {
		[, $elapsed] = graph_perf_time(fn() => $indexed->neighbors_many($active_nodes, ["requires", "alternative"], 50, "both"));
		$warm_times[] = $elapsed;
	}
	sort($warm_times);
	$cache_property = new ReflectionProperty(fixed_file_manager::class, "index_cache");
	$cache = $cache_property->getValue($indexed);
	$cached_shards = [];
	foreach ($cache as $field => $shards) $cached_shards[$field] = count($shards);
	$indexed->close();

	$edge_count = array_sum(array_map("count", $indexed_rows));
	$index_bytes = 0;
	foreach (["from_node_id", "to_node_id", "relation_type", "enabled"] as $field) {
		$index_bytes += graph_perf_size($root . "/data/edge.dat." . $field . ".idx");
	}
	$out = [
		"rows" => $count,
		"node_space" => $node_space,
		"active_nodes" => count($active_nodes),
		"returned_edges_across_nodes" => $edge_count,
		"insert_ms" => round($insert_ms, 3),
		"index_build_ms" => round($build_ms, 3),
		"legacy_single_scan_ms" => round($legacy_ms, 3),
		"indexed_cold_ms" => round($indexed_cold_ms, 3),
		"indexed_warm_median_ms" => round($warm_times[5], 3),
		"cold_speedup" => $indexed_cold_ms > 0 ? round($legacy_ms / $indexed_cold_ms, 2) : null,
		"warm_speedup" => $warm_times[5] > 0 ? round($legacy_ms / $warm_times[5], 2) : null,
		"cached_index_shards" => $cached_shards,
		"index_bytes" => $index_bytes,
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
} finally {
	graph_perf_remove($root);
}
