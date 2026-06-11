---
name: fbp-auto-task
description: Operate and maintain the local FBP auto-task workflow. Use when investigating auto-task cron execution, task API acquisition, project/appcode resolution, taskcheck/taskexec/status behavior, screenshot evidence, app locking, release-wait automation, or editing scripts such as auto-taskflow.sh, auto-taskcheck.sh, auto-taskexec.sh, auto-taskcheckstatus.sh, and auto-taskscreenshot.sh.
---

# fbp-auto-task

## Core model

Treat auto-task as the orchestration layer around task API, management API, Codex execution,
evidence capture, and release waiting. It is not the same as FBP app implementation.

Keep these identities separate:

- `project_appcode`: execution, app lock, source/test path, release, and management API target.
- task `project_name` / `task_name`: appcode-internal project, public area, or business feature title.
- `sftp_api item.name`: app-wide management name.

When the values differ, use `project_appcode` for operations and task `project_name` /
`task_name` for narrowing code/docs/routes/templates inside the app.

## Initial checks

1. Read `local-main.md` first and follow scripts SVN rules.
2. For task-specific work, fetch the task bundle first:
   `~/scripts/task.sh <task_id>`.
3. For an app target, resolve app metadata with production management API:
   `MGMT_API_MODE=production ~/scripts/sftp_api.sh get <appcode>`.
4. Inspect logs and state under `~/scripts/tmp/`, but do not commit runtime files.
5. Before editing scripts, check `svn status ~/scripts`; after script edits, commit `~/scripts`.

## Reference routing

- Cron, logs, locks, and overall flow: read `references/operations.md`.
- `auto-taskcheck.sh` prompt/status routing: read `references/taskcheck.md`.
- `auto-taskexec.sh` implementation, evidence, and release-wait flow: read `references/taskexec.md`.
- Screenshots and evidence manifests: read `references/screenshots.md`.
- `auto-taskcheckstatus.sh` completion/status reports: read `references/status.md`.

## Safety rules

- Do not infer an app from project name when `project_appcode` is missing.
- Do not expose API keys, passwords, signed URLs, cookies, or secrets from management API output.
- Do not edit `~/scripts/tmp` artifacts except temporary local diagnostics.
- Use `apply_patch` for manual script and skill edits.
- Keep docs concise; put long operational detail in the referenced files, not in this file.
