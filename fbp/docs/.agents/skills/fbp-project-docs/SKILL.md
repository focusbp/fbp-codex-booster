---
name: fbp-project-docs
description: Maintain per-project docs for FBP apps, especially customer-support notes, support/change history, and system direction memos that Codex should read before support, code changes, task handling, or release work.
---

# fbp-project-docs

Use this skill when working on an FBP app that has a project-level `docs/` folder, or when asked to add/update project docs.

## Purpose

Project docs are source-side context for AI and developers. They are not runtime code and should not contain environment-specific values.

## Preferred files

For support-heavy apps, prefer this small structure:

- `docs/customer-support.md`: customer response notes, tone, personalization, support handling rules
- `docs/history.md`: important support and system changes that affect future decisions
- `docs/direction.md`: product/system direction, status design, automation policy, future improvements

Do not create broad architecture or operations documents unless the user explicitly asks for them.

## Before work

1. Resolve the target project/appcode using the normal FBP environment rules.
2. If `<source_dir>/docs/` exists, read only the relevant files:
   - customer/support response: `customer-support.md`
   - prior behavior or why something is that way: `history.md`
   - direction or design tradeoff: `direction.md`
3. Treat docs as guidance, not as a substitute for code, database, task, or management API checks.

## During and after work

Update docs when the work changes future behavior or support judgment:

- Add customer-specific response preferences only as practical support notes.
- Add important support rules or recurring questions to `customer-support.md`.
- Add meaningful completed changes to `history.md`.
- Add product or automation direction decisions to `direction.md`.

Keep entries short. Prefer one or two bullets over detailed transcripts.

## What not to write

Do not hard-code environment-dependent or sensitive information in project docs:

- passwords, API keys, secrets
- production/test URLs
- server names
- release commands or release script values
- cron lines, local absolute paths, machine-specific logs

When those details are needed, say that management APIs or environment-specific procedures are the source of truth.

## Release behavior

Project docs are generally source-side reference material. Do not assume they are included in app release artifacts. Do not run a production release just because docs changed unless the user explicitly asks for release testing.
