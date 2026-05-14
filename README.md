# FBP Codex Booster

> Get Codex building structured PHP business apps in minutes.

FBP Codex Booster is a ready-to-run PHP app base for Codex. It gives Codex a
predictable place to create screens, data flows, business actions, public pages,
cron jobs, webhooks, and verification commands.

It is not another Laravel competitor. Think of it as a booster kit for AI coding:
clone it, run it, start Codex inside it, and ask Codex to build business app
features in a controlled shape.

## Try It Now

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

If port `8000` is already in use:

```bash
php -S 127.0.0.1:8001 router.php
```

## Start Codex

Open another terminal and start Codex from the repository root:

```bash
cd fbp-codex-booster
codex
```

### Prompt To Paste Into Codex

Copy the whole block below and paste it into Codex:

```text
Read README.md and fbp/docs/.agents/skills/fbp-customer-demo/SKILL.md.
Create the default customer management demo.
Use the fixed customer fields from the skill.
Build it in stages: CRUD first, then seed data, then CSV, then PDF.
Verify each stage with the FBP CLI.
```

## Route Convention

FBP uses a simple route shape:

```text
/<class>*<function>
```

Examples:

```text
/login*page
/customers_original_management*page
/public_pages*contact&id=123
```

This keeps generated links predictable for Codex. The local `router.php` handles
these routes when you run the PHP built-in server.

## What Codex Can Build Here

- CRUD screens for internal tools
- Customer, order, task, and workflow management
- Dashboards and admin panels
- Public pages and forms
- Webhooks and cron automation
- Email, PDF, API, LINE, and Square-connected workflows

## Why FBP Codex Booster

Codex is fast, but business apps need structure. FBP gives Codex that structure:
where screens live, how routes work, where data is stored, how actions are called,
and how generated behavior can be checked.

The first goal is simple: make it easy to try Codex-driven business app generation
without setting up Apache, a database server, or a full PHP framework stack.

## License

MIT License.
