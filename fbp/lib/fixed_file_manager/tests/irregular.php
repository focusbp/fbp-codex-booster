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

function irregular_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? irregular_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function irregular_root(string $name): string {
	$root = sys_get_temp_dir() . "/ffm-index-irregular-" . $name . "-" . bin2hex(random_bytes(5));
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	return $root;
}

function irregular_open(string $root, ?string $fmt = null, array $options = []): fixed_file_manager {
	if ($fmt !== null) file_put_contents($root . "/fmt/sample.fmt", $fmt);
	$GLOBALS["lock_class_arr"] = [];
	return new fixed_file_manager("sample", $root . "/data", $root . "/fmt", $options);
}

function irregular_same($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException($message . "\nexpected=" . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\nactual=" . json_encode($actual, JSON_UNESCAPED_UNICODE));
	}
}

function irregular_query_matrix(fixed_file_manager $ffm): array {
	$out = [];
	$queries = [
		["text", "", true],
		["text", "  ", true],
		["text", "日本語", true],
		["text", " 前後 ", true],
		["number", 0, true],
		["number", "0", true],
		["number", -9, true],
		["number", 999999999, true],
		["decimal", 0.0, true],
		["decimal", -1.25, true],
		["decimal", 1.23456789, true],
	];
	foreach ($queries as $i => [$field, $value, $exact]) {
		$is_last = null;
		$out["select_$i"] = $ffm->select($field, $value, true, "AND", "id", SORT_ASC, 2, $is_last);
		$out["select_{$i}_last"] = $is_last;
		$is_last = null;
		$out["filter_$i"] = $ffm->filter($field, $value, $exact, "AND", "id", SORT_DESC, 3, $is_last);
		$out["filter_{$i}_last"] = $is_last;
	}
	$out["and"] = $ffm->select(["text", "number"], ["日本語", 0], ["=", "="], "AND");
	$out["or_fallback"] = $ffm->select(["text", "number"], ["日本語", -9], ["=", "="], "OR");
	$out["range_fallback"] = $ffm->select("number", 0, ">=");
	$out["partial_fallback"] = $ffm->filter("text", "日本", false);
	$ffm->set_flg_filter_zero(true);
	$out["standard_numeric"] = $ffm->filter("number", 0, false, "AND", "id", SORT_ASC, null, $is_last, ["="]);
	$ffm->set_flg_filter_zero(false);
	$out["standard_numeric_range"] = $ffm->filter("number", -9, false, "AND", "id", SORT_ASC, null, $is_last, [">="]);
	$out["standard_mixed"] = $ffm->filter(["number", "text"], [0, "日本"], false, "AND", "id", SORT_ASC, null, $is_last, ["=", "="]);
	return $out;
}

function irregular_index_dir(string $root, string $field): string {
	return $root . "/data/sample.dat." . $field . ".idx";
}

function irregular_shard_path(string $root, string $field, string $type, $value): string {
	if ($type === "N") $normalized = (string) (int) $value;
	elseif ($type === "F") $normalized = sprintf("%.17g", (float) $value);
	else $normalized = trim((string) $value);
	$hash = hash("sha256", $type . "\0" . $normalized, true);
	return irregular_index_dir($root, $field) . "/" . sprintf("%03d.bin", ord($hash[0]) >> 1);
}

function irregular_write_at(string $path, int $offset, string $bytes): void {
	$handle = fopen($path, "r+b");
	if (!is_resource($handle)) throw new RuntimeException("could not mutate " . $path);
	fseek($handle, $offset);
	fwrite($handle, $bytes);
	fclose($handle);
}

function irregular_compare_with_legacy(string $root, string $message): void {
	$indexed = irregular_open($root);
	try {
		$actual = irregular_query_matrix($indexed);
	} finally {
		$indexed->close();
	}
	$legacy = irregular_open($root, null, ["index_disabled" => true]);
	try {
		$expected = irregular_query_matrix($legacy);
	} finally {
		$legacy->close();
	}
	foreach ($expected as $key => $value) {
		irregular_same($value, $actual[$key] ?? null, $message . " at " . $key);
	}
	irregular_same(array_keys($expected), array_keys($actual), $message . " (query keys)");
}

function irregular_wait_children(array $pids, int $timeout_seconds, string $message): void {
	$deadline = microtime(true) + $timeout_seconds;
	$remaining = array_fill_keys($pids, true);
	while (count($remaining) > 0 && microtime(true) < $deadline) {
		foreach (array_keys($remaining) as $pid) {
			$result = pcntl_waitpid($pid, $status, WNOHANG);
			if ($result === $pid) {
				unset($remaining[$pid]);
				if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
					throw new RuntimeException($message . ": child " . $pid . " failed");
				}
			}
		}
		usleep(10000);
	}
	if (count($remaining) > 0) {
		foreach (array_keys($remaining) as $pid) posix_kill($pid, SIGKILL);
		throw new RuntimeException($message . ": timeout/deadlock");
	}
}

$fmt = "id,24,N,IDX\ntext,90,T,IDX\nnumber,24,N,IDX\ndecimal,30,F,IDX\npayload,120,T\n";
$fmt_without_idx = "id,24,N\ntext,90,T\nnumber,24,N\ndecimal,30,F\npayload,120,T\n";
$roots = [];
$results = [];
$owner_pid = getmypid();
$run = static function (string $name, callable $test) use (&$results): void {
	fwrite(STDERR, "RUN  " . $name . "\n");
	try {
		$test();
		$results[$name] = ["status" => "PASS"];
		fwrite(STDERR, "PASS " . $name . "\n");
	} catch (Throwable $e) {
		$results[$name] = ["status" => "FAIL", "error" => get_class($e) . ": " . $e->getMessage()];
		fwrite(STDERR, "FAIL " . $name . ": " . get_class($e) . "\n");
	}
};

try {
	$base = $roots[] = irregular_root("base");
	$ffm = irregular_open($base, $fmt);
	foreach ([
		["text" => "", "number" => 0, "decimal" => 0.0, "payload" => "empty"],
		["text" => "  ", "number" => "0", "decimal" => "0.0", "payload" => "spaces"],
		["text" => "日本語", "number" => -9, "decimal" => -1.25, "payload" => "日本語本文"],
		["text" => " 前後 ", "number" => 999999999, "decimal" => 1.23456789, "payload" => "trim"],
		["text" => "日本語", "number" => 0, "decimal" => 1.23456789, "payload" => "duplicate"],
	] as $row) $ffm->insert($row);
	$ffm->close();

	$run("boundary_values_on_off", static function () use ($base): void {
		irregular_compare_with_legacy($base, "boundary-value indexed and legacy results differ");
	});

	$run("update_non_indexed_field", static function () use ($base): void {
		$ffm = irregular_open($base);
		$row = $ffm->get(3);
		$row["payload"] = "changed-only-non-indexed";
		$ffm->update($row);
		$ffm->close();
		irregular_compare_with_legacy($base, "non-indexed update changed indexed behavior");
	});

	$run("missing_index", static function () use ($base): void {
		$path = irregular_index_dir($base, "text");
		irregular_remove($path);
		irregular_compare_with_legacy($base, "missing index did not fall back");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
	});

	$run("empty_index", static function () use ($base): void {
		$path = irregular_index_dir($base, "text") . "/manifest.bin";
		file_put_contents($path, "");
		irregular_compare_with_legacy($base, "empty index did not fall back");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
	});

	$run("orphan_tmp_only", static function () use ($base): void {
		$path = irregular_index_dir($base, "text");
		rename($path, $path . ".tmp.orphan");
		irregular_compare_with_legacy($base, "orphan tmp did not fall back");
		irregular_remove($path . ".tmp.orphan");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
	});

	$run("legacy_json_auto_migration", static function () use ($base): void {
		$path = irregular_index_dir($base, "text");
		irregular_remove($path);
		file_put_contents($path, '{"meta":{"version":1},"values":{}}');
		$ffm = irregular_open($base);
		$rows = $ffm->select("text", "日本語");
		$ffm->close();
		irregular_same(2, count($rows), "legacy JSON migration changed results");
		if (!is_dir($path) || !is_file($path . "/manifest.bin")) throw new RuntimeException("legacy JSON index was not migrated to binary shards");
	});

	$run("dirty_after_forced_exit", static function () use ($base): void {
		$pid = pcntl_fork();
		if ($pid === 0) {
			file_put_contents($base . "/data/sample.dat.idx.dirty", "1\n", LOCK_EX);
			posix_kill(posix_getpid(), SIGKILL);
			exit(9);
		}
		pcntl_waitpid($pid, $status);
		if (!pcntl_wifsignaled($status) || pcntl_wtermsig($status) !== SIGKILL) {
			throw new RuntimeException("child was not killed as expected");
		}
		irregular_compare_with_legacy($base, "dirty state after forced exit did not fall back");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
		if (is_file($base . "/data/sample.dat.idx.dirty")) throw new RuntimeException("dirty state remained after rebuild");
	});

	$run("read_only_corruption_falls_back_without_repair", static function () use ($base): void {
		$path = irregular_index_dir($base, "text") . "/manifest.bin";
		irregular_write_at($path, 8, pack("N", 999));
		$corrupt_hash = hash_file("sha256", $path);
		$ffm = irregular_open($base, null, ["read_only" => true]);
		$actual = $ffm->select("text", "日本語");
		$ffm->close();
		$legacy = irregular_open($base, null, ["read_only" => true, "index_disabled" => true]);
		$expected = $legacy->select("text", "日本語");
		$legacy->close();
		irregular_same($expected, $actual, "read-only corrupt index did not fall back");
		irregular_same($corrupt_hash, hash_file("sha256", $path), "read-only open unexpectedly repaired index");
		$ffm = irregular_open($base); $ffm->close();
		if (hash_file("sha256", $path) === $corrupt_hash) throw new RuntimeException("writable open did not auto-repair index");
	});

	foreach ([
		"wrong_manifest_version" => static function (string $root): void { irregular_write_at(irregular_index_dir($root, "text") . "/manifest.bin", 8, pack("N", 999)); },
		"wrong_manifest_shards" => static function (string $root): void { irregular_write_at(irregular_index_dir($root, "text") . "/manifest.bin", 12, pack("N", 64)); },
		"wrong_manifest_maxid" => static function (string $root): void { irregular_write_at(irregular_index_dir($root, "text") . "/manifest.bin", 16, pack("J", 999)); },
		"wrong_manifest_format_hash" => static function (string $root): void { irregular_write_at(irregular_index_dir($root, "text") . "/manifest.bin", 40, str_repeat("X", 32)); },
		"wrong_manifest_identity" => static function (string $root): void { irregular_write_at(irregular_index_dir($root, "text") . "/manifest.bin", 72, str_repeat("Y", 32)); },
		"missing_shard" => static function (string $root): void { unlink(irregular_shard_path($root, "text", "T", "日本語")); },
		"truncated_shard" => static function (string $root): void { file_put_contents(irregular_shard_path($root, "text", "T", "日本語"), "short"); },
		"wrong_shard_number" => static function (string $root): void { irregular_write_at(irregular_shard_path($root, "text", "T", "日本語"), 12, pack("N", 999)); },
	] as $name => $mutate) {
		$run($name, static function () use ($base, $mutate, $name): void {
			$mutate($base);
			try {
				irregular_compare_with_legacy($base, $name . " did not fall back");
			} finally {
				$ffm = irregular_open($base);
				$ffm->rebuild_indexes();
				$ffm->close();
			}
		});
	}

	$run("shard_checksum_mismatch_marks_dirty", static function () use ($base): void {
		$path = irregular_shard_path($base, "text", "T", "日本語");
		irregular_write_at($path, filesize($path) - 1, "Z");
		irregular_compare_with_legacy($base, "checksum mismatch did not fall back");
		if (!is_file($base . "/data/sample.dat.idx.dirty")) throw new RuntimeException("checksum mismatch did not mark index dirty");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
	});

	$run("standard_numeric_corruption_falls_back", static function () use ($base): void {
		$path = irregular_shard_path($base, "number", "N", -9);
		$offset = filesize($path) - 1;
		$current = file_get_contents($path, false, null, $offset, 1);
		irregular_write_at($path, $offset, chr(ord($current) ^ 0xff));
		$ffm = irregular_open($base);
		$actual = $ffm->filter("number", -9, false, "AND", "id", SORT_ASC, null, $is_last, ["="]);
		$ffm->close();
		$legacy = irregular_open($base, null, ["index_disabled" => true]);
		$expected = $legacy->filter("number", -9, false, "AND", "id", SORT_ASC, null, $is_last, ["="]);
		$legacy->close();
		irregular_same($expected, $actual, "Standard Screen numeric filter did not fall back after corruption");
		if (!is_file($base . "/data/sample.dat.idx.dirty")) throw new RuntimeException("Standard Screen numeric corruption did not mark index dirty");
		$ffm = irregular_open($base); $ffm->rebuild_indexes(); $ffm->close();
	});

	if (function_exists("pcntl_fork") && function_exists("posix_kill")) {
		$run("concurrent_writers_and_readers", static function () use (&$roots, $fmt): void {
			$root = $roots[] = irregular_root("concurrent");
			$seed = irregular_open($root, $fmt); $seed->close();
			$pids = [];
			for ($worker = 0; $worker < 3; $worker++) {
				$pid = pcntl_fork();
				if ($pid === 0) {
					for ($i = 0; $i < 20; $i++) {
						$ffm = irregular_open($root);
						$row = ["text" => "worker-" . $worker, "number" => $worker, "decimal" => $i / 10, "payload" => "row-" . $i];
						$ffm->insert($row);
						$ffm->close();
					}
					exit(0);
				}
				$pids[] = $pid;
			}
			for ($reader = 0; $reader < 3; $reader++) {
				$pid = pcntl_fork();
				if ($pid === 0) {
					for ($i = 0; $i < 30; $i++) {
						$ffm = irregular_open($root, null, ["read_only" => true]);
						$rows = $ffm->select("number", $i % 3);
						foreach ($rows as $row) if ((int) $row["number"] !== $i % 3) exit(3);
						$ffm->close();
					}
					exit(0);
				}
				$pids[] = $pid;
			}
			irregular_wait_children($pids, 20, "concurrent readers/writers");
			$ffm = irregular_open($root);
			if (count($ffm->getall()) !== 60) throw new RuntimeException("concurrent inserts lost rows");
			$ffm->close();
			irregular_compare_with_legacy($root, "concurrent final index differs from dat scan");
		});

		$run("concurrent_change_format", static function () use (&$roots, $fmt, $fmt_without_idx): void {
			$root = $roots[] = irregular_root("change-format");
			$ffm = irregular_open($root, $fmt_without_idx);
			for ($i = 0; $i < 20; $i++) {
				$row = ["text" => "same", "number" => $i % 2, "decimal" => $i / 10, "payload" => "data"];
				$ffm->insert($row);
			}
			$ffm->close();
			file_put_contents($root . "/fmt/sample.fmt", $fmt);
			$pids = [];
			for ($i = 0; $i < 5; $i++) {
				$pid = pcntl_fork();
				if ($pid === 0) {
					$child = irregular_open($root);
					$rows = $child->select("number", 1);
					$child->close();
					exit(count($rows) === 10 ? 0 : 4);
				}
				$pids[] = $pid;
			}
			irregular_wait_children($pids, 20, "concurrent changeFormat");
			irregular_compare_with_legacy($root, "changeFormat final index differs from dat scan");
		});
	}

	$failed = array_filter($results, static fn(array $result): bool => $result["status"] === "FAIL");
	echo json_encode([
		"summary" => ["total" => count($results), "passed" => count($results) - count($failed), "failed" => count($failed)],
		"results" => $results,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
	exit(count($failed) === 0 ? 0 : 1);
} finally {
	if (getmypid() === $owner_pid) {
		foreach (array_reverse($roots) as $root) irregular_remove($root);
	}
}
