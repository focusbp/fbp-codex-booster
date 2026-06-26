---
name: fbp-mcp-server
description: Build and maintain FBP MCP Server features, including OAuth authorization, MCP Tool registration, App Action tools, subject provider selection between FBP users and custom public-service users, and MCP-specific login separation.
---

# fbp-mcp-server

Use this skill when adding or changing FBP MCP Server behavior, exposing Note CRUD or App Action tools to MCP clients, or connecting MCP OAuth to a user model other than FBP admin users.

## Core Model

- `mcp_server` handles OAuth and JSON-RPC endpoints.
- `mcp_manage` manages MCP servers, tools, OAuth tokens, and call logs.
- Note CRUD tools are configured records. App Action tools are PHP classes implementing `McpActionInterface`.
- The authenticated actor is a `McpSubject`, not always an FBP `user` row.
- Existing FBP-admin behavior is represented by `subject_type=fbp_user`.
- Public-service or app-specific members use `subject_type=custom` with `subject_provider_class`.

## Subject Interfaces

Use `fbp/interface/McpSubjectInterface.php`.

- `McpSubject` contains `type`, `id`, `label`, and optional FBP `user_id`.
- `McpSubjectProviderInterface` is server-facing. It resolves the current MCP authorization subject, builds the login URL, validates token subjects, formats labels, and receives authorize/revoke hooks.
- `McpLoginHandlerInterface` is service-facing. It keeps MCP login separate from ordinary public-site login, authenticates credentials, manages the MCP session key, and normalizes return URLs.
- `McpFbpUserSubjectProvider` is the default provider for existing FBP admin users.

Keep public-page login and MCP login separate even when they use the same member table. Use separate session keys and separate return URL handling so ChatGPT or another MCP client does not accidentally inherit an ordinary browser login.

## Server Configuration

`mcp_server_config` supports these subject fields:

- `subject_type`: `fbp_user` or `custom`.
- `subject_provider_class`: required when `subject_type=custom`; empty for `fbp_user`.

OAuth-related tables store subject fields for audit and filtering:

- `mcp_oauth_auth_codes.subject_type`
- `mcp_oauth_auth_codes.subject_id`
- `mcp_oauth_auth_codes.subject_label`
- `mcp_oauth_tokens.subject_type`
- `mcp_oauth_tokens.subject_id`
- `mcp_oauth_tokens.subject_label`
- `mcp_call_logs.subject_type`
- `mcp_call_logs.subject_id`
- `mcp_call_logs.subject_label`

Keep `user_id` for backward compatibility and FBP-admin ownership. For custom subjects, set `user_id` only when there is a meaningful related FBP user; otherwise leave it empty.

## Custom Subject Provider Pattern

Create an app class that implements `McpSubjectProviderInterface`.

```php
class fishing_mcp_subject_provider implements McpSubjectProviderInterface {
	public function subjectType(): string {
		return "fishing_member";
	}

	public function currentSubject(Controller $ctl, array $server): ?McpSubject {
		$member_id = (int) ($_SESSION["fishing_mcp_member_id"] ?? 0);
		if ($member_id <= 0) {
			return null;
		}
		$member = $ctl->db("fishing_member", "fishing_member")->get($member_id);
		if (!$member || (int) ($member["deleted"] ?? 0) === 1) {
			return null;
		}
		return new McpSubject($this->subjectType(), (int) $member["id"], (string) $member["name"]);
	}

	public function loginUrl(Controller $ctl, array $server, string $returnUrl): string {
		$_SESSION["fishing_mcp_return_url"] = $returnUrl;
		return $ctl->get_APP_URL("fishing_mcp_login", "login");
	}

	public function subjectLabel(Controller $ctl, McpSubject $subject): string {
		return $subject->label();
	}

	public function validateSubject(Controller $ctl, array $server, McpSubject $subject): bool {
		$member = $ctl->db("fishing_member", "fishing_member")->get($subject->id());
		return (bool) $member && (int) ($member["deleted"] ?? 0) !== 1;
	}

	public function onAuthorizeConfirmed(Controller $ctl, array $server, McpSubject $subject, array $oauthParams, string $scope): void {
	}

	public function onTokenRevoked(Controller $ctl, array $server, McpSubject $subject, array $tokenRow): void {
	}
}
```

Use a separate login class for MCP-specific screens and sessions. If you implement a reusable login handler, implement `McpLoginHandlerInterface` and call it from the login class.

## App Action Tool Usage

`McpActionRequest` exposes the resolved subject:

```php
$subject = $request->subject();
$member_id = $request->subjectId();
$subject_type = $request->subjectType();
```

For custom-member services, every App Action must filter data by `subjectId()` or a service-owned membership relation. Do not trust client-supplied member IDs for ownership.

When a Note CRUD tool is too broad for per-member access control, prefer an App Action tool that applies explicit member filtering.

## Input Validation

For App Action tools, define JSON Schema hints and runtime validation together. JSON Schema helps MCP clients choose the right shape, but runtime validation is still required because clients can send ambiguous strings, memo text, units, or mixed date/time values.

Use `McpInputValidator` from `McpActionInterface.php` for common MCP argument patterns:

```php
"started_at" => McpInputValidator::timeSchema("Start time."),
"trip_date" => McpInputValidator::dateSchema("Trip date."),
"count" => McpInputValidator::integerSchema("Count.", ["minimum" => 0]),
```

```php
$started_at = McpInputValidator::time($request, "started_at");
$trip_date = McpInputValidator::date($request, "trip_date");
$count = McpInputValidator::integer($request, "count", ["default" => 1, "minimum" => 0]);
```

Supported validators:

- `time`: accepts and normalizes `HH:MM`; rejects dates, ranges, and memo text.
- `date`: accepts and normalizes `YYYY-MM-DD`; rejects datetime and relative words.
- `yearMonth`: accepts and normalizes `YYYY-MM`; rejects day values.
- `integer`: accepts integer values or numeric strings without units; supports `minimum`, `maximum`, `default`, and `required`.
- `decimal`: accepts decimal values or numeric strings without units; supports `minimum`, `maximum`, `default`, and `required`.
- `enum`: restricts values and can normalize aliases.
- `string`: handles required strings and optional `maxLength`.

Validation failures throw `ToolError: ...` messages through the normal MCP tool error path. Prefer clear messages that tell the client the exact field and format. Do not silently move invalid values into memo fields; fail fast so the MCP client can retry with corrected arguments.

## CLI Registration

MCP tool registration JSON may include subject server settings:

```json
{
  "server_key": "service_mcp",
  "server_config": {
    "enabled": true,
    "title": "Service MCP Server",
    "auth_mode": "oauth2",
    "subject_type": "custom",
    "subject_provider_class": "service_mcp_subject_provider"
  },
  "tools": []
}
```

Use `subject_type=fbp_user` for existing admin-user MCP servers. Use `custom` only after the provider class exists and implements `McpSubjectProviderInterface`.

## Verification

1. Run PHP lint for the provider/login/action classes.
2. Copy framework/app source to the test environment according to local environment rules.
3. Verify `mcp_server::health`.
4. Render `mcp_manage::page` and confirm the server shows the expected subject type.
5. For custom providers, open the OAuth authorize URL from a logged-out MCP browser session and confirm it redirects to the MCP-specific login page.
6. Complete authorization and confirm `mcp_oauth_tokens` stores the expected `subject_type`, `subject_id`, and `subject_label`.
7. Call at least one tool and confirm `mcp_call_logs` records the same subject.
8. Revoke the token and confirm provider revoke hooks do not throw.

## Related Skills

- Use `fbp-service-user-management` for public member tables, public account creation, password reset, and service subscriptions.
- Use `fbp-public-pages` for MCP-specific login screens when they are implemented as public pages.
- Use `fbp-cli` for `app_call` verification.
- Use `fbp-db` when adding MCP-related or service-member fields.
