<?php

interface Controller {
}

require_once __DIR__ . "/../fbp/app/setting/setting.php";

function setting_write_test_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$reflection = new ReflectionClass(setting::class);
$setting = $reflection->newInstanceWithoutConstructor();
$write = $reflection->getMethod("write_generated_file");
$write->setAccessible(true);

$success_path = tempnam(sys_get_temp_dir(), "fbp-setting-write-");
if ($success_path === false) {
	throw new RuntimeException("Failed to create the setting write test file.");
}

try {
	$write->invoke($setting, $success_path, "generated", ".htaccess");
	setting_write_test_assert(file_get_contents($success_path) === "generated", "Generated file contents were not written.");
} finally {
	@unlink($success_path);
}

$missing_parent_path = sys_get_temp_dir() . "/fbp-setting-write-missing-" . bin2hex(random_bytes(8)) . "/.htaccess";
$exception = null;
try {
	$write->invoke($setting, $missing_parent_path, "generated", ".htaccess");
} catch (RuntimeException $e) {
	$exception = $e;
}

setting_write_test_assert($exception instanceof RuntimeException, "A failed generated-file write did not throw RuntimeException.");
setting_write_test_assert($exception->getMessage() === "Failed to write .htaccess.", "The generated-file exception message is invalid.");

echo "setting generated file write test passed\n";
