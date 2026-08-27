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

function standard_read_only_real_controller(string $root, bool $read_only): Controller_class {
	$dirs = new class($root) {
		public string $datadir;
		private string $root;

		function __construct(string $root) {
			$this->root = $root;
			$this->datadir = $root . "/data";
		}

		function get_class_dir(string $class): string {
			return $this->root . "/classes/" . $class;
		}
	};
	$reflection = new ReflectionClass(Controller_class::class);
	$ctl = $reflection->newInstanceWithoutConstructor();
	$reflection->getProperty("dirs")->setValue($ctl, $dirs);
	$reflection->getProperty("class")->setValue($ctl, "common");
	$reflection->getProperty("dbarr")->setValue($ctl, []);
	$reflection->getProperty("db_read_only")->setValue($ctl, false);
	if ($read_only) {
		standard_read_only_assert($ctl->set_db_read_only(true), "controller could not enter read-only mode");
	}
	return $ctl;
}

function standard_read_only_wait_for_file(string $path): void {
	$deadline = microtime(true) + 3;
	while (!is_file($path)) {
		if (microtime(true) >= $deadline) {
			throw new RuntimeException("timed out waiting for barrier: " . basename($path));
		}
		usleep(10000);
	}
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

// A read-only reverse-order open must enter the normal lock ordering before it waits.
if (function_exists("pcntl_fork") && function_exists("posix_kill")) {
	$lock_root = sys_get_temp_dir() . "/standard-screen-lock-order-" . bin2hex(random_bytes(5));
	try {
		mkdir($lock_root . "/data/common", 0777, true);
		mkdir($lock_root . "/classes/common/fmt", 0777, true);
		$lock_fmt = "id,24,N\nname,60,T\n";
		foreach (["a_table", "z_table"] as $table) {
			file_put_contents($lock_root . "/classes/common/fmt/" . $table . ".fmt", $lock_fmt);
			$GLOBALS["lock_class_arr"] = [];
			$seed = new fixed_file_manager($table, $lock_root . "/data/common", $lock_root . "/classes/common/fmt");
			$seed->close();
		}

		$reader_ready = $lock_root . "/reader.ready";
		$writer_ready = $lock_root . "/writer.ready";
		$reader_pid = pcntl_fork();
		if ($reader_pid === 0) {
			try {
				$GLOBALS["lock_class_arr"] = [];
				$reader = standard_read_only_real_controller($lock_root, true);
				$reader->db("z_table", "common");
				file_put_contents($reader_ready, "1");
				standard_read_only_wait_for_file($writer_ready);
				$reader->db("a_table", "common");
				$reader->close_all_db();
				exit(0);
			} catch (Throwable $e) {
				fwrite(STDERR, $e->getMessage() . "\n");
				exit(1);
			}
		}
		standard_read_only_assert($reader_pid > 0, "reader fork failed");

		$writer_pid = pcntl_fork();
		if ($writer_pid === 0) {
			try {
				$GLOBALS["lock_class_arr"] = [];
				$writer = standard_read_only_real_controller($lock_root, false);
				$writer->db("a_table", "common");
				file_put_contents($writer_ready, "1");
				standard_read_only_wait_for_file($reader_ready);
				$writer->db("z_table", "common");
				$writer->close_all_db();
				exit(0);
			} catch (Throwable $e) {
				fwrite(STDERR, $e->getMessage() . "\n");
				exit(1);
			}
		}
		standard_read_only_assert($writer_pid > 0, "writer fork failed");

		$pending = [$reader_pid => true, $writer_pid => true];
		$deadline = microtime(true) + 5;
		while (!empty($pending) && microtime(true) < $deadline) {
			foreach (array_keys($pending) as $pid) {
				$result = pcntl_waitpid($pid, $status, WNOHANG);
				if ($result === $pid) {
					unset($pending[$pid]);
					standard_read_only_assert(
						pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
						"ordered lock child failed"
					);
				}
			}
			if (!empty($pending)) usleep(20000);
		}
		if (!empty($pending)) {
			foreach (array_keys($pending) as $pid) posix_kill($pid, SIGKILL);
			foreach (array_keys($pending) as $pid) pcntl_waitpid($pid, $status);
			throw new RuntimeException("read-only reverse-order open deadlocked");
		}
	} finally {
		standard_read_only_remove($lock_root);
	}
}

echo "standard screen read-only routing test passed\n";
