---
name: fbp-app-samples
description: Use when building, designing, adding, extracting, or maintaining reusable FBP app-level samples; provides sample authoring rules, sample scope, DB notes, installer patterns, and reusable starter code assets without depending on a live project.
---

# fbp-app-samples

Use this skill when a user asks for an app-level FBP sample, wants to build a similar app from a known pattern, asks to add a reusable sample to Codex Booster, or asks how to structure a LINE-entry mall/ecommerce app.

## Authoring Samples

When creating a new reusable sample from an existing app or feature, read `references/sample-authoring.md` first. Use it to decide sample scope, remove project-specific logic, create assets/references/installers, update the README Make Samples prompt, and verify the result.

## Samples

- **Event Registration**: A no-external-service event registration sample with admin event session management, a participants side panel with add/delete/status actions, a public registration page, and an admin dialog that shows the public registration URL.
  - Read `references/event-registration.md` for flow and implementation scope.
  - Read `references/event-registration-db.md` for note/table structure.
  - Use `assets/event-registration/` for starter code.
  - Prefer `scripts/install_event_registration.php` when creating the default sample in a clean Codex Booster app.
- **Schedule Appointment**: A one-note appointment booking sample with logged-in-user scoped admin weekly slots, a per-user public URL, and a public booking calendar.
  - Read `references/schedule-appointment.md` for flow and implementation scope.
  - Read `references/schedule-appointment-db.md` for note/table structure.
  - Use `assets/schedule-appointment/` for starter code.
  - Prefer `scripts/install_schedule_appointment.php` when creating the default sample in a clean Codex Booster app.
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

1. Decide whether the user wants to use an existing sample or author a new reusable sample.
2. For new samples, read `references/sample-authoring.md` before editing files.
3. For existing samples, identify which sample applies and read its reference file.
4. Copy or adapt only the needed asset files into the target app.
5. Build the DB/note definitions from the DB reference, not from a live project.
6. Remove unused sample features before implementation.
7. Verify with FBP CLI using the target app's web-side `cli.php`.
