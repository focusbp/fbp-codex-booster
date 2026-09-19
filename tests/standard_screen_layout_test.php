<?php

// Exercise old setting data against the new fixed format, without app data.
class Controller_class {
	public static function getInstance() { return null; }
}

$framework = getenv("FBP_FRAMEWORK_ROOT") ?: __DIR__ . "/../fbp";
require_once $framework . "/lib/StandardScreenLayout.php";
require_once $framework . "/lib/fixed_file_manager/fixed_file_manager.php";

function layout_assert($condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}

foreach ([null, "", 0, "0", 2, "11", [], true, false, 1.0] as $value) {
	layout_assert(fbp_normalize_standard_screen_responsive($value) === 0, "Invalid/missing settings must remain responsive");
}
foreach ([1, "1"] as $value) {
	layout_assert(fbp_normalize_standard_screen_responsive($value) === 1, "Desktop setting must be recognized");
}

$root = __DIR__ . "/tmp-layout-" . bin2hex(random_bytes(6));
$ffm = null;
try {
	mkdir($root . "/data", 0700, true);
	mkdir($root . "/fmt", 0700, true);
	$format = file_get_contents($framework . "/app/setting/fmt/setting.fmt");
	layout_assert(substr_count($format, "standard_screen_responsive,1,N\n") === 1, "Setting must have exactly one numeric field");
	file_put_contents($root . "/fmt/setting.fmt", str_replace("standard_screen_responsive,1,N\n", "", $format));
	$ffm = new fixed_file_manager("setting", $root . "/data", $root . "/fmt");
	$row = ["system_name" => "既存設定", "viewport_base" => "width=1024", "viewport_public" => "width=600", "timezone" => "Asia/Tokyo", "app_url_protocol" => 1];
	$ffm->insert($row);
	$before = $ffm->get($row["id"]);
	$ffm->close();
	file_put_contents($root . "/fmt/setting.fmt", $format);
	$ffm = new fixed_file_manager("setting", $root . "/data", $root . "/fmt");
	$after = $ffm->get($row["id"]);
	foreach ($before as $key => $value) {
		layout_assert($after[$key] === $value, "Format migration changed existing setting: " . $key);
	}
	layout_assert(fbp_normalize_standard_screen_responsive($after["standard_screen_responsive"]) === 0, "Migrated data must default to responsive");
	foreach ([1, 0] as $mode) {
		$after["standard_screen_responsive"] = $mode;
		$ffm->update($after);
		$ffm->close();
		$ffm = new fixed_file_manager("setting", $root . "/data", $root . "/fmt");
		$after = $ffm->get($row["id"]);
		layout_assert(fbp_normalize_standard_screen_responsive($after["standard_screen_responsive"]) === $mode, "Saved mode did not survive reopen");
		foreach ($before as $key => $value) {
			layout_assert($after[$key] === $value, "Mode update changed existing setting: " . $key);
		}
	}
	echo "PASS: mode defaults, invalid values, old-format migration, existing settings preserved, both modes persist\n";
} finally {
	if ($ffm) { $ffm->close(); }
	if (is_dir($root)) {
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($root);
	}
}
