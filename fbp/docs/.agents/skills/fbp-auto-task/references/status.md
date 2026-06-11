# Auto-Task Status

## Purpose

Use this reference when investigating or modifying `~/scripts/auto-taskcheckstatus.sh`.

## Flow

1. Lists candidate task IDs from the task API.
2. Downloads each task bundle with `~/scripts/task.sh bundle`.
3. Classifies whether a question, bug, or change task needs status correction.
4. Uses `project_appcode` for locks and local project checks.
5. Posts status/comment updates only through the task API scripts.

## Classification prompts

Prompts should include:

- `appcode`
- task `project_name`
- task `task_name`
- task title/detail
- latest AI comment
- recent history

`task_name` is useful when the same appcode has multiple public areas. It helps classification
avoid confusing unrelated features.

## Status intent

- `change_request`: question task should become a work item.
- `confirmed`: question response is complete.
- `complete`: implementation or bug task can be closed after confirmation.
- `no_change`: leave current status as-is.

Do not classify as complete merely because an AI replied. Look for actual completion, release,
verification, or customer confirmation in history.

## Common diagnostics

```bash
tail -n 120 ~/scripts/tmp/logs/auto-taskcheckstatus.log
sed -n '1,220p' ~/scripts/tmp/auto-taskcheckstatus/task_<id>/prompt.txt
cat ~/scripts/tmp/auto-taskcheckstatus/task_<id>/detail.json
```

## Editing guardrails

- Keep prompts read-only; status checks should not edit app code.
- Do not infer app targets from title when `project_appcode` is missing.
- Run `bash -n ~/scripts/auto-taskcheckstatus.sh` after edits.
