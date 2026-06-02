---
name: fbp-dat-backup
description: Create and compare local FBP .dat backups during development, using docs/backup workspaces, dat_backup.sh snapshots, manifest.json metadata, dat_read.sh inspection, and SVN ignore checks for backup files.
---

# fbp-dat-backup

Use this skill when the user asks to back up, compare, inspect, or restore from local FBP `.dat` files during development, especially under a project `docs/backup/` workspace.

## Purpose

Development backups are local comparison snapshots. They help Codex and developers compare data before/after schema or data work without relying on `.fmt` files.

They are not release artifacts, customer-facing docs, or a substitute for production backups.

## Storage policy

Preferred structure:

```text
docs/backup/
  20260602_183000_a1b2c3/
    manifest.json
    common/customers.dat
    db/db.dat
```

- Use `docs/backup/<yyyymmdd_hhmmss>_<shortid>/`.
- Preserve source-relative subpaths when copying from `classes/data/`, e.g. `common/customers.dat`.
- Include only `.dat` files needed for the current task.
- Prefer `manifest.json`, not `manifest.txt`, so tools and Codex can parse it reliably.

## Security constraints

- Treat all `.dat` files as sensitive.
- Do not commit backup `.dat` files.
- Do not write passwords, API keys, URLs, login info, server names, local absolute source paths, or release commands into `manifest.json`.
- Be extra careful with `setting.dat`, `user.dat`, `remember_me.dat`, upload indexes, and any customer/person data.
- Do not take production data backups into `docs/backup/` unless the user explicitly asks and understands the sensitivity.

## SVN policy

Before creating backups, check SVN state from the project root:

```bash
svn info
svn status docs
svn propget svn:ignore docs/backup
svn propget svn:ignore docs
```

Preferred SVN setup:

- `docs/backup/` directory may be versioned as an empty workspace.
- Contents of `docs/backup/` must be ignored with `svn:ignore "*" docs/backup`.
- If `docs/backup/` is not versioned, `docs` may ignore the `backup` directory instead.
- Do not run `svn add`, `svn rm`, `svn propset`, or `svn commit` without user approval.

Useful commands after approval:

```bash
mkdir -p docs/backup
svn add docs/backup
svn propset svn:ignore "*" docs/backup
svn commit docs/backup -m "Add ignored backup workspace"
```

If the directory itself should remain unversioned:

```bash
svn propset svn:ignore "backup" docs
svn commit docs -m "Ignore docs backup directory"
```

If `docs/backup` was accidentally added and should be unversioned:

```bash
svn rm --keep-local docs/backup
svn propset svn:ignore "backup" docs
svn commit docs -m "Ignore docs backup directory"
```

Always verify:

```bash
svn status docs/backup
```

Backup `.dat` files must not appear as addable/unversioned items in normal `svn status`.

## Manifest fields

Use a compact `manifest.json`:

```json
{
  "created_at": "2026-06-02T18:30:00+09:00",
  "purpose": "before db field edit",
  "source": "local_web",
  "files": [
    {
      "backup_path": "common/customers.dat",
      "source_relative": "classes/data/common/customers.dat",
      "size": 12345,
      "sha256": "...",
      "maxid": 33,
      "recordsize": 4472,
      "headersize": 304,
      "record_count": 28
    }
  ]
}
```

Allowed `source` values should stay generic, such as `local_web`, `local_source`, `test_export`, or `manual`. Do not include server names or URLs.

## Create backups

Use `~/scripts/dat_backup.sh` to create snapshot directories and `manifest.json` together:

```bash
~/scripts/dat_backup.sh ~/web/<appcode>/classes/data ~/NetBeansProjects/<appcode>/docs/backup db/db.dat common/customers.dat --purpose "before db edit"
```

To copy all `.dat` files under a data directory:

```bash
~/scripts/dat_backup.sh ~/web/<appcode>/classes/data ~/NetBeansProjects/<appcode>/docs/backup --all --purpose "before major data change"
```

Useful options:

- `--name <snapshot>` fixes the snapshot directory name instead of using the generated timestamp and short id.
- `--source <label>` sets the generic manifest source label, e.g. `local_web` or `manual`.
- `--source-prefix <prefix>` sets the source path prefix recorded in the manifest, usually `classes/data`.

The script copies only `.dat` files, preserves source-relative subpaths, writes `manifest.json`, and prints a JSON summary. Prefer explicit `.dat` paths unless a full snapshot is needed.

Use `~/scripts/dat_manifest.sh` when a snapshot directory already exists and only the manifest needs to be generated or refreshed:

```bash
~/scripts/dat_manifest.sh docs/backup/<snapshot> --purpose "existing snapshot" --source manual
```

## Read and compare

Use `~/scripts/dat_read.sh` for read-only inspection:

```bash
~/scripts/dat_read.sh describe docs/backup/<snapshot>/common/customers.dat
~/scripts/dat_read.sh list docs/backup/<snapshot>/common/customers.dat 20
~/scripts/dat_read.sh get docs/backup/<snapshot>/common/customers.dat 123
```

For current test data, read from the web-side data directory:

```bash
~/scripts/dat_read.sh describe ~/web/<appcode>/classes/data/common/customers.dat
```

Production server backups stored locally, such as `/home/nakama/usb_hd_001/server_backup`, may also be inspected with `dat_read.sh` when needed:

```bash
~/scripts/dat_read.sh describe /home/nakama/usb_hd_001/server_backup/<...>/classes/data/common/customers.dat
~/scripts/dat_read.sh list /home/nakama/usb_hd_001/server_backup/<...>/classes/data/common/customers.dat 20
```

Treat these files as production-derived sensitive data. Prefer reading them in place with `dat_read.sh`; do not copy them into `docs/backup/` unless the user explicitly asks and the comparison requires a local project snapshot.

When comparing, first compare manifest metadata (`sha256`, `record_count`, `maxid`, `recordsize`, `headersize`). Only inspect row contents when metadata suggests a meaningful difference or the user asks for row-level comparison.

Use `~/scripts/dat_compare.sh` for directory-level metadata comparison:

```bash
~/scripts/dat_compare.sh docs/backup/<snapshot> ~/web/<appcode>/classes/data
```

The compare script matches `.dat` files by relative path and reports SHA-256, `maxid`, `record_count`, `recordsize`, `headersize`, missing files, and extra files. It does not print row contents.

Use `~/scripts/dat_diff.sh` for row-level comparison of a specific `.dat` pair:

```bash
~/scripts/dat_diff.sh docs/backup/<snapshot>/common/customers.dat ~/web/<appcode>/classes/data/common/customers.dat --only-changed
```

By default, `dat_diff.sh` reports changed IDs and changed field names only. It does not print row values. Use `--with-values` only when sensitive values are safe to display. Use `--limit <n>` to cap detail output for large diffs.

## Workflow

1. Resolve the target project using the normal NetBeansProjects rules.
2. Confirm whether the data source is local development/test data or production-derived data.
3. Check SVN ignore state before creating `docs/backup/`.
4. Use `~/scripts/dat_backup.sh` to create a timestamped snapshot directory.
5. Copy only the requested `.dat` files unless a full snapshot is needed.
6. Let `dat_backup.sh` generate `manifest.json`; use `dat_manifest.sh` only for existing snapshots.
7. Verify backup files are ignored by SVN.
8. Use `dat_read.sh` for read-only inspection and comparison.

## Restore stance

This skill is mainly for backup and comparison. For restore:

- Never overwrite current `.dat` files without explicit user approval.
- Prefer copying to a temporary file and reading it with `dat_read.sh` first.
- Before any replacement, create a new backup of the current data.
- Do not perform production restore from this workflow unless a separate, explicit restore plan is approved.
