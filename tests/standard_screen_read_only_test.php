<?php

interface Controller {
	function set_db_read_only(bool $read_only): bool;
}

putenv("FBP_FFM_LOG_DISABLE=1");
require_once __DIR__ . "/../fbp/lib/fixed_file_manager/fixed_file_manager.php";
require_once __DIR__ . "/../fbp/lib/Controller_class.php";
require_once __DIR__ . "/../fbp/app/db_exe/db_exe.php";

function standard_read_only_assert($condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function standard_read_only_remove(string $dir): void {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), [".", ".."]) as $name) {
		$path = $dir . "/" . $name;
		is_dir($path) ? standard_read_only_remove($path) : unlink($path);
	}
	rmdir($dir);
}

class standard_read_only_test_controller extends Controller_class {
	private $test_dbs;
	public $assigned = [];

	function __construct(array $test_dbs) {
		$this->test_dbs = $test_dbs;
	}

	function db($name, ?string $class = null, ?string $separated_by = null): FFM {
		return $this->test_dbs[$name];
	}

	function assign($key, $val) {
		$this->assigned[$key] = $val;
	}
}

$display_functions = [
	"page", "search", "search_child", "search_weekly_calendar", "rows",
	"add", "edit", "delete", "rows_child", "add_child", "edit_child", "delete_child",
	"rows_weekly_calendar", "unassigned_tasks", "set_datetime", "reload",
];
foreach ($display_functions as $function_name) {
	standard_read_only_assert(db_exe::is_read_only_function($function_name), "display function is not read-only: " . $function_name);
}

$write_functions = [
	"add_exe", "edit_exe", "duplicate", "edit_datetime_exe", "delete_exe",
	"add_child_exe", "edit_child_exe", "delete_child_exe", "manual_sort",
];
foreach ($write_functions as $function_name) {
	standard_read_only_assert(!db_exe::is_read_only_function($function_name), "write function became read-only: " . $function_name);
}

$reflection = new ReflectionClass(Controller_class::class);
$ctl = $reflection->newInstanceWithoutConstructor();
$dbarr = $reflection->getProperty("dbarr");
$dbarr->setValue($ctl, []);
$read_only = $reflection->getProperty("db_read_only");
$read_only->setValue($ctl, false);
standard_read_only_assert($ctl->set_db_read_only(true), "empty controller could not enter read-only mode");
standard_read_only_assert($read_only->getValue($ctl) === true, "controller read-only mode was not stored");
$dbarr->setValue($ctl, ["open" => new stdClass()]);
standard_read_only_assert(!$ctl->set_db_read_only(false), "controller changed DB mode while a database was open");
standard_read_only_assert($read_only->getValue($ctl) === true, "failed mode change modified controller state");

$root = sys_get_temp_dir() . "/standard-screen-read-only-" . bin2hex(random_bytes(5));
try {
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	file_put_contents($root . "/fmt/screen_fields.fmt", "id,24,N\ntb_name,80,T\nscreen_name,40,T\ndb_fields_id,24,N\nsort,24,N\n");
	file_put_contents($root . "/fmt/db_fields.fmt", "id,24,N\n");
	$GLOBALS["lock_class_arr"] = [];
	$screen_fields = new fixed_file_manager("screen_fields", $root . "/data", $root . "/fmt");
	$stale_screen_field = [
		"tb_name" => "product_variant",
		"screen_name" => "search",
		"db_fields_id" => 999,
		"sort" => 1,
	];
	$screen_fields->insert($stale_screen_field);
	$screen_fields->close();
	$db_fields = new fixed_file_manager("db_fields", $root . "/data", $root . "/fmt");
	$db_fields->close();

	$GLOBALS["lock_class_arr"] = [];
	$screen_fields = new fixed_file_manager("screen_fields", $root . "/data", $root . "/fmt", ["read_only" => true]);
	$db_fields = new fixed_file_manager("db_fields", $root . "/data", $root . "/fmt", ["read_only" => true]);
	$ctl = new standard_read_only_test_controller([
		"screen_fields" => $screen_fields,
		"db_fields" => $db_fields,
	]);
	$ctl->assign_field_settings("search_group", "product_variant", "search");
	standard_read_only_assert(($ctl->assigned["search_group"] ?? null) === [], "stale screen field was not ignored");
	standard_read_only_assert(count($screen_fields->getall()) === 1, "display unexpectedly changed screen_fields");
} finally {
	if (isset($screen_fields) && $screen_fields instanceof fixed_file_manager) $screen_fields->close();
	if (isset($db_fields) && $db_fields instanceof fixed_file_manager) $db_fields->close();
	standard_read_only_remove($root);
}

echo "standard screen read-only routing test passed\n";
