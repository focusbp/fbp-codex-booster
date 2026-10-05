<?php
// Run from the test environment against the synced runtime library.
require $argv[1];
class CliMethodFixture {
    public $prefix;
    public static $calls = 0;
    public function __construct($prefix = "x") { $this->prefix = $prefix; }
    private function hidden($value) { self::$calls++; echo "captured"; return $this->prefix . $value; }
    protected function inherited($value = 7) { return $value; }
    public static function total(...$values) { return array_sum($values); }
    public static function identity($value) { return $value; }
    public function fails() { echo "before-error"; throw new RuntimeException("fixture failure"); }
    public function reference(&$value) { return $value; }
    public function objectValue() { return new stdClass(); }
    public function binaryOutput() { echo "\xff"; return null; }
}
class CliMethodChild extends CliMethodFixture {}
class CliMethodNeedsConstructor {
    public function __construct($value) {}
    private function pure($value) { return $value; }
}
$count = 0;
function check($condition, $name) {
    global $count;
    if (!$condition) { throw new RuntimeException("FAIL: " . $name); }
    $count++;
}
function callMethod($function, $extra = [], $class = "CliMethodFixture") {
    return CliMethodCall::execute(array_merge(["class" => $class, "function" => $function], $extra), function ($name) {
        if (!class_exists($name, false)) { throw new RuntimeException("missing fixture"); }
    });
}
$r = callMethod("hidden", ["args" => ["y"], "constructor_args" => ["pre"], "expect" => "prey"]);
check($r["status"] === "PASS" && $r["return"] === "prey" && $r["output"] === "captured" && CliMethodFixture::$calls === 1, "private/constructor/output/exactly once");
check(callMethod("inherited", [], "CliMethodChild")["return"] === 7, "inherited protected/default args");
check(callMethod("total", ["args" => [1,2,3], "expect" => 6])["status"] === "PASS", "static variadic");
check(callMethod("identity", ["args" => [1], "expect" => "1"])["status"] === "FAIL", "strict mismatch");
check(callMethod("identity", ["args" => [null], "expect" => null])["status"] === "PASS", "explicit null expectation");
check(callMethod("identity", ["args" => [false]])["status"] === "EXECUTED", "false is a return value, not execution failure");
check(callMethod("identity", ["args" => [["a" => [1,2]]], "expect" => ["a" => [1,2]]])["status"] === "PASS", "nested arrays");
$r = callMethod("fails");
check($r["status"] === "ERROR" && $r["output"] === "before-error" && $r["error"]["phase"] === "invoke", "exception with output");
check(callMethod("pure", ["args" => [3]], "CliMethodNeedsConstructor")["error"]["phase"] === "construct", "constructor not silently skipped");
check(callMethod("pure", ["args" => [3], "without_constructor" => true], "CliMethodNeedsConstructor")["return"] === 3, "explicit constructor skip");
foreach ([
    ["identity", []], ["identity", ["args" => [1,2]]],
    ["identity", ["args" => null]], ["identity", ["args" => ["named" => 1]]],
    ["identity", ["unknown" => 1]], ["reference", ["args" => [1]]],
    ["missing", []], ["__construct", []], ["objectValue", []],
    ["total", ["constructor_args" => [1]]], ["hidden", ["without_constructor" => "true"]],
    ["hidden", ["args" => [1], "without_constructor" => true, "constructor_args" => [1]]],
    ["../invalid", []],
] as $case) {
    check(callMethod($case[0], $case[1])["ok"] === false, "invalid input: " . $case[0]);
}
$r = callMethod("binaryOutput");
check($r["ok"] && $r["output_encoding"] === "base64" && base64_decode($r["output"]) === "\xff", "binary output remains JSON compatible");
echo "PASS: " . $count . " method-call checks\n";
