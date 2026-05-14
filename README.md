# FBP Codex Booster

> A PHP booster kit that helps Codex generate structured, verifiable business apps.

FBP Codex Booster gives Codex a ready-to-run business app surface: routing,
screen structure, data helpers, reusable app patterns, and CLI checks.

It is not trying to be another Laravel competitor. The goal is different:
give Codex enough structure to build practical business tools without starting
from a blank PHP project.

## Quick Start

Requirements:

- PHP 8+
- Git

Run:

```bash
git clone https://github.com/focusbp/fbp-codex-booster.git
cd fbp-codex-booster
php -S 127.0.0.1:8000 router.php
```

Open:

```text
http://127.0.0.1:8000/
```

You should see the FBP login screen.

Try the FBP route format:

```text
http://127.0.0.1:8000/login*page
```

If port `8000` is already in use:

```bash
php -S 127.0.0.1:8001 router.php
```

Stop the local server with `Ctrl-C`.

## What This Gives Codex

Codex works better when the app has predictable boundaries. FBP Codex Booster
provides those boundaries for common business systems.

- A PHP runtime for business app screens and actions
- A route format Codex can generate consistently: `/class*function`
- Reusable CRUD, dashboard, public page, webhook, cron, email, PDF, and API patterns
- File-based app data and configuration for easy local trials
- CLI commands for checking generated behavior
- Agent-oriented docs and skills under `fbp/docs/.agents/skills/`

## Why It Exists

AI can write code quickly, but business apps need more than code snippets.
They need stable places for screens, data, validation, actions, uploads,
scheduled jobs, public forms, and verification.

FBP Codex Booster is the structure around that work. It helps Codex produce
apps that are easier to inspect, run, and fix.

## Not A Laravel Replacement

Use Laravel when you want a full general-purpose PHP application stack.

Use FBP Codex Booster when you want Codex to generate business app features
inside a constrained, repeatable shape.

Good fits:

- Internal CRUD tools
- Customer or order management
- Admin dashboards
- Public forms and landing workflows
- Webhook receivers
- Cron-driven automation
- LINE, email, PDF, Square, or API-connected workflows

## Local Routing

Apache is optional for local trials.

The included `router.php` lets PHP's built-in web server absorb the main FBP
rewrite routes:

```text
/login*page                 -> fbp/app.php?class=login&function=page
/orders*detail&id=1         -> fbp/app.php?class=orders&function=detail&id=1
/css/appstyle.css           -> fbp/css/appstyle.css
/js/function.js             -> fbp/js/function.js
/images/example.png         -> classes/app/images/example.png
```

Protected internals such as `fbp/lib`, `fbp/app`, `fbp/interface`,
`fbp/Templates`, `fbp/lib_ext`, `fbp/docs`, `fbp/cli.php`, and `classes/data`
return `403` through the built-in server router.

The PHP built-in web server is for local development and demos only. For
production, use a real web server and appropriate deployment settings.

## Smoke Tests

With the local server running:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/
curl -s -o /dev/null -w "%{http_code}\n" 'http://127.0.0.1:8000/login*page'
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/fbp/lib/Controller_class.php
```

Expected:

```text
200
200
403
```

You can also call the app through the CLI:

```bash
php fbp/cli.php app_call --json='{"class":"login","function":"page"}'
```

## Use With Codex

After cloning the repo, ask Codex to read the relevant FBP skill docs before it
changes code. For example:

```text
Read README.md and fbp/docs/.agents/skills/fbp-original-screen/SKILL.md.
Create a customer management screen using the existing FBP patterns.
Verify the result with php fbp/cli.php app_call.
```

For public pages:

```text
Read fbp/docs/.agents/skills/fbp-public-pages/SKILL.md.
Create a public contact form with validation and a completion page.
Use FBP helpers for form rendering and verify the route locally.
```

For scheduled jobs:

```text
Read fbp/docs/.agents/skills/fbp-cron/SKILL.md.
Add a cron task that processes pending records and verify it with the FBP CLI.
```

## Repository Layout

```text
router.php                 PHP built-in server router
fbp/app.php                Main web entry point
fbp/cli.php                CLI verification and app operation entry point
fbp/app/                   Built-in FBP app classes
fbp/lib/                   Core libraries
fbp/Templates/             Shared templates
fbp/docs/.agents/skills/   Codex-oriented implementation guides
classes/app/               Project-specific app classes
classes/data/              Local app data and configuration
```

## Route Convention

FBP's normal route shape is:

```text
/<class>*<function>
```

Examples:

```text
/login*page
/customers_original_management*page
/public_pages*contact&id=123
```

Inside the app, those routes are handled as:

```text
class    = login
function = page
```

This convention is intentionally simple so Codex can generate and verify links
without guessing a routing DSL.

## Current Status

This repository is an early public packaging of FBP for Codex-based development.
The local trial path works with PHP's built-in server, and Apache remains
supported for existing deployments.

The next focus areas are:

- Cleaner first-run setup
- More complete sample apps
- Short demo prompts for Codex
- More verification examples for generated screens and workflows

## License

MIT License.
