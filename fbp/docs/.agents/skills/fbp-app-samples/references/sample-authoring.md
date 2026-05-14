# Sample Authoring

Use this reference when adding a reusable app-level sample to FBP Codex Booster or extracting one from an existing project.

## Goal

Create a small, reusable sample that Codex can install or adapt in a clean FBP app without depending on any live project path, customer-specific workflow, local environment, private credentials, or production data.

## Scope Rules

- Start from the user-visible use case, not from the full source app.
- Keep the minimum complete workflow that proves the sample pattern.
- Remove customer-specific business rules, identifiers, statuses, text, permissions, credentials, endpoints, local paths, and one-off compatibility code.
- Keep integration stubs only when they teach the reusable pattern.
- Prefer Original Screen for management screens unless the sample is specifically about legacy Standard Screen behavior.
- Prefer normal FBP helpers and existing skills over copied bespoke UI code.
- Do not make the sample depend on a live app, database, task, or server configuration.

## Files To Add

Use this structure for each sample:

```text
fbp/docs/.agents/skills/fbp-app-samples/
  assets/<sample-name>/
    <starter source files>
    <sample-name>.json
  references/<sample-name>.md
  references/<sample-name>-db.md
  scripts/install_<sample_name>.php
```

Keep `SKILL.md` short. Add only a concise sample entry there, and put detailed flow, DB notes, and implementation scope in the reference files.

## Manifest

The sample manifest should list the files that make the sample installable or auditable. Include source assets, DB/field definitions if represented in JSON, and any public page or webhook metadata needed by the installer.

Avoid environment-specific values such as domains, tokens, absolute paths, login IDs, and production table contents.

## Installer

Add an installer script when the sample should be copied into a clean Codex Booster app repeatedly.

The installer should:

- Resolve paths relative to the repository or an explicit target directory.
- Refuse ambiguous or missing targets with a clear error.
- Create only the expected destination files/directories.
- Avoid overwriting unrelated user files unless the behavior is explicit and reported.
- Print a short summary of installed files and next verification steps.

## README Prompt

When adding a sample to the repository, update the README `Make Samples` section with a prompt that another Codex session can use to recreate the sample.

The prompt should state:

- The sample name.
- The reference app or feature to inspect.
- The reusable scope to keep.
- The project-specific behavior to remove.
- The required assets, references, manifest, installer, and README update.
- The verification commands or scenarios expected before completion.

## Verification

For a new or changed sample, run checks that fit the files changed:

- `php -l` for all added or changed PHP files.
- JSON parsing for manifests.
- Installer execution into a temporary directory.
- FBP CLI checks from the web-side app when generated code is runnable: `app_call`, `app_check`, `webhook_rule_list`, `db_schema`, `data_add`, or `data_list` as appropriate.

If a check cannot be run, record the reason in the final response.

## Prompt Template

```text
Add a reusable FBP Codex Booster Make Sample.

Sample name:
<name>

Reference:
Inspect <app-or-feature> as the source pattern.

Scope:
Keep <minimal reusable workflow>.
Remove project-specific rules, credentials, production data, local paths, and one-off compatibility behavior.

Required output:
- Add starter files under fbp/docs/.agents/skills/fbp-app-samples/assets/<sample-name>/
- Add references/<sample-name>.md
- Add references/<sample-name>-db.md when DB/note definitions are part of the sample
- Add scripts/install_<sample_name>.php when repeatable installation is useful
- Update fbp-app-samples/SKILL.md with a concise sample entry
- Update README Make Samples with a Codex prompt for creating this sample

Verify:
- PHP lint for added PHP files
- manifest JSON parse
- temporary install
- relevant FBP CLI checks
```
