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

function daily_open(string $root, string $table, array $options = []): fixed_file_manager {
	$GLOBALS["lock_class_arr"] = [];
	return new fixed_file_manager($table, $root . "/data", $root . "/fmt", $options);
}

function daily_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? daily_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function daily_assert(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function daily_timed(callable $callback): array {
	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	$value = $callback();
	return [
		"value" => $value,
		"seconds" => microtime(true) - $started,
		"memory_bytes" => memory_get_usage(true),
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
}

function daily_close(?fixed_file_manager &$ffm): void {
	if ($ffm instanceof fixed_file_manager) $ffm->close();
	$ffm = null;
	gc_collect_cycles();
}

if (($argv[1] ?? "") === "--reader-worker") {
	if ($argc !== 4) exit(2);
	$root = $argv[2];
	$iterations = (int) $argv[3];
	if (function_exists("memory_reset_peak_usage")) memory_reset_peak_usage();
	$started = microtime(true);
	$ffm = daily_open($root, "customer_history", ["read_only" => true]);
	$open_seconds = microtime(true) - $started;
	$loaded_memory = memory_get_usage(true);
	$started = microtime(true);
	$total_rows = 0;
	for ($i = 0; $i < $iterations; $i++) {
		$parent_id = (($i * 7919) % 100000) + 1;
		$rows = $ffm->select("parent_id", $parent_id, true, "AND", "action_date", SORT_DESC, 20);
		if (count($rows) !== 10) exit(3);
		$total_rows += count($rows);
	}
	$query_seconds = microtime(true) - $started;
	$result = [
		"open_seconds" => $open_seconds,
		"query_seconds" => $query_seconds,
		"iterations" => $iterations,
		"rows" => $total_rows,
		"loaded_memory_bytes" => $loaded_memory,
		"peak_memory_bytes" => memory_get_peak_usage(true),
	];
	$ffm->close();
	echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

$customer_count = 100000;
$history_count = 1000000;
$reader_processes = 8;
$reader_iterations = 200;
$root = sys_get_temp_dir() . "/ffm-index-daily-" . bin2hex(random_bytes(5));
$ffm = null;

try {
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	$customer_fmt = implode("\n", [
		"id,24,N",
		"name,90,T",
		"postal_code,8,T",
		"prefecture,30,T",
		"address1,120,T",
		"address2,120,T",
		"phone,20,T",
		"email,120,T",
		"status,24,N",
		"registered_date,10,T",
	]) . "\n";
	$history_fmt_legacy = implode("\n", [
		"id,24,N",
		"parent_id,24,N",
		"action_date,10,T",
		"memo,300,T",
		"author_name,90,T",
		"category,24,N",
	]) . "\n";
	$history_fmt_indexed = str_replace("parent_id,24,N", "parent_id,24,N,IDX", $history_fmt_legacy);
	file_put_contents($root . "/fmt/customer.fmt", $customer_fmt);
	file_put_contents($root . "/fmt/customer_history.fmt", $history_fmt_legacy);

	$family_names = ["佐藤", "鈴木", "高橋", "田中", "伊藤", "渡辺", "山本", "中村", "小林", "加藤"];
	$given_names = ["太郎", "花子", "一郎", "美咲", "健一", "陽子", "大輔", "直子", "誠", "恵"];
	$prefectures = ["東京都", "大阪府", "北海道", "福岡県", "愛知県", "宮城県", "広島県", "京都府"];
	$authors = ["山田担当", "佐々木担当", "営業一課", "サポート窓口", "管理者"];
	$memos = [
		"電話で契約内容を確認しました。次回連絡日を案内済みです。",
		"住所変更の連絡を受け、登録内容と送付先を確認しました。",
		"問い合わせに回答し、追加資料をメールで送付しました。",
		"定期フォローを実施しました。現時点で追加対応はありません。",
		"来店時の相談内容を記録しました。担当者へ引き継ぎ済みです。",
	];

	fwrite(STDERR, "Generating 100,000 customers...\n");
	$ffm = daily_open($root, "customer", ["index_disabled" => true]);
	$customer_insert = daily_timed(static function () use ($ffm, $customer_count, $family_names, $given_names, $prefectures): void {
		for ($i = 1; $i <= $customer_count; $i++) {
			$row = [
				"name" => $family_names[$i % count($family_names)] . " " . $given_names[($i * 7) % count($given_names)] . sprintf("%06d", $i),
				"postal_code" => sprintf("%07d", $i % 10000000),
				"prefecture" => $prefectures[$i % count($prefectures)],
				"address1" => "中央区サンプル町" . ($i % 5000) . "番地",
				"address2" => "テストビル" . ($i % 300) . "号室",
				"phone" => sprintf("090-%04d-%04d", intdiv($i, 10000) % 10000, $i % 10000),
				"email" => "customer" . $i . "@example.test",
				"status" => $i % 3,
				"registered_date" => sprintf("2025-%02d-%02d", ($i % 12) + 1, ($i % 28) + 1),
			];
			$ffm->insert($row);
		}
	});
	daily_close($ffm);

	fwrite(STDERR, "Generating 1,000,000 customer histories...\n");
	$ffm = daily_open($root, "customer_history", ["index_disabled" => true]);
	$history_insert = daily_timed(static function () use ($ffm, $history_count, $authors, $memos): void {
		for ($i = 1; $i <= $history_count; $i++) {
			$row = [
				"parent_id" => (($i - 1) % 100000) + 1,
				"action_date" => sprintf("2026-%02d-%02d", ($i % 12) + 1, ($i % 28) + 1),
				"memo" => $memos[$i % count($memos)] . " 管理番号:" . $i,
				"author_name" => $authors[$i % count($authors)],
				"category" => $i % 5,
			];
			$ffm->insert($row);
		}
	});

	$customer_get = daily_timed(static function () use ($root): int {
		$customer = daily_open($root, "customer", ["read_only" => true]);
		$found = 0;
		for ($i = 0; $i < 10000; $i++) {
			$id = (($i * 7919) % 100000) + 1;
			if ($customer->get($id) !== null) $found++;
		}
		$customer->close();
		return $found;
	});
	$customer_partial = daily_timed(static function () use ($root): int {
		$customer = daily_open($root, "customer", ["read_only" => true]);
		$rows = $customer->filter("name", "佐藤", false, "AND", null, SORT_DESC, 20);
		$customer->close();
		return count($rows);
	});

	$legacy_history = daily_timed(static function () use ($ffm): array {
		return $ffm->select("parent_id", 54321, true, "AND", "action_date", SORT_DESC, 20);
	});
	daily_assert(count($legacy_history["value"]) === 10, "legacy history count mismatch");
	daily_close($ffm);

	fwrite(STDERR, "Building parent_id index with 100,000 distinct parents...\n");
	file_put_contents($root . "/fmt/customer_history.fmt", $history_fmt_indexed);
	$index_build = daily_timed(static function () use ($root): fixed_file_manager {
		return daily_open($root, "customer_history");
	});
	$ffm = $index_build["value"];
	$index_build["value"] = null;
	$index_loaded_memory = memory_get_usage(true);

	$indexed_once = daily_timed(static function () use ($ffm): array {
		return $ffm->select("parent_id", 54321, true, "AND", "action_date", SORT_DESC, 20);
	});
	daily_assert($legacy_history["value"] === $indexed_once["value"], "indexed and legacy daily history differ");
	$hot_queries = daily_timed(static function () use ($ffm): int {
		$total = 0;
		for ($i = 0; $i < 1000; $i++) {
			$parent_id = (($i * 7919) % 100000) + 1;
			$total += count($ffm->select("parent_id", $parent_id, true, "AND", "action_date", SORT_DESC, 20));
		}
		return $total;
	});
	daily_assert($hot_queries["value"] === 10000, "hot parent history query count mismatch");

	$crud = [];
	$crud["insert"] = daily_timed(static function () use ($ffm): int {
		$row = ["parent_id" => 54321, "action_date" => "2026-12-31", "memo" => "日常利用テストで追加", "author_name" => "テスト担当", "category" => 9];
		return $ffm->insert($row);
	});
	$new_id = $crud["insert"]["value"];
	daily_assert(count($ffm->select("parent_id", 54321)) === 11, "insert was not reflected in index");
	$crud["update_memo"] = daily_timed(static function () use ($ffm, $new_id): void {
		$row = $ffm->get($new_id);
		$row["memo"] = "メモだけを更新しました";
		$ffm->update($row);
	});
	$crud["move_parent"] = daily_timed(static function () use ($ffm, $new_id): void {
		$row = $ffm->get($new_id);
		$row["parent_id"] = 99999;
		$ffm->update($row);
	});
	daily_assert(count($ffm->select("parent_id", 54321)) === 10, "old parent retained moved history");
	daily_assert(count($ffm->select("parent_id", 99999)) === 11, "new parent did not receive moved history");
	$moved = $ffm->get($new_id);
	$crud["delete"] = daily_timed(static function () use ($ffm, $new_id): void { $ffm->delete($new_id); });
	daily_assert(count($ffm->select("parent_id", 99999)) === 10, "delete was not reflected in index");
	$crud["restore"] = daily_timed(static function () use ($ffm, $moved): void { $ffm->restore_deleted_record($moved); });
	daily_assert(count($ffm->select("parent_id", 99999)) === 11, "restore was not reflected in index");
	$ffm->delete($new_id);
	daily_close($ffm);

	fwrite(STDERR, "Measuring cold opens and concurrent readers...\n");
	$script = __FILE__;
	$cold = [];
	for ($i = 0; $i < 3; $i++) {
		$command = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($script) . " --reader-worker " . escapeshellarg($root) . " 1";
		$started = microtime(true);
		$output = shell_exec($command);
		$result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
		$result["process_seconds"] = microtime(true) - $started;
		$cold[] = $result;
	}

	$processes = [];
	$concurrent_started = microtime(true);
	for ($i = 0; $i < $reader_processes; $i++) {
		$command = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($script) . " --reader-worker " . escapeshellarg($root) . " " . $reader_iterations;
		$descriptor = [1 => ["pipe", "w"], 2 => ["pipe", "w"]];
		$proc = proc_open($command, $descriptor, $pipes);
		daily_assert(is_resource($proc), "failed to start reader worker");
		$processes[] = [$proc, $pipes];
	}
	$concurrent = [];
	foreach ($processes as [$proc, $pipes]) {
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$status = proc_close($proc);
		daily_assert($status === 0, "reader worker failed: " . trim($error));
		$concurrent[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	}
	$concurrent_seconds = microtime(true) - $concurrent_started;

	$result = [
		"data" => [
			"customers" => $customer_count,
			"histories" => $history_count,
			"histories_per_customer" => 10,
			"customer_dat_bytes" => filesize($root . "/data/customer.dat"),
			"history_dat_bytes" => filesize($root . "/data/customer_history.dat"),
			"history_index_bytes" => filesize($root . "/data/customer_history.dat.parent_id.idx"),
		],
		"generation" => [
			"customer_seconds" => $customer_insert["seconds"],
			"customer_peak_memory_bytes" => $customer_insert["peak_memory_bytes"],
			"history_seconds" => $history_insert["seconds"],
			"history_peak_memory_bytes" => $history_insert["peak_memory_bytes"],
		],
		"customer_usage" => [
			"id_get_10000_seconds" => $customer_get["seconds"],
			"id_get_found" => $customer_get["value"],
			"partial_name_search_seconds" => $customer_partial["seconds"],
			"partial_name_result_limit" => $customer_partial["value"],
		],
		"history_usage" => [
			"legacy_parent_search_seconds" => $legacy_history["seconds"],
			"index_build_seconds" => $index_build["seconds"],
			"index_build_peak_memory_bytes" => $index_build["peak_memory_bytes"],
			"index_loaded_memory_bytes" => $index_loaded_memory,
			"indexed_parent_search_seconds" => $indexed_once["seconds"],
			"hot_1000_parent_queries_seconds" => $hot_queries["seconds"],
			"hot_1000_parent_queries_rows" => $hot_queries["value"],
		],
		"crud_seconds" => array_map(static fn(array $measurement): float => $measurement["seconds"], $crud),
		"cold_requests" => $cold,
		"concurrent_readers" => [
			"processes" => $reader_processes,
			"queries_per_process" => $reader_iterations,
			"wall_seconds" => $concurrent_seconds,
			"sum_loaded_memory_bytes" => array_sum(array_column($concurrent, "loaded_memory_bytes")),
			"max_process_peak_memory_bytes" => max(array_column($concurrent, "peak_memory_bytes")),
			"workers" => $concurrent,
		],
	];
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} finally {
	daily_close($ffm);
	daily_remove($root);
}
