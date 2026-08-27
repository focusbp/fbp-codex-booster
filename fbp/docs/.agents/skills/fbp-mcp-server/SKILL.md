---
name: fbp-mcp-server
description: Build and maintain the single MCP Server and function registry for an FBP app, including tools/list, tools/call, OAuth subjects, mcp_functions, and deterministic MCP function classes.
---

# fbp-mcp-server

Use this Skill for new or migrated FBP MCP features. One FBP app exposes one MCP Server endpoint and any number of registered functions.

## Canonical Model

- Each project/app has exactly one MCP Server.
- Publish the endpoint without a server selector: `mcp_server*rpc`.
- Use the MCP standard methods `tools/list` and `tools/call`. Do not invent protocol methods such as `list_functions` or `execute_function`.
- Store registry metadata in `mcp_functions`.
- Put input/output schema and execution logic in the function class.
- Derive the PHP class from the function name. Do not register an editable class name.

Legacy `mcp_tools`, `mcp_tool_fields`, Note CRUD, App Action, multiple `mcp_server_config` rows, and `?server=<server_key>` URLs are migration compatibility only. Do not use them for new implementations.

## Function and Class Naming

The function name must match:

```text
^[a-z][a-z0-9_]*$
```

The framework deterministically derives:

```text
function: task_list
class:    mcp_task_list
file:     classes/app/mcp_task_list/mcp_task_list.php
```

`McpFunctionLoader` validates and resolves this rule at registration and execution time. Never add an `action_class`-style override.

## Function Contract

Implement `McpFunctionInterface` from `fbp/interface/McpFunctionInterface.php`.

```php
class mcp_task_list implements McpFunctionInterface {
	public function getInputSchema(Controller $ctl, array $function): array {
		return [
			"type" => "object",
			"properties" => [
				"limit" => McpInputValidator::integerSchema("Maximum rows.", [
					"minimum" => 1,
					"maximum" => 100,
				]),
			],
			"additionalProperties" => false,
		];
	}

	public function getOutputSchema(Controller $ctl, array $function): array {
		return ["type" => "object", "additionalProperties" => true];
	}

	public function execute(Controller $ctl, McpFunctionRequest $request): McpActionResult {
		$limit = McpInputValidator::integer($request, "limit", [
			"default" => 20,
			"minimum" => 1,
			"maximum" => 100,
		]);
		return McpFunctionResult::success("Tasks retrieved.", [
			"items" => [],
			"count" => 0,
		]);
	}
}
```

Use JSON Schema and runtime validation together. Prefer `McpInputValidator`. For FBP numeric date fields, convert validated dates to timestamps before storage and format them back for responses.

### PHP to JSON Schema Serialization

Validate the JSON representation, not only the PHP array. PHP encodes an empty array as `[]`, but JSON Schema keywords such as `properties`, `$defs`, `patternProperties`, and `dependentSchemas` require JSON objects. A function class may use an empty PHP array as its internal empty property map:

```php
return [
	"type" => "object",
	"properties" => [],
	"additionalProperties" => false,
];
```

The MCP Server owns the JSON boundary. Its schema normalization must serialize an empty property map as `{}` and must not turn an explicitly supplied object back into `[]`. Fix this centrally in the framework; do not add per-app casts as the primary solution. Keep array-valued keywords such as `required`, `oneOf`, `anyOf`, and `allOf` as JSON arrays.

Framework regression coverage for schema normalization must include both a no-argument function and a function with named arguments, and assert their serialized JSON types rather than comparing only PHP values.

## Registry

`mcp_functions` stores:

- `enabled`
- `function_name`
- `title`
- `description`
- `required_scope`
- `requires_confirmation`
- `read_only`
- `destructive`
- `sort`
- optional JSON `handler_config`
- timestamps

Do not store input/output schemas or PHP class names in the registry.

Register through the web-side CLI after syncing the class:

```bash
php fbp/cli.php mcp_function_apply --json-file spec.json
```

Dry-run is the default. Set top-level `"dry_run": false` only when applying an authorized change.

```json
{
  "functions": [
    {
      "function_name": "task_list",
      "title": "List tasks",
      "description": "List or search tasks.",
      "required_scope": "mcp.read",
      "read_only": true
    }
  ]
}
```

## OAuth, Subject, and Ownership

- `mcp_server_config` is a singleton app setting. Keep `server_key=default` internally for compatibility; do not expose it in new URLs or UI.
- `subject_type=fbp_user` uses `McpFbpUserSubjectProvider`.
- `subject_type=custom` requires a class implementing `McpSubjectProviderInterface`.
- Keep MCP login sessions separate from normal public-page login sessions.
- Use `McpFunctionRequest::subjectId()` and subject-owned relations for authorization.
- Never trust client-supplied owner IDs when ownership can be resolved from the subject.
- Apply `required_scope` before execution and log the resolved subject.
- Use `requires_confirmation` for operations that need explicit confirmation and set `destructive` accurately.

## Results

Return `McpFunctionResult::success()` for ordinary results. `McpActionResult` remains an allowed return type only for migration adapters.

For images, put normalized content in `data.mcp_content_images` with `mime_type` and base64 data. The MCP Server converts it to MCP image content.

## Verification

1. Lint each function class and framework MCP file.
2. Sync framework/app source according to the local environment rules.
3. Run `mcp_function_apply` in dry-run mode, then apply.
4. Render `mcp_manage::page`; confirm there is one server and every function is `ready`.
5. Call `tools/list`; verify names, input schemas, output schemas, annotations, and scopes.
6. Inspect the serialized JSON for every returned tool. Object-valued schema keywords must encode as `{}`, never `[]`; in particular, every `inputSchema.properties` must have JSON type `object`. Do not stop after validating the first tool because one invalid descriptor can make the client reject the complete tool list.
7. Call at least one read function and one safe write function through OAuth when available.
8. Confirm `mcp_call_logs` records function name and subject.
9. Verify token revocation and custom subject hooks when those paths changed.

For local read-only handler checks, `mcp_server::cli_function_check` is guarded by `CLI_APP_CALL` and may be invoked through `fbp-cli`.

## Migration Only

During migration, the runtime may read legacy tools only when `mcp_functions` has no rows. This fallback prevents an unmigrated app from losing its MCP immediately.

For each migrated app:

1. Create deterministic `mcp_<function_name>` classes.
2. Register all functions in `mcp_functions`.
3. Verify the single endpoint and OAuth reconnection.
4. Stop publishing server-key URLs.
5. Back up and then remove obsolete server/tool rows only after confirming no active client depends on them.

After all apps migrate, remove the legacy registry, multi-server routing, adapters, CLI/scripts, old samples, docs, and Skills. Do not leave old and new approaches as equal choices.

## Related Skills

- Use `fbp-cli` for app and handler checks.
- Use `fbp-db` for registry format/lifecycle work.
- Use `fbp-service-user-management` for custom service users.
- Use `fbp-public-pages` for MCP-specific custom login screens.
