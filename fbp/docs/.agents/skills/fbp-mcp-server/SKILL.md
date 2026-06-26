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

## App Action CRUD Pattern

For member-owned service data, prefer a single App Action tool with explicit CRUD operations and owner checks.

- `list`: accepts optional filters such as `limit` and `query`; always filters by the authenticated owner; returns `items` and `count`.
- `create_item`: validates required fields; sets owner fields from `McpActionRequest::subjectId()` or the resolved service member; returns `id` and the created `item`.
- `update_item`: requires `item_id`; verifies ownership before updating; updates only arguments that are present; returns `id` and the updated `item`.
- `delete_item`: requires `item_id` and `confirm=true`; verifies ownership before deleting; returns `id` and a delete summary such as `deleted=true`.

Do not accept ownership fields such as `member_id`, `user_id`, or `parent_id` from MCP clients. Resolve them server-side from the MCP subject.

If a delete affects related data, handle that explicitly in the operation. Either delete child rows, clear references, or reject the delete with a clear `ToolError`; do not leave accidental orphan rows.

## Optional External Enrichment

When an App Action uses an external API to enrich a create or update operation, keep the primary operation separate from the enrichment step.

- If enrichment is optional, do not fail the primary create/update just because the external API failed. Save the primary record and return the enrichment failure in the result.
- If enrichment is required for the operation to be meaningful, validate that requirement up front and fail with a clear `ToolError`.
- Store only normalized fields needed by the app. Do not store raw API responses by default.
- Store enough source metadata to explain or reproduce the enrichment, such as `external_source`, `external_code`, `external_name`, latitude/longitude, or retrieved timestamp.
- Never hard-code API keys, trial keys, endpoint secrets, or production endpoints in Skills, samples, or project docs. Load credentials from app settings, environment variables, or another approved configuration source.

Return enrichment status in a machine-readable shape so MCP clients can decide whether to retry, ask the user, or proceed:

```php
[
	"item" => $item,
	"enrichment" => [
		"status" => "success", // success, skipped, failed
		"source" => "external_service_name",
		"message" => "External data applied.",
		"external_code" => $external_code,
	],
]
```

Use `status=skipped` when required inputs for enrichment were not supplied. Use `status=failed` when inputs were supplied but the external API could not be used. Keep the message concise and safe to show to an MCP client.

## Image and Chart Results

For App Action tools that return generated images, put MCP-displayable images in `data.mcp_content_images`. The MCP server converts each item into a formal MCP image content block:

```php
"mcp_content_images" => [[
	"mime_type" => "image/png",
	"data_base64" => $png_base64,
]],
```

The converted tool result content uses:

```json
{"type":"image","data":"<base64>","mimeType":"image/png"}
```

Use base64 image data without a `data:image/...;base64,` prefix for `data_base64`. Prefer PNG for broad client compatibility. Keep `svg`, `svg_data_uri`, or `png_data_uri` only as structured supplemental data for compatibility, debugging, or reuse; do not rely on data URIs as the primary MCP image display path.

When the same result includes chartable structured data, also return normalized arrays and chart metadata in structured content. A useful pattern is:

- `hourly_heights`: normalized data rows, such as `time` and `height_cm`.
- `chart`: simple app-neutral chart metadata and data.
- `chart_widget_spec`: a widget-ready chart spec when the client supports it.

Do not remove existing SVG/PNG fields when adding structured chart data; add new keys for compatibility.

MCP image content handling and ChatGPT rendering behavior may change. If images or charts stop rendering, verify the current official MCP specification and OpenAI/ChatGPT Apps SDK documentation before adding app-specific workarounds.

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

FBP `date` fields are stored as numeric timestamps in fixed-file data. When an MCP App Action writes to an FBP `date` field, do not store the validated `YYYY-MM-DD` string directly. Convert it to a timestamp for storage, and convert it back to `YYYY-MM-DD` in MCP responses. If `YYYY-MM-DD` is written directly to an `N` field, only the leading year may be retained.

```php
$trip_date = McpInputValidator::date($request, "trip_date");
$row["trip_date"] = strtotime($trip_date . " 00:00:00");

// In MCP response:
$item["trip_date"] = !empty($row["trip_date"]) ? date("Y-m-d", (int) $row["trip_date"]) : "";
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
