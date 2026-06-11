# Auto-Task Exec

## Purpose

Use this reference when investigating or modifying `~/scripts/auto-taskexec.sh`.

## Flow

1. Runs release-ready checks for apps whose open tasks are all release-wait.
2. Claims taskexec-wait tasks from task API.
3. Requires `project_appcode`; if missing, fail rather than guessing.
4. Acquires app lock by appcode and ensures local project exists.
5. Loads or creates an execution profile from `~/scripts/tmp/auto-taskexec-profiles/`.
6. Builds a Codex prompt and runs `codex_completion.sh`.
7. Parses escalation control lines.
8. Posts task result and runs screenshot manifests if present.
9. Requeues once with stronger profile when the task requests escalation and policy allows.

## Scope fields

`reasoning_effort` and `scope` steer how much Codex should inspect:

- `evidence_only`: verify and capture evidence without code/data/release changes.
- `narrow_change`: implement a clear localized change.
- `broad_change`: allow broader DB/API/permission/framework investigation when needed.

## Project title scoping

The generated prompt should tell Codex to read `task.sh <task_id>` first. Treat task
`project_name` / `task_name` as appcode-internal area names and use them before broad `rg`.

This is especially important when:

- one app hosts multiple public sites or AI flows;
- `sftp_api item.name` is only the app-wide name;
- route/class/template names are not obvious from task title alone.

## Evidence and release

For system change and bug tasks, require task evidence:

- Screenshot evidence for UI-visible changes.
- CLI/log/file summaries for backend-only changes.
- Explanation when screenshot evidence is not possible.

Release-ready tasks are released by appcode. The release script is resolved from `sftp_api`; do
not hard-code production commands into docs or prompts.

## Common diagnostics

```bash
tail -n 120 ~/scripts/tmp/logs/auto-taskexec.log
sed -n '1,260p' ~/scripts/tmp/auto-taskexec/task_<id>/prompt.txt
cat ~/scripts/tmp/auto-taskexec/task_<id>/exec_profile.json
cat ~/scripts/tmp/auto-taskexec/task_<id>/codex_control.json
cat ~/scripts/tmp/auto-taskexec/task_<id>/timings.json
```

## Editing guardrails

- Preserve app locking and requeue behavior.
- Keep final task-history messages short and customer-safe.
- Do not print secrets from task, db, or sftp API output.
- Run `bash -n ~/scripts/auto-taskexec.sh` after edits.
