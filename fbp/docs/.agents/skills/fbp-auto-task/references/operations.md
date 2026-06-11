# Auto-Task Operations

## Purpose

Use this reference for cron, log, lock, and overall auto-task flow investigations.

## Main scripts

- `~/scripts/auto-taskflow.sh`: top-level cron entry that runs the auto-task phases.
- `~/scripts/auto-taskcheck.sh`: claims newly auto-actionable tasks and decides reply/approval/escalation.
- `~/scripts/auto-taskexec.sh`: claims taskexec-wait tasks, runs Codex for implementation, posts results, captures evidence, and releases when ready.
- `~/scripts/auto-taskcheckstatus.sh`: reviews task histories and performs status/report corrections.
- `~/scripts/auto-taskscreenshot.sh`: validates screenshot manifests, captures screenshots, and uploads task evidence.

## Cron checks

Check OS cron before debugging script logic:

```bash
ls -l /etc/cron.d/auto-taskflow
sed -n '1,220p' /etc/cron.d/auto-taskflow
systemctl is-active cron 2>/dev/null || systemctl is-active crond 2>/dev/null || true
```

The file must be root-owned and readable. If cron restarted after a scheduled slot, the next
phase may not run until its next configured time.

## Logs and state

Use the newest relevant files under:

- `~/scripts/tmp/logs/`
- `~/scripts/tmp/auto-taskcheck/task_<id>/`
- `~/scripts/tmp/auto-taskexec/task_<id>/`
- `~/scripts/tmp/auto-taskcheckstatus/task_<id>/`
- `~/scripts/tmp/auto-taskexec-profiles/task_<id>.json`
- `~/scripts/tmp/auto-task-app-locks/`
- `~/scripts/tmp/locks/`

Runtime state is diagnostic only. Do not add it to SVN.

## App locks

App locks are keyed by `project_appcode`, not by project title. If a task is skipped with
`app_lock_busy`, check whether another auto-task phase is processing the same appcode.

## Project identity

`task.sh` output is the task source of truth:

- `project_appcode`: the operational app target.
- `project_name` / `task_name`: project title inside the task system.

`sftp_api get <appcode>` adds environment metadata. Its `name` may be broader than the task
project title, especially in apps with many public features.

Use title values to narrow code search, not to choose the app target.
