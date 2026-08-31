<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/mcp_server.php';

function assert_true(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$reflection = new ReflectionClass(mcp_server::class);
$server = $reflection->newInstanceWithoutConstructor();
$normalize = $reflection->getMethod('normalize_input_schema');

$empty = $normalize->invoke($server, [
	'type' => 'object',
	'properties' => [],
	'additionalProperties' => false,
]);
$emptyJson = json_decode(json_encode($empty, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
assert_true($emptyJson->properties instanceof stdClass, 'Empty properties must serialize as an object.');
assert_true(is_array($emptyJson->required), 'required must serialize as an array.');

$named = $normalize->invoke($server, [
	'type' => 'object',
	'properties' => [
		'limit' => ['type' => 'integer'],
	],
]);
$namedJson = json_decode(json_encode($named, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
assert_true($namedJson->properties instanceof stdClass, 'Named properties must serialize as an object.');
assert_true(isset($namedJson->properties->limit), 'Named properties must be preserved.');

$explicitObject = $normalize->invoke($server, [
	'type' => 'object',
	'properties' => new stdClass(),
]);
$explicitJson = json_decode(json_encode($explicitObject, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
assert_true($explicitJson->properties instanceof stdClass, 'An explicit properties object must be preserved.');

$listDescriptor = $reflection->getMethod('build_function_list_descriptor')->invoke($server, [
	'auth_mode' => 'oauth2',
	'default_scope' => 'mcp.read mcp.write',
]);
$callDescriptor = $reflection->getMethod('build_function_call_descriptor')->invoke($server, [
	'auth_mode' => 'oauth2',
	'default_scope' => 'mcp.read mcp.write',
]);
assert_true($listDescriptor['name'] === 'function_list', 'The public function catalog Tool must have a fixed name.');
assert_true($callDescriptor['name'] === 'function_call', 'The public function dispatcher Tool must have a fixed name.');
assert_true($listDescriptor['inputSchema']['properties'] instanceof stdClass, 'The function_list properties must serialize as an object.');
assert_true($callDescriptor['inputSchema']['required'] === ['function_name', 'arguments'], 'The function_call contract must require a name and arguments.');

echo "schema_normalization_test: OK\n";
