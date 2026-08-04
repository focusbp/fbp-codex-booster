<?php

interface Controller {
}

function endsWith(string $haystack, string $needle): bool {
	return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

$controller_file = getenv("FBP_CONTROLLER_FILE");
if ($controller_file === false || $controller_file === "") {
	$controller_file = __DIR__ . "/../fbp/lib/Controller_class.php";
}
require_once $controller_file;

function app_url_test_assert_same(string $expected, string $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException($message . "\nexpected: " . $expected . "\nactual:   " . $actual);
	}
}

$server = $_SERVER;

try {
	$_SERVER["HTTPS"] = "on";
	$_SERVER["HTTP_HOST"] = "example.test";
	$_SERVER["SCRIPT_NAME"] = "/sample/fbp/app.php";

	$reflection = new ReflectionClass(Controller_class::class);
	/** @var Controller_class $ctl */
	$ctl = $reflection->newInstanceWithoutConstructor();

	app_url_test_assert_same(
		"https://example.test/sample/public_pages*register",
		$ctl->get_APP_URL("public_pages", "register"),
		"URL without parameters changed"
	);
	app_url_test_assert_same(
		"https://example.test/sample/public_pages*register?token=a%20b&return=%2Forders%3Fpage%3D1",
		$ctl->get_APP_URL("public_pages", "register", ["token" => "a b", "return" => "/orders?page=1"]),
		"standard query URL is invalid"
	);
	app_url_test_assert_same(
		"https://example.test/sample/practice?t=abc",
		$ctl->get_APP_URL(null, "practice", ["t" => "abc"]),
		"default-class route did not use standard query syntax"
	);
	app_url_test_assert_same(
		"https://example.test/sample/public_pages*register?token=abc&mode=1",
		$ctl->get_APP_URL("public_pages", "register", "token=abc&mode=1"),
		"string parameters did not use standard query syntax"
	);
	app_url_test_assert_same(
		"https://example.test/sample/public_pages*register&token=abc&mode=1",
		$ctl->get_APP_URL(
			"public_pages",
			"register",
			["token" => "abc", "mode" => "1"],
			["query_format" => "legacy"]
		),
		"legacy query format is unavailable"
	);

	printf("controller APP URL test passed\n");
} finally {
	$_SERVER = $server;
}
