# Auto-Task Screenshots

## Purpose

Use this reference for `auto-taskscreenshot.sh`, screenshot manifests, and task evidence images.

## Current rule

Auto-task screenshots are full-page by default. Partial-capture options are ignored even if a
manifest includes them:

- `screenshot.full_page=false`
- `screenshot.clip_to_selector`
- `screenshot.clip_before_selector`
- `screenshot.crop_bottom_px`
- `screenshot.clip`

Use `screenshot.hide_selectors` for intentional fixed/debug element hiding. The standard fixed
footer is hidden unless the manifest asks otherwise.

## Manifest locations

Taskexec looks for:

- `~/scripts/tmp/auto-taskexec/task_<id>/screenshot_manifest.json`
- `~/scripts/tmp/auto-taskexec/task_<id>/screenshot_manifests/<name>.json`

Keep `output_dir` under the task directory, usually:

`~/scripts/tmp/auto-taskexec/task_<id>/screenshots/<name>`

## Validation and capture

```bash
~/scripts/auto-taskscreenshot.sh --validate <manifest.json>
~/scripts/auto-taskscreenshot.sh <manifest.json>
```

Use `fbp-playwright` when manually reproducing or inspecting a screen before writing a manifest.

## Evidence expectations

- UI changes should have screenshot evidence.
- PDF/report changes may need rendered PDF page images or file summaries.
- CSV/download changes should include file facts such as row counts, header, filename, or checksum.
- Backend-only changes should post a task comment summarizing CLI/log/DB verification.

## Editing guardrails

- Keep capture behavior deterministic and non-interactive.
- Do not require the caller to install Playwright ad hoc; use the local wrappers already provided.
- Do not include credentials, cookies, or session storage in manifests or comments.
- Run `bash -n ~/scripts/auto-taskscreenshot.sh` after shell edits.
