---
name: fbp-service-user-management
description: Build FBP service-account user management for quickly turning an app feature into a paid public service. Use when implementing public-side account creation, independent login separate from FBP admin users, password reset, member access control, minimal admin member/plan/subscription/payment screens, or Square card payment/subscription-style activation for serviceized FBP apps such as small SaaS prototypes.
---

# fbp-service-user-management

Use this skill when a feature should become a small public service without mixing public customers into the framework's administrator user table.

## Core Pattern

- Keep service users in `service_member`, not in FBP admin users.
- Keep public auth session keys service-specific, such as `service_member_id`.
- Use `public_service` or an app-specific public class for registration, login, logout, account, password reset, plan selection, and Square callback.
- Store payment state in service-owned tables; do not infer access from Square alone.
- Treat Square payment as activation for the current service period. Add true recurring billing only when explicitly required.
- Keep credentials and Square settings in normal app settings; never store them in sample assets or docs.

## Starter Sample

For a reusable starting point, use the `fbp-app-samples` Service User Management sample:

- Read `../fbp-app-samples/references/service-user-management.md` for flow and scope.
- Read `../fbp-app-samples/references/service-user-management-db.md` for DB fields and Standard Screen add/edit/delete display sets.
- Use `../fbp-app-samples/assets/service-user-management/` for starter public-page code and templates.
- Install into a clean FBP app with `../fbp-app-samples/scripts/install_service_user_management.php`.

## Implementation Workflow

1. Decide the public service entry class and service name.
2. Install or adapt the `service-user-management` sample.
3. Rename table prefixes only if the app already has conflicting `service_` tables.
4. Replace sample copy and plan seed data.
5. Gate the target service feature with an active `service_subscription` check for the current `service_member`.
6. Configure Square credentials in the target app settings before live payment testing.
7. Verify registration, login, password reset token generation, plan selection, Square callback failure handling, and active subscription access.

## Boundaries

- Do not add ecommerce cart, shipping, inventory, order items, coupons, or receipt PDF for this pattern. Use `fbp-app-samples` Web Commerce Basic when selling products.
- Do not use Square OAuth unless each seller/shop needs its own Square account.
- Do not send reset emails unless the target app already has email settings or the user requests email delivery; the sample exposes the reset URL for local verification.
- Prefer Standard Screen admin pages for the minimum package. Build Original Screen admin only when the user needs a custom operator workflow.

## Related Skills

- Use `fbp-app-samples` when installing or editing the reusable sample.
- Use `fbp-square-payment` when changing the Square callback or payment behavior.
- Use `fbp-public-pages` when changing public routing, templates, or login-free pages.
- Use `fbp-db` or `fbp-standard-screen` when changing DB fields or Standard Screen display sets.
