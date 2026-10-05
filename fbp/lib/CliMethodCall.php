<?php

// CLI-only helper invocation; no HTTP route or application lifecycle dispatch.
class CliMethodCall {
    private static function arguments($value, string $name): array {
        if (!is_array($value) || ($value && array_keys($value) !== range(0, count($value) - 1))) {
            throw new InvalidArgumentException($name . " must be a positional JSON array");
        }
        return $value;
    }

    private static function validateArity(ReflectionFunctionAbstract $method, array $args): void {
        if (count($args) < $method->getNumberOfRequiredParameters() ||
            (!$method->isVariadic() && count($args) > $method->getNumberOfParameters())) {
            throw new InvalidArgumentException("Argument count mismatch for " . $method->getName());
        }
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                throw new InvalidArgumentException("By-reference parameters are unsupported; use app_call or an existing test");
            }
        }
    }

    public static function execute(array $data, callable $loadClass): array {
        $out = ["ok" => false, "command" => "method_call", "status" => "ERROR", "checks" => []];
        $level = ob_get_level();
        ob_start();
        $phase = "input";
        try {
            $allowed = ["class", "function", "args", "constructor_args", "without_constructor", "expect"];
            if (array_diff(array_keys($data), $allowed)) {
                throw new InvalidArgumentException("Unknown method_call option");
            }
            foreach (["class", "function"] as $key) {
                if (!isset($data[$key]) || !is_string($data[$key]) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $data[$key])) {
                    throw new InvalidArgumentException($key . " must be a class/method identifier");
                }
            }
            $class = $data["class"];
            $function = $data["function"];
            $out["class"] = $class;
            $out["function"] = $function;
            $args = self::arguments(array_key_exists("args", $data) ? $data["args"] : [], "args");
            $constructorArgs = self::arguments(array_key_exists("constructor_args", $data) ? $data["constructor_args"] : [], "constructor_args");
            $skipConstructor = array_key_exists("without_constructor", $data) ? $data["without_constructor"] : false;
            if (!is_bool($skipConstructor) || ($skipConstructor && $constructorArgs)) {
                throw new InvalidArgumentException("without_constructor must be boolean and cannot be combined with constructor_args");
            }
            $phase = "load";
            $loadClass($class);
            $reflection = new ReflectionClass($class);
            $method = $reflection->getMethod($function);
            if ($method->isAbstract() || $method->isConstructor() || $method->isDestructor()) {
                throw new InvalidArgumentException("Target must be a concrete ordinary method");
            }
            self::validateArity($method, $args);
            $object = null;
            $phase = "construct";
            if ($method->isStatic()) {
                if ($constructorArgs || $skipConstructor) {
                    throw new InvalidArgumentException("Static methods do not use constructor options");
                }
            } elseif ($skipConstructor) {
                $object = $reflection->newInstanceWithoutConstructor();
            } else {
                $constructor = $reflection->getConstructor();
                if ($constructor) {
                    self::validateArity($constructor, $constructorArgs);
                } elseif ($constructorArgs) {
                    throw new InvalidArgumentException("Class has no constructor");
                }
                $object = $reflection->newInstanceArgs($constructorArgs);
            }
            $method->setAccessible(true);
            $phase = "invoke";
            $value = $method->invokeArgs($object, $args);
            unset($object);
            $phase = "serialize";
            // JSON objects supplied as arguments/expectations use PHP associative arrays.
            // Reject non-JSON return values rather than accidentally reporting a pass.
            self::validateValue($value);
            $out["return"] = $value;
            $out["return_type"] = gettype($value);
            $out["visibility"] = $method->isPrivate() ? "private" : ($method->isProtected() ? "protected" : "public");
            $out["static"] = $method->isStatic();
            $out["constructor"] = $method->isStatic() ? "not_used" : ($skipConstructor ? "skipped" : "normal");
            $hasExpected = array_key_exists("expect", $data);
            $passed = !$hasExpected || $value === $data["expect"];
            $out["ok"] = $passed;
            $out["status"] = $hasExpected ? ($passed ? "PASS" : "FAIL") : "EXECUTED";
            if ($hasExpected) {
                $out["checks"][] = ["label" => "return", "ok" => $passed, "reason" => $passed ? "" : "equals mismatch", "expected" => $data["expect"]];
            }
        } catch (Throwable $e) {
            $out["error"] = ["phase" => $phase, "type" => get_class($e), "message" => $e->getMessage()];
        } finally {
            $output = "";
            while (ob_get_level() > $level) {
                $output = ob_get_clean() . $output;
            }
            $out["output"] = json_encode($output) === false ? base64_encode($output) : $output;
            $out["output_encoding"] = json_encode($output) === false ? "base64" : "utf-8";
        }
        return $out;
    }

    private static function validateValue($value, int $depth = 0): void {
        if ($depth > 128 || is_object($value) || is_resource($value) || (is_float($value) && !is_finite($value))) {
            throw new UnexpectedValueException("Return value must be JSON-compatible scalars/arrays/null (depth <= 128)");
        }
        if (is_array($value)) {
            foreach ($value as $item) { self::validateValue($item, $depth + 1); }
        }
        if (json_encode($value) === false) {
            throw new UnexpectedValueException("Return value cannot be encoded as JSON");
        }
    }
}
