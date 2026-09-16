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
$session = $_SESSION ?? [];

try {
	$_SERVER["HTTPS"] = "on";
	$_SERVER["HTTP_HOST"] = "example.test";
	$_SERVER["SCRIPT_NAME"] = "/sample/fbp/app.php";

	$reflection = new ReflectionClass(Controller_class::class);
	/** @var Controller_class $ctl */
	$ctl = $reflection->newInstanceWithoutConstructor();
	$ctl->set_windowcode("app_url_test");
	$_SESSION["app_url_test"]["setting"] = [];

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

	foreach ([null, "", "0", "1", "2", "9"] as $protocol) {
		foreach (["off", "on"] as $https) {
			$_SERVER["HTTPS"] = $https;
			$_SESSION["app_url_test"]["setting"] = $protocol === null ? [] : ["app_url_protocol" => $protocol];
			$scheme = $protocol === "1" ? "https" : ($protocol === "2" ? "http" : ($https === "on" ? "https" : "http"));
			app_url_test_assert_same($scheme . "://example.test/sample/public_pages*register?token=a%20b", $ctl->get_APP_URL("public_pages", "register", ["token" => "a b"]), "protocol override failed");
		}
	}
	printf("controller APP URL test passed\n");
} finally {
	$_SERVER = $server;
	$_SESSION = $session;
}
