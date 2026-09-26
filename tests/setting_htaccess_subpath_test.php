<?php

interface Controller {}
require_once __DIR__ . "/../fbp/app/setting/setting.php";

$resolver = new ReflectionMethod(setting::class, "resolve_htaccess_subpath");
$resolver->setAccessible(true);
$template = file_get_contents(__DIR__ . "/../fbp/app/setting/Templates/htaccess.tpl");
$existing = static function (string $prefix) use ($template): string {
	return strtr($template, [
		'{$subpath}' => $prefix, '{$class}' => 'login', '{$function}' => 'page',
		'{$default_class_name}' => '', '{$ssl}' => '',
	]);
};
// Both relative and absolute CLI filesystem paths must preserve deployment URLs.
$cases = [
	["fbp/cli.php", true, $existing("/sample"), null, "/sample"],
	["cli.php", true, $existing("/nested/sample"), null, "/nested/sample"],
	["/srv/apps/sample/fbp/cli.php", true, $existing(""), null, ""],
	["C:\\apps\\sample\\fbp\\cli.php", true, $existing("/sample"), null, "/sample"],
	["fbp/cli.php", true, str_replace("\n", "\r\n", $existing("/sample")), null, "/sample"],
	["fbp/cli.php", true, $existing("fbp"), "/sample", "/sample"],
	["fbp/cli.php", true, "", "/nested/sample/", "/nested/sample"],
	["fbp/cli.php", true, "", "", ""],
	["fbp/cli.php", true, "", "/", ""],
	["/sample/fbp/app.php", false, $existing("fbp"), null, "/sample"],
	["/nested/sample/fbp/app.php", false, "", null, "/nested/sample"],
	["/fbp/app.php", false, $existing("/old"), null, ""],
];
foreach ($cases as [$script, $cli, $contents, $override, $expected]) {
	$actual = $resolver->invoke(null, $script, $cli, $contents, $override);
	if ($actual !== $expected) {
		throw new RuntimeException("Unexpected URL prefix for " . $script);
	}
}
$invalid = [
	["fbp/cli.php", true, $existing("fbp"), null],
	["fbp/cli.php", true, "", null],
	["fbp/cli.php", true, $existing("/a") . $existing("/b"), null],
	["fbp/cli.php", true, "", "fbp"],
	["fbp/cli.php", true, "", "/sample/../other"],
	["fbp/cli.php", true, "", "/sample/./other"],
	["fbp/cli.php", true, "", "/sample\nRewriteRule"],
	["fbp/cli.php", true, "", "https://example.test/sample"],
	["fbp/cli.php", true, "", "//example.test/sample"],
	["fbp/cli.php", false, "", null],
	["/sample/fbp/app.php", false, "", "/other"],
];
foreach ($invalid as $args) {
	$rejected = false;
	try {
		$resolver->invoke(null, ...$args);
	} catch (RuntimeException $e) {
		$rejected = true;
	}
	if (!$rejected) {
		throw new RuntimeException("An ambiguous or invalid URL prefix was accepted.");
	}
}
printf("setting htaccess subpath: %d cases passed\n", count($cases) + count($invalid));
