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

function ffm_concurrent_format_test_delete_directory(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}
	foreach (array_diff(scandir($dir), [".", ".."]) as $item) {
		$path = $dir . "/" . $item;
		if (is_dir($path)) {
			ffm_concurrent_format_test_delete_directory($path);
		} else {
			unlink($path);
		}
	}
	rmdir($dir);
}

function ffm_concurrent_format_test_kill_children(array $pids): void {
	foreach ($pids as $pid) {
		@posix_kill($pid, SIGKILL);
	}
	foreach ($pids as $pid) {
		pcntl_waitpid($pid, $status);
	}
}

if (!function_exists("pcntl_fork") || !function_exists("posix_kill")) {
	throw new RuntimeException("pcntl and posix extensions are required for the concurrent format test.");
}

$root = sys_get_temp_dir() . "/fixed-file-manager-concurrent-format-" . bin2hex(random_bytes(6));
$pids = [];
$ffm = null;

try {
	mkdir($root . "/data", 0777, true);
	mkdir($root . "/fmt", 0777, true);
	file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nname,50,T\n");

	$GLOBALS["lock_class_arr"] = [];
	$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");
	$row = ["name" => "before"];
	$ffm->insert($row);
	$ffm->close();
	$ffm = null;

	file_put_contents($root . "/fmt/sample.fmt", "id,24,N\nname,50,T\nmemo,100,T\n");
	$start_at = microtime(true) + 0.5;
	$child_count = 8;

	for ($i = 0; $i < $child_count; $i++) {
		$pid = pcntl_fork();
		if ($pid === -1) {
			throw new RuntimeException("Failed to fork concurrent format test child.");
		}
		if ($pid === 0) {
			while (microtime(true) < $start_at) {
				usleep(1000);
			}
			$GLOBALS["lock_class_arr"] = [];
			$child_ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");
			$data = $child_ffm->get(1);
			$child_ffm->close();
			exit(is_array($data) && ($data["name"] ?? "") === "before" ? 0 : 1);
		}
		$pids[$pid] = $pid;
	}

	$deadline = microtime(true) + 8.0;
	$failed = [];
	while (count($pids) > 0 && microtime(true) < $deadline) {
		foreach (array_keys($pids) as $pid) {
			$result = pcntl_waitpid($pid, $status, WNOHANG);
			if ($result === 0) {
				continue;
			}
			unset($pids[$pid]);
			if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
				$failed[] = $pid;
			}
		}
		usleep(10000);
	}

	if (count($pids) > 0) {
		ffm_concurrent_format_test_kill_children($pids);
		$pids = [];
		throw new RuntimeException("Concurrent format conversion timed out (possible lock deadlock).");
	}
	if (count($failed) > 0) {
		throw new RuntimeException("Concurrent format conversion child failed.");
	}

	$GLOBALS["lock_class_arr"] = [];
	$ffm = new fixed_file_manager("sample", $root . "/data", $root . "/fmt");
	$data = $ffm->get(1);
	$header = $ffm->get_header_info();
	if (!is_array($data) || ($data["name"] ?? "") !== "before") {
		throw new RuntimeException("Concurrent format conversion did not preserve the existing row.");
	}
	if (strpos((string) ($header["format_txt"] ?? ""), "memo,100,T") === false) {
		throw new RuntimeException("Concurrent format conversion did not apply the new format.");
	}

	echo "fixed file manager concurrent format test passed\n";
} finally {
	if ($ffm instanceof fixed_file_manager) {
		$ffm->close();
	}
	if (count($pids) > 0) {
		ffm_concurrent_format_test_kill_children($pids);
	}
	ffm_concurrent_format_test_delete_directory($root);
}
