<?php

if (PHP_SAPI !== "cli") {
	http_response_code(404);
	exit;
}

const FFM_BASELINE_COMMIT = "c240d3b89df899516442ae3850e2d25e854e4902";

class Controller_class {
	public static function getInstance() { return null; }
}

putenv("FBP_FFM_LOG_DISABLE=1");
$ffm_file = dirname(__DIR__) . "/fixed_file_manager.php";
require_once $ffm_file;

function ffm_test_assert($condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function ffm_test_same($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException($message . "\nexpected=" . json_encode($expected) . "\nactual=" . json_encode($actual));
	}
}

function ffm_test_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? ffm_test_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function ffm_test_root(string $name): string {
	$root = sys_get_temp_dir() . "/ffm-index-" . $name . "-" . bin2hex(random_bytes(5));
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	return $root;
}

function ffm_test_open(string $root, string $fmt, array $options = []): fixed_file_manager {
	file_put_contents($root . "/fmt/sample.fmt", $fmt);
	$GLOBALS["lock_class_arr"] = [];
	return new fixed_file_manager("sample", $root . "/data", $root . "/fmt", $options);
}

function ffm_test_rows(fixed_file_manager $ffm): void {
	foreach ([
		["parent_id" => 10, "name" => "alpha", "score" => 1.5, "status" => 0, "sort" => 30],
		["parent_id" => 20, "name" => "bravo", "score" => 2.5, "status" => 1, "sort" => 10],
		["parent_id" => 10, "name" => "charlie", "score" => 1.5, "status" => 1, "sort" => 20],
		["parent_id" => 10, "name" => "alpha", "score" => 3.5, "status" => 2, "sort" => 20],
	] as $row) $ffm->insert($row);
}

function ffm_test_query_matrix(fixed_file_manager $ffm): array {
	$out = [];
	foreach ([
		["parent_id", 10, true, "AND", null, SORT_DESC, null],
		["parent_id", 10, true, "AND", "id", SORT_ASC, 2],
		[["parent_id", "status"], [10, 1], true, "AND", "sort", SORT_ASC, 10],
		["name", "alpha", true, "AND", null, SORT_DESC, null],
		["score", 1.5, true, "AND", null, SORT_DESC, null],
	] as $i => $args) {
		$is_last = null;
		$out["filter_" . $i] = $ffm->filter($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $is_last);
		$out["filter_" . $i . "_last"] = $is_last;
	}
	$is_last = null;
	$out["select"] = $ffm->select(["parent_id", "status"], [10, 1], ["=", "="], "AND", "id", SORT_DESC, 5, $is_last);
	$out["select_last"] = $is_last;
	$out["partial"] = $ffm->filter("name", "ha", false);
	$out["partial_case"] = $ffm->filter("name", "ALP", false);
	$out["partial_tokens"] = $ffm->filter("name", "missing,CHAR", false);
	$out["standard_numeric"] = $ffm->filter("status", 1, false, "AND", "id", SORT_ASC, null, $is_last, ["="]);
	$ffm->set_flg_filter_zero(true);
	$out["standard_numeric_zero"] = $ffm->filter("status", 0, false, "AND", "id", SORT_ASC, null, $is_last, ["="]);
	$ffm->set_flg_filter_zero(false);
	$out["standard_mixed"] = $ffm->filter(["parent_id", "name"], [10, "ha"], false, "AND", "id", SORT_ASC, null, $is_last, ["=", "="]);
	$out["standard_numeric_range"] = $ffm->filter("status", 1, false, "AND", "id", SORT_ASC, null, $is_last, [">="]);
	$out["or"] = $ffm->select(["parent_id", "status"], [20, 2], ["=", "="], "OR");
	return $out;
}

$roots = [];
try {
	$fmt3 = "id,24,N\nparent_id,24,N\nname,60,T\nscore,24,F\nstatus,24,N\nsort,24,N\n";
	$fmt4 = "id,24,N,IDX\nparent_id,24,N,IDX\nname,60,T,IDX\nscore,24,F,IDX\nstatus,24,N,IDX\nsort,24,N\n";

	// IDX addition uses changeFormat and preserves rows/maxid.
	$root = $roots[] = ffm_test_root("format");
	$ffm = ffm_test_open($root, $fmt3);
	ffm_test_rows($ffm);
	$ffm->close();
	$ffm = ffm_test_open($root, $fmt4);
	ffm_test_same(4, count($ffm->getall()), "changeFormat lost rows");
	ffm_test_same(4, (int) $ffm->get_header_info()["maxid"], "changeFormat changed maxid");
	ffm_test_assert(str_contains($ffm->get_header_info()["format_txt"], "parent_id,24,N,IDX"), "IDX missing from dat header");
	ffm_test_assert(!file_exists($root . "/data/sample.dat.id.idx"), "id index must be ignored");
	ffm_test_assert(is_dir($root . "/data/sample.dat.parent_id.idx"), "parent_id index missing");
	ffm_test_assert(count(glob($root . "/data/sample.dat.parent_id.idx/*.bin")) === 129, "binary index must contain manifest and 128 shards");
	$formats = $ffm->get_format();
	ffm_test_assert(empty($formats[0]["indexed"]), "id IDX was not ignored");
	ffm_test_assert(!empty($formats[1]["indexed"]), "parent_id IDX was not parsed");

	// Indexed and forced legacy paths must be identical.
	$indexed = ffm_test_query_matrix($ffm);
	$ffm->close();
	$legacy = ffm_test_open($root, $fmt4, ["index_disabled" => true, "text_search_disabled" => true]);
	ffm_test_same($indexed, ffm_test_query_matrix($legacy), "initial indexed and legacy query results differ");
	$legacy->close();
	$ffm = ffm_test_open($root, $fmt4);
	$row = $ffm->get(3); $row["parent_id"] = 20; $ffm->update($row);
	$ffm->delete(2);
	$ffm->restore_deleted_record(["id" => 2, "parent_id" => 20, "name" => "bravo", "score" => 2.5, "status" => 1, "sort" => 10]);
	$after_crud = ffm_test_query_matrix($ffm);
	$ffm->close();
	$legacy = ffm_test_open($root, $fmt4, ["index_disabled" => true, "text_search_disabled" => true]);
	ffm_test_same($after_crud, ffm_test_query_matrix($legacy), "indexed and legacy query results differ after CRUD");
	$legacy->close();

	// Corrupt and dirty indexes must fall back to the legacy scan.
	file_put_contents($root . "/data/sample.dat.parent_id.idx/manifest.bin", "broken");
	$ffm = ffm_test_open($root, $fmt4);
	ffm_test_same($after_crud, ffm_test_query_matrix($ffm), "corrupt index did not fall back");
	$ffm->close();

	// Raw matches spanning fixed-width field boundaries must not become candidates.
	$boundary_root = $roots[] = ffm_test_root("text-boundary");
	$boundary_fmt = "id,24,N\nleft_text,3,T\nright_text,3,T\n";
	$ffm = ffm_test_open($boundary_root, $boundary_fmt);
	$boundary_row = ["left_text" => "foo", "right_text" => "bar"];
	$ffm->insert($boundary_row);
	ffm_test_same([], $ffm->filter("left_text", "foobar", false), "cross-field text match was accepted");
	ffm_test_same([], $ffm->filter([["left_text", "right_text"]], ["foobar"], false), "grouped cross-field text match was accepted");
	$ffm->close();
	file_put_contents($root . "/data/sample.dat.idx.dirty", "1\n");
	$ffm = ffm_test_open($root, $fmt4);
	ffm_test_same($after_crud, ffm_test_query_matrix($ffm), "dirty index did not fall back");
	$ffm->rebuild_indexes();
	$ffm->close();
	ffm_test_assert(!is_file($root . "/data/sample.dat.idx.dirty"), "rebuild did not clear dirty state");

	// Concurrent readers must see the same indexed result without deadlock.
	if (function_exists("pcntl_fork")) {
		$pids = [];
		for ($i = 0; $i < 6; $i++) {
			$pid = pcntl_fork();
			if ($pid === 0) {
				$GLOBALS["lock_class_arr"] = [];
				$child = new fixed_file_manager("sample", $root . "/data", $root . "/fmt", ["read_only" => true]);
				$rows = $child->select("parent_id", 20);
				$child->close();
				exit(count($rows) === 2 ? 0 : 1);
			}
			ffm_test_assert($pid > 0, "pcntl_fork failed");
			$pids[] = $pid;
		}
		foreach ($pids as $pid) {
			pcntl_waitpid($pid, $status);
			ffm_test_assert(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, "concurrent indexed reader failed");
		}
	}

	// Removing IDX uses changeFormat, preserves rows and stops creating indexes.
	$ffm = ffm_test_open($root, $fmt3);
	ffm_test_same(4, count($ffm->getall()), "IDX removal lost rows");
	ffm_test_assert(!str_contains($ffm->get_header_info()["format_txt"], "IDX"), "IDX removal did not update dat header");
	ffm_test_assert(!file_exists($root . "/data/sample.dat.parent_id.idx"), "IDX removal left binary index directory");
	$ffm->close();

	// allclear keeps valid empty indexes.
	$clear_root = $roots[] = ffm_test_root("clear");
	$ffm = ffm_test_open($clear_root, $fmt4);
	ffm_test_rows($ffm);
	$ffm->allclear();
	ffm_test_same([], $ffm->select("parent_id", 10), "allclear left indexed rows");
	$ffm->close();

	// Unknown, empty, lowercase and extra fourth-column options are rejected.
	foreach (["index", "", "idx", "IDX,OTHER"] as $option) {
		$bad = $roots[] = ffm_test_root("bad-format");
		file_put_contents($bad . "/fmt/sample.fmt", "id,24,N\nparent_id,24,N," . $option . "\n");
		$thrown = false;
		try { new fixed_file_manager("sample", $bad . "/data", $bad . "/fmt"); } catch (Exception $e) { $thrown = true; }
		ffm_test_assert($thrown, "invalid format option was accepted: " . $option);
	}

	// Differential run against the implementation immediately before index work.
	$repo = dirname(__DIR__, 4);
	$baseline_dir = $roots[] = ffm_test_root("baseline-source");
	$baseline_file = $baseline_dir . "/fixed_file_manager.php";
	$command = "git -C " . escapeshellarg($repo) . " show " . escapeshellarg(FFM_BASELINE_COMMIT . ":fbp/lib/fixed_file_manager/fixed_file_manager.php") . " 2>/dev/null";
	$baseline_source = shell_exec($command);
	if (!is_string($baseline_source) || $baseline_source === "") {
		echo "SKIP baseline differential test (Git source unavailable)\n";
	} else {
		file_put_contents($baseline_file, $baseline_source);
		copy(dirname(__DIR__, 3) . "/interface/FFM.php", $baseline_dir . "/FFM.php");
		$baseline_source = str_replace("dirname(__FILE__) . '/../../interface/FFM.php'", "__DIR__ . '/FFM.php'", $baseline_source);
		file_put_contents($baseline_file, $baseline_source);
		$scenario = __DIR__ . "/differential_scenario.php";
		$old_root = $roots[] = ffm_test_root("old");
		$new_root = $roots[] = ffm_test_root("new");
		$run = static function (string $file, string $work) use ($scenario): array {
			$cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($scenario) . " " . escapeshellarg($file) . " " . escapeshellarg($work);
			$json = shell_exec($cmd);
			ffm_test_assert(is_string($json) && $json !== "", "differential subprocess failed");
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		};
		$old = $run($baseline_file, $old_root);
		$new = $run($ffm_file, $new_root);
		ffm_test_same($old, $new, "baseline and current behavior/dat differ without IDX");
		ffm_test_same([], glob($new_root . "/data/*.idx*"), "IDX-free differential run created index files");
	}

	echo "fixed file manager index tests passed\n";
} finally {
	foreach (array_reverse($roots) as $root) ffm_test_remove($root);
}
