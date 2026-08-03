<?php

class Controller {
	function t(string $key, array $params = []): string {
		return $key;
	}

	function cron_set(): void {
	}
}

require_once __DIR__ . "/../fbp/app/release/ReleaseManager.php";

function release_test_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function release_test_delete_directory(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}
	foreach (array_diff(scandir($dir), [".", ".."]) as $item) {
		$path = $dir . "/" . $item;
		if (is_dir($path)) {
			release_test_delete_directory($path);
		} else {
			unlink($path);
		}
	}
	rmdir($dir);
}

$root = sys_get_temp_dir() . "/release-manager-atomic-" . bin2hex(random_bytes(6));
$zipFile = $root . "/release.zip";

try {
	mkdir($root . "/classes/app/old", 0777, true);
	mkdir($root . "/classes/data/lang", 0777, true);
	mkdir($root . "/classes/data/templates_c", 0777, true);
	file_put_contents($root . "/classes/app/old/obsolete.php", "<?php\n");
	file_put_contents($root . "/classes/data/lang/old.dat", "old");
	file_put_contents($root . "/classes/data/templates_c/cache.php", "cache");

	$zip = new ZipArchive();
	release_test_assert($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, "cannot create test zip");
	$zip->addFromString("app/new/current.php", "<?php\n");
	$zip->addFromString("data/lang/current.dat", "current");
	$zip->addFromString("data/_common/fmt/setting.fmt", "setting");
	$zip->close();

	$manager = new ReleaseManager($root, $zipFile);
	$manager->apply_release_zip(new Controller(), $zipFile);

	release_test_assert(is_file($root . "/classes/app/new/current.php"), "staged app was not deployed");
	release_test_assert(!file_exists($root . "/classes/app/old/obsolete.php"), "old app file remains");
	release_test_assert(is_file($root . "/classes/data/lang/current.dat"), "release data was not deployed");
	release_test_assert(!file_exists($root . "/classes/data/lang/old.dat"), "old release data remains");
	release_test_assert(is_file($root . "/classes/data/_common/fmt/setting.fmt"), "common fmt was not deployed");
	release_test_assert(!is_dir($root . "/classes/data/templates_c"), "template cache was not cleared");
	release_test_assert(glob($root . "/classes/.release_stage_*") === [], "release stage remains");

	printf("release manager atomic deployment test passed\n");
} finally {
	release_test_delete_directory($root);
}
