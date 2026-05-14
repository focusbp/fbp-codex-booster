---
name: fbp-app-samples
description: Use when building or designing a new FBP app from a complete app-level sample, especially a LINE-entry mall or ecommerce app; provides sample scope, DB notes, and reusable starter code assets without depending on a live project.
---

# fbp-app-samples

Use this skill when a user asks for an app-level FBP sample, wants to build a similar app from a known pattern, or asks how to structure a LINE-entry mall/ecommerce app.

## Samples

- **LINE Bot basic**: A minimal LINE Bot app base with LINE member DB, standard webhook receiver, keyword rules, a public profile page, and LINE member management.
  - Read `references/line-bot-basic.md` for flow and implementation scope.
  - Read `references/line-bot-basic-db.md` for note/table structure.
  - Use `assets/line-bot-basic/` for starter code.
  - Prefer `scripts/install_line_bot_basic.php` when creating the default sample in a clean Codex Booster app.
- **LINE-entry mall**: A LINE-only public mall with member registration, multiple shops, single-shop cart, shop-level shipping fee, Square payment, orders, order items, inquiries, and Original Screen admin pages.
  - Read `references/line-entry-mall.md` for flow and implementation scope.
  - Read `references/line-entry-mall-db.md` for note/table structure.
  - Use `assets/line-entry-mall/` for starter code.

## Rules

- Treat sample assets as reusable starting points, not project-specific truth.
- Do not reference or depend on any live app path when using this sample.
- Keep project-specific integrations out of reusable samples unless explicitly requested.
- For the LINE-entry mall sample, exclude project-specific member classifications, external-app SSO, and project-specific post-order workflows.
- For the LINE Bot basic sample, keep `line_member / userid / line_name / name / member_type` as the fixed base and do not create a custom `getting_member` rule unless the target app explicitly needs compatibility behavior.
- Use normal FBP skills with this sample as needed:
  - `fbp-public-pages` for public LINE-entry pages.
  - `fbp-original-screen` for management screens.
  - `fbp-db` for note/table construction.
  - `fbp-webhook` for LINE Bot webhook rule design.
  - `fbp-square-payment` or `fbp-square-oauth` for Square payment design.

## Workflow

1. Identify which sample applies.
2. Read the relevant reference file.
3. Copy or adapt only the needed asset files into the target app.
4. Build the DB/note definitions from the DB reference, not from a live project.
5. Remove unused sample features before implementation.
6. Verify with FBP CLI using the target app's web-side `cli.php`.
