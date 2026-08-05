<?php

class Controller_class {
	public static function getInstance() {
		return null;
	}
}

$fixed_file_manager_file = getenv("FBP_FIXED_FILE_MANAGER_FILE");
if ($fixed_file_manager_file === false || $fixed_file_manager_file === "") {
	$fixed_file_manager_file = __DIR__ . "/../fbp/lib/fixed_file_manager/fixed_file_manager.php";
}
require_once $fixed_file_manager_file;

function ffm_filter_test_assert_same($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . "\nexpected: " . json_encode($expected) . "\nactual:   " . json_encode($actual)
		);
	}
}

function ffm_filter_test_delete_directory(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}
	foreach (array_diff(scandir($dir), [".", ".."]) as $item) {
		$path = $dir . "/" . $item;
		if (is_dir($path)) {
			ffm_filter_test_delete_directory($path);
		} else {
			unlink($path);
		}
	}
	rmdir($dir);
}

$root = sys_get_temp_dir() . "/fixed-file-manager-filter-" . bin2hex(random_bytes(6));
$ffm = null;

try {
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	file_put_contents(
		$root . "/fmt/sample.fmt",
		"id,24,N\nfirst_name,50,T\nmiddle_name,50,T\nlast_name,50,T\nsort,24,N\n"
	);

	$GLOBALS["lock_class_arr"] = [];
	$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");

	for ($i = 1; $i <= 12; $i++) {
		$row = [
			"first_name" => $i === 1 ? "TARGET" : "no",
			"middle_name" => $i === 2 ? "TARGET" : "no",
			"last_name" => $i === 3 ? "TARGET" : "no",
			"sort" => $i,
		];
		$ffm->insert($row);
	}

	$is_last = null;
	$rows = $ffm->filter(
		[["first_name", "middle_name", "last_name"]],
		["TARGET"],
		true,
		"AND",
		null,
		SORT_DESC,
		null,
		$is_last
	);
	ffm_filter_test_assert_same([3, 2, 1], array_column($rows, "id"), "grouped text fields were not OR matched");
	ffm_filter_test_assert_same(true, $is_last, "unlimited grouped filter did not reach the last row");

	foreach ([null, "sort"] as $sortitem) {
		$is_last = null;
		$rows = $ffm->filter([], [], false, "AND", $sortitem, SORT_DESC, 9, $is_last);
		ffm_filter_test_assert_same(9, count($rows), "filter below the total did not return max rows");
		ffm_filter_test_assert_same(false, $is_last, "filter below the total did not report remaining rows");

		$is_last = null;
		$rows = $ffm->filter([], [], false, "AND", $sortitem, SORT_DESC, 12, $is_last);
		ffm_filter_test_assert_same(12, count($rows), "filter at the total did not return max rows");
		ffm_filter_test_assert_same(true, $is_last, "filter at the total incorrectly reported remaining rows");

		$is_last = null;
		$rows = $ffm->filter([], [], false, "AND", $sortitem, SORT_DESC, 15, $is_last);
		ffm_filter_test_assert_same(12, count($rows), "filter above the total changed the result count");
		ffm_filter_test_assert_same(true, $is_last, "filter above the total did not reach the last row");
	}

	printf("fixed file manager filter test passed\n");
} finally {
	if ($ffm instanceof fixed_file_manager) {
		$ffm->close();
	}
	ffm_filter_test_delete_directory($root);
}
