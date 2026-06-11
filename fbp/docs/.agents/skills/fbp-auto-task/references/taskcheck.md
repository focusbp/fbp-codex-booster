# Auto-Task Check

## Purpose

Use this reference when investigating or modifying `~/scripts/auto-taskcheck.sh`.

## Flow

1. Claims tasks from `task_auto_claim`.
2. Skips tasks without `project_appcode`; do not infer appcode from title.
3. Acquires app lock by `project_appcode`.
4. Ensures the local app project exists.
5. Downloads task bundle with `~/scripts/task.sh bundle`.
6. Resolves project context with `MGMT_API_MODE=production ~/scripts/sftp_api.sh get <appcode>`.
7. Builds a Codex prompt for question answering, approval classification, or taskexec routing.
8. Posts the selected task result through task API.

## Prompt identity rules

The prompt should expose:

- `appcode`
- task `project_name`
- task `task_name`
- `app_management_name` from `sftp_api item.name`
- the `project_context.json` path

Use task `project_name` / `task_name` as appcode-internal scope hints. This matters for
`app-soshikikaikaku`, where one app contains multiple public areas.

## Read-only mode

For question tasks, default to read-only checks. Use source reads, docs, task history, and
minimal management API reads. Do not change code or data unless the task is explicitly routed to
taskexec or the user has requested implementation.

## Common diagnostics

```bash
tail -n 120 ~/scripts/tmp/logs/auto-taskcheck.log
sed -n '1,220p' ~/scripts/tmp/auto-taskcheck/task_<id>/prompt.txt
cat ~/scripts/tmp/auto-taskcheck/task_<id>/project_context.json
cat ~/scripts/tmp/auto-taskcheck/task_<id>/timings.json
```

## Editing guardrails

- Preserve the claim/requeue/fail state machine.
- Keep project resolution based on `project_appcode`.
- Do not add deterministic keyword shortcuts that override explicit Codex classification unless
  the safety case is clear.
- Run `bash -n ~/scripts/auto-taskcheck.sh` after edits.
