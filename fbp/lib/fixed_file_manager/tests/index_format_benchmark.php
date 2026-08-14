<?php

if (PHP_SAPI !== "cli") {
	http_response_code(404);
	exit;
}

const INDEX_FORMAT_SHARDS = 128;
const INDEX_FORMAT_PARENTS = 100000;
const INDEX_FORMAT_IDS_PER_PARENT = 10;
const INDEX_FORMAT_BINARY_MAGIC = "FFMIDXB1";
const INDEX_FORMAT_BINARY_HEADER_SIZE = 16;
const INDEX_FORMAT_BINARY_ENTRY_SIZE = 40;

function format_benchmark_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? format_benchmark_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function format_benchmark_assert(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function format_benchmark_hash(int $parent_id): string {
	return hash("sha256", "N\0" . $parent_id, true);
}

function format_benchmark_shard(string $raw_hash): int {
	return ord($raw_hash[0]) >> 1;
}

function format_benchmark_path(string $root, string $format, int $shard): string {
	return $root . "/" . $format . "/" . sprintf("%03d", $shard) . ($format === "json" ? ".json" : ".bin");
}

function format_benchmark_json_lookup(string $root, int $parent_id, array &$cache): array {
	$raw_hash = format_benchmark_hash($parent_id);
	$shard = format_benchmark_shard($raw_hash);
	if (!isset($cache[$shard])) {
		$decoded = json_decode((string) file_get_contents(format_benchmark_path($root, "json", $shard)), true, 512, JSON_THROW_ON_ERROR);
		format_benchmark_assert(is_array($decoded), "invalid JSON shard");
		$cache[$shard] = $decoded;
	}
	return array_map("intval", $cache[$shard][bin2hex($raw_hash)] ?? []);
}

function format_benchmark_binary_open(string $root, int $shard): array {
	$handle = fopen(format_benchmark_path($root, "binary", $shard), "rb");
	format_benchmark_assert(is_resource($handle), "binary shard open failed");
	$header = fread($handle, INDEX_FORMAT_BINARY_HEADER_SIZE);
	format_benchmark_assert(strlen($header) === INDEX_FORMAT_BINARY_HEADER_SIZE, "binary header is short");
	format_benchmark_assert(substr($header, 0, 8) === INDEX_FORMAT_BINARY_MAGIC, "binary magic mismatch");
	$counts = unpack("Nkey_count/Nid_count", substr($header, 8));
	return [
		"handle" => $handle,
		"key_count" => (int) $counts["key_count"],
		"id_count" => (int) $counts["id_count"],
		"ids_offset" => INDEX_FORMAT_BINARY_HEADER_SIZE + ((int) $counts["key_count"] * INDEX_FORMAT_BINARY_ENTRY_SIZE),
	];
}

function format_benchmark_binary_lookup(string $root, int $parent_id, array &$cache): array {
	$target = format_benchmark_hash($parent_id);
	$shard = format_benchmark_shard($target);
	if (!isset($cache[$shard])) $cache[$shard] = format_benchmark_binary_open($root, $shard);
	$state = $cache[$shard];
	$handle = $state["handle"];
	$low = 0;
	$high = $state["key_count"] - 1;
	while ($low <= $high) {
		$middle = $low + intdiv($high - $low, 2);
		fseek($handle, INDEX_FORMAT_BINARY_HEADER_SIZE + ($middle * INDEX_FORMAT_BINARY_ENTRY_SIZE));
		$entry = fread($handle, INDEX_FORMAT_BINARY_ENTRY_SIZE);
		format_benchmark_assert(strlen($entry) === INDEX_FORMAT_BINARY_ENTRY_SIZE, "binary directory entry is short");
		$comparison = strcmp(substr($entry, 0, 32), $target);
		if ($comparison < 0) {
			$low = $middle + 1;
			continue;
		}
		if ($comparison > 0) {
			$high = $middle - 1;
			continue;
		}
		$location = unpack("Noffset/Ncount", substr($entry, 32));
		$count = (int) $location["count"];
		fseek($handle, $state["ids_offset"] + ((int) $location["offset"] * 4));
		$bytes = fread($handle, $count * 4);
		format_benchmark_assert(strlen($bytes) === $count * 4, "binary ID data is short");
		return array_values(unpack("N*", $bytes));
	}
	return [];
}

function format_benchmark_close_cache(array &$cache): void {
	foreach ($cache as $state) {
		if (is_array($state) && isset($state["handle"]) && is_resource($state["handle"])) fclose($state["handle"]);
	}
	$cache = [];
}

function format_benchmark_worker(string $format, string $root, int $queries, bool $same_parent): array {
	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$cache = [];
	$started = microtime(true);
	$rows = 0;
	for ($i = 0; $i < $queries; $i++) {
		$parent_id = $same_parent ? 54321 : ((($i * 7919) % INDEX_FORMAT_PARENTS) + 1);
		$ids = $format === "json"
			? format_benchmark_json_lookup($root, $parent_id, $cache)
			: format_benchmark_binary_lookup($root, $parent_id, $cache);
		format_benchmark_assert(count($ids) === INDEX_FORMAT_IDS_PER_PARENT, $format . " lookup count mismatch");
		$rows += count($ids);
	}
	$result = [
		"seconds" => microtime(true) - $started,
		"queries" => $queries,
		"rows" => $rows,
		"loaded_shards" => count($cache),
		"memory_bytes" => memory_get_usage(true),
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
	format_benchmark_close_cache($cache);
	return $result;
}

if (($argv[1] ?? "") === "--worker") {
	if ($argc !== 6 || !in_array($argv[2], ["json", "binary"], true)) exit(2);
	$result = format_benchmark_worker($argv[2], $argv[3], (int) $argv[4], $argv[5] === "same");
	echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

function format_benchmark_subprocess(string $format, string $root, int $queries, bool $same_parent): array {
	$command = escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__FILE__)
		. " --worker " . escapeshellarg($format) . " " . escapeshellarg($root)
		. " " . $queries . " " . ($same_parent ? "same" : "spread");
	$started = microtime(true);
	$output = shell_exec($command);
	$result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
	$result["process_seconds"] = microtime(true) - $started;
	return $result;
}

function format_benchmark_concurrent(string $format, string $root, int $process_count, int $queries): array {
	$processes = [];
	$started = microtime(true);
	for ($i = 0; $i < $process_count; $i++) {
		$command = escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__FILE__)
			. " --worker " . escapeshellarg($format) . " " . escapeshellarg($root) . " " . $queries . " spread";
		$proc = proc_open($command, [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
		format_benchmark_assert(is_resource($proc), "could not start " . $format . " worker");
		$processes[] = [$proc, $pipes];
	}
	$workers = [];
	foreach ($processes as [$proc, $pipes]) {
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$status = proc_close($proc);
		format_benchmark_assert($status === 0, $format . " worker failed: " . trim($error));
		$workers[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	}
	return [
		"processes" => $process_count,
		"queries_per_process" => $queries,
		"wall_seconds" => microtime(true) - $started,
		"sum_memory_bytes" => array_sum(array_column($workers, "memory_bytes")),
		"max_peak_memory_bytes" => max(array_column($workers, "peak_memory_bytes")),
		"workers" => $workers,
	];
}

$root = sys_get_temp_dir() . "/ffm-index-format-" . bin2hex(random_bytes(5));
try {
	mkdir($root . "/json", 0777, true);
	mkdir($root . "/binary", 0777, true);
	$shards = array_fill(0, INDEX_FORMAT_SHARDS, []);
	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	for ($parent_id = 1; $parent_id <= INDEX_FORMAT_PARENTS; $parent_id++) {
		$raw_hash = format_benchmark_hash($parent_id);
		$ids = [];
		for ($i = 0; $i < INDEX_FORMAT_IDS_PER_PARENT; $i++) $ids[] = $parent_id + ($i * INDEX_FORMAT_PARENTS);
		$shards[format_benchmark_shard($raw_hash)][bin2hex($raw_hash)] = $ids;
	}
	$map = [
		"seconds" => microtime(true) - $started,
		"memory_bytes" => memory_get_usage(true),
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];

	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	$json_bytes = 0;
	foreach ($shards as $shard => $values) {
		$payload = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		file_put_contents(format_benchmark_path($root, "json", $shard), $payload);
		$json_bytes += strlen($payload);
	}
	$json_build = [
		"seconds" => microtime(true) - $started,
		"bytes" => $json_bytes,
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];

	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	$binary_bytes = 0;
	foreach ($shards as $shard => $values) {
		ksort($values, SORT_STRING);
		$directory = "";
		$id_data = "";
		$id_offset = 0;
		foreach ($values as $hex_hash => $ids) {
			$directory .= hex2bin($hex_hash) . pack("NN", $id_offset, count($ids));
			$id_data .= pack("N*", ...$ids);
			$id_offset += count($ids);
		}
		$payload = INDEX_FORMAT_BINARY_MAGIC . pack("NN", count($values), $id_offset) . $directory . $id_data;
		file_put_contents(format_benchmark_path($root, "binary", $shard), $payload);
		$binary_bytes += strlen($payload);
	}
	$binary_build = [
		"seconds" => microtime(true) - $started,
		"bytes" => $binary_bytes,
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
	unset($shards, $values, $payload, $directory, $id_data);
	gc_collect_cycles();

	foreach ([1, 54321, 100000] as $parent_id) {
		$json_cache = [];
		$binary_cache = [];
		$json_ids = format_benchmark_json_lookup($root, $parent_id, $json_cache);
		$binary_ids = format_benchmark_binary_lookup($root, $parent_id, $binary_cache);
		format_benchmark_close_cache($json_cache);
		format_benchmark_close_cache($binary_cache);
		format_benchmark_assert($json_ids === $binary_ids, "format result mismatch for parent " . $parent_id);
	}

	$cold = ["json" => [], "binary" => []];
	foreach (["json", "binary"] as $format) {
		for ($i = 0; $i < 5; $i++) $cold[$format][] = format_benchmark_subprocess($format, $root, 1, true);
	}
	$spread = [
		"json" => format_benchmark_subprocess("json", $root, 1000, false),
		"binary" => format_benchmark_subprocess("binary", $root, 1000, false),
	];
	$hot = [
		"json" => format_benchmark_subprocess("json", $root, 10000, true),
		"binary" => format_benchmark_subprocess("binary", $root, 10000, true),
	];
	$concurrent = [
		"json" => format_benchmark_concurrent("json", $root, 8, 200),
		"binary" => format_benchmark_concurrent("binary", $root, 8, 200),
	];

	echo json_encode([
		"conditions" => [
			"shards" => INDEX_FORMAT_SHARDS,
			"parents" => INDEX_FORMAT_PARENTS,
			"ids" => INDEX_FORMAT_PARENTS * INDEX_FORMAT_IDS_PER_PARENT,
			"ids_per_parent" => INDEX_FORMAT_IDS_PER_PARENT,
		],
		"map_generation" => $map,
		"format_build" => ["json" => $json_build, "binary" => $binary_build],
		"cold_single_lookup" => $cold,
		"spread_1000_lookups" => $spread,
		"hot_same_parent_10000_lookups" => $hot,
		"concurrent_readers" => $concurrent,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} finally {
	format_benchmark_remove($root);
}
