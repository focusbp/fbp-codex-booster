<?php

interface Controller {
	function set_db_read_only(bool $read_only): bool;
}

require_once __DIR__ . "/../fbp/lib/Controller_class.php";
require_once __DIR__ . "/../fbp/app/db_exe/db_exe.php";

function standard_read_only_assert($condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
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

echo "standard screen read-only routing test passed\n";
