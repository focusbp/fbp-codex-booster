# MCP Service App Sample

## Purpose

Reusable FBP sample for a public-member service exposed to MCP clients. It provides a custom MCP OAuth subject, an MCP-specific login flow, and a member-owned App Action tool.

This sample is intentionally smaller than a normal public portal. The public side only creates an account and shows the MCP endpoint URL after login. It does not include a dashboard or aggregate counters.

## Included

- Public account creation for `service_member`.
- Minimal public account page that shows the MCP endpoint URL.
- MCP-specific login page and session separate from the public account session.
- `McpSubjectProviderInterface` implementation using `subject_type=service_member`.
- `McpLoginHandlerInterface` implementation for email/password MCP login.
- `mcp_service_action` App Action tool with owner-scoped `list/create_item/update_item/delete_item`.
- MCP server registration using `subject_type=custom`.
- Standard Screen admin management for members and items.

## Excluded

- Dashboard metrics, counters, charts, or public item management UI.
- Payment, subscription, ecommerce, Square, email delivery, password reset, team accounts, SSO, invitations, and roles.
- External API integration.
- Credentials, domains, production data, or live project paths.

## Public Flow

1. Visitor opens `/public_mcp_service*register`.
2. Visitor creates an account.
3. If registration came from MCP login via `?from=mcp`, the user is redirected to `/public_mcp_service_login*login`.
4. Otherwise the user is redirected to `/public_mcp_service*portal`.
5. The portal shows only the MCP endpoint URL and logout.

## MCP OAuth Flow

1. MCP client connects to the `mcp_service` server.
2. `mcp_server` uses `mcp_service_subject_provider` because the server is configured with `subject_type=custom`.
3. The provider stores the OAuth return URL in a dedicated MCP return session value and redirects to `public_mcp_service_login`.
4. The login handler validates email/password against `service_member`.
5. Successful MCP login stores only the MCP session key and redirects back to the OAuth authorize URL.
6. App Action tools resolve the authenticated member from `McpActionRequest::subjectId()`.

## Starter Code

- `assets/mcp-service-app/classes/app/public_mcp_service/public_mcp_service.php`
- `assets/mcp-service-app/classes/app/public_mcp_service/Templates/page.tpl`
- `assets/mcp-service-app/classes/app/public_mcp_service/Templates/_head.tpl`
- `assets/mcp-service-app/classes/app/public_mcp_service_login/public_mcp_service_login.php`
- `assets/mcp-service-app/classes/app/public_mcp_service_login/Templates/login.tpl`
- `assets/mcp-service-app/classes/app/public_mcp_service_login/Templates/_head.tpl`
- `assets/mcp-service-app/classes/app/mcp_service_login_handler/mcp_service_login_handler.php`
- `assets/mcp-service-app/classes/app/mcp_service_subject_provider/mcp_service_subject_provider.php`
- `assets/mcp-service-app/classes/app/mcp_service_action/mcp_service_action.php`

Install with:

```bash
php fbp/docs/.agents/skills/fbp-app-samples/scripts/install_mcp_service_app.php
```

## Implementation Notes

- Keep public login and MCP login sessions separate even when both use `service_member`.
- Do not expose Note CRUD directly for member-owned data unless the server/tool layer enforces member ownership. This sample uses App Action for explicit filtering.
- Do not trust client-supplied member IDs. Use `McpActionRequest::subjectId()` and verify row ownership before update/delete.
- Keep the portal small. For most MCP-first services, endpoint URL display is enough.
- Add external API enrichment, images, or `chart_widget_spec` only in domain-specific samples.
