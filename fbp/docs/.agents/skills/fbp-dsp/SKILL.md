---
name: fbp-dsp
description: Implement or audit FBP Database Security Policy rules that compensate for authorization mistakes in AI-generated code through fixed_file_manager, including project policies, Controller/channel context, row and field restrictions, and compatibility tests.
---

# Database Security Policy

## Scope

DSP complements normal FFM data access; it is not isolation from arbitrary PHP code. Do not expand an ordinary DSP task into direct-file, malicious-bypass, policy-tampering, maintenance-operation or OS-isolation work unless separately requested.

Undefined DBs preserve existing behavior. Registered policies allow or throw DspException. A registered policy's missing implementation, invalid registry or evaluation failure stops safely; never downgrade an error to undefined.

## Application switch

The administrator system setting "Database Security Policy (DSP)" enables or disables enforcement for that application. `dsp_disabled=0` is ON and `1` is OFF; missing/invalid values keep ON. OFF retains definitions and PHP but skips registry/policy loading, judgments and dependency opens, including explicitly supplied policies. Existing application authentication/authorization remains active. Use OFF only when explicitly requested for that app; do not change another app's setting or infer permission to leave it OFF from a general audit request.

Framework entry points read the saved system setting on each request and populate Controller's server-owned setting snapshot before protected access. The next request after saving observes the switch, including already logged-in sessions. Do not accept the flag from GET/POST for data access or read the system setting DB from a judgment. Already opened handles retain their binding within that request. With ON, load/evaluation failures still stop safely. Tests should cover ON restoration, all entry channels, dependency suppression and unchanged application authorization.

## Human definitions and creation workflow

The developer panel DSP tab stores one `dsp/policies` row per note ID and operation. Visible fields are Note, Add/Read/Update/Delete, Allow/Deny/Custom and Conditions. Custom requires plain-text conditions; other modes have no conditions. Prevent duplicate note/operation rows. Note IDs link to existing `db/db` definitions; never guess rights from the note name.

Definitions are specifications, not runtime configuration. Saving them does not change enforcement. Runtime reads only generated classes/app/_dsp PHP through the existing registry; no definition DB reads, prose evaluation, or definition/code mismatch checks are permitted in the data-access path. The audit task compares definitions against generated PHP. Undefined notes/operations have no additional DSP restriction (All Allow); application authorization remains in effect.

Use `fbp/lib/DspPolicyTemplate.php` or CLI `app_call dsp generate_template` with note_id to obtain the source template and registry entry. Allow authorization methods simply return; Allow reads return DspReadDecision(true, null). Deny methods throw DspException. Unconfigured operations use the Allow template. Custom starts with a refusing placeholder; replace it only after confirmed conditions are implemented and tested. Do not install the placeholder as a finished Custom implementation. There are no definition hashes to match at runtime. Keep policy.md as a generated human-readable description and the panel definitions as the source of truth.

Auto Task creation (type 8) has three stages, tracked by existing task/comment history, with no new status flags:
1. Read-only selection of candidate notes with reasons. Request explicit agreement using the existing customer-response-wait status and TASKEXEC_WAIT_CUSTOMER: true.
2. After selection is agreed, propose all four operation modes and conditions for the selected notes. Ask unresolved questions. Request agreement using the same existing customer-response-wait status. Explain the existing release-permission checkbox if production release is requested.
3. Only after both agreements, save confirmed definitions, create PHP, test allow/deny behavior and release under existing code/release permissions. Permissions ON alone, silence, elapsed time and a generic initial creation/change request do not substitute for those agreements. Already explicit agreements in history need not be repeated.

For new creation and additions/changes, select the starting stage from explicit evidence in the request and comment history:
- No clear target note: stage 1, selecting only the necessary scope; do not reselect every note for a small change.
- Customer explicitly names the note, but the operation/mode/conditions are not settled: stage 2. Present the current and proposed values and relevant access/field effects only for affected operations.
- Customer explicitly requests concrete note, operation and resulting mode/conditions, or has already agreed to that exact difference: stage 3 for that scope, under existing change/release permissions. Do not ask again for that already explicit instruction. A generic request or permission ON is insufficient.
- Partial agreement: confirm only the unresolved part. Expanding scope requires confirmation only of the additional part.

Preserve unrelated notes, operations, conditions, exceptions and PHP methods. Add only the selected definition rows; update only the agreed rows/operations. Do not reset all definitions or regenerate unrelated code to repair discrepancies noticed during this task. If a shared helper would change other operations, explain that additional effect and obtain agreement before making it. Report the actual changes and tests/release results. Use the existing customer-response-wait status for unresolved questions, never a new flag.

Examples: “Add DSP to reservations” starts at the reservation policy proposal; “Change reservation Update from owner-or-manager to manager-only” starts at implementation/testing of Update and does not reopen Read or other notes. An unclear request to review access starts at target selection.

Audit (type 9) reads both panel definitions and PHP, including changed/deleted definitions, missing implementations and undefined data paths. It remains read-only and reports discrepancies; it does not automatically update definitions or code.

## Project layout and registration

Place policy.md, registry.php and policy classes in classes/app/_dsp. The _dsp directory is internal application code; do not create a routable _dsp.php or register it as a screen. No old classes/dsp lookup is supported. Shared DspInterface and DspException belong to the framework; do not duplicate them in the project.

registry.php returns a map keyed by `class/table`, for example:

```php
return [
    'common/reservations' => [
        'file' => 'ReservationDsp.php',
        'class' => 'ReservationDsp',
        'dependencies' => [['table' => 'shop_members', 'class' => 'common']],
    ],
];
```

Exact physical partition keys such as `common_12/reservations` take precedence; `common/reservations` covers Controller-managed partitions of that class. A direct FFM construction should pass database_class when using partitions, plus controller. The runtime resolves classes/app/_dsp from the classes/data path. Nonstandard data roots require an explicit `dsp` option. Avoid circular policy dependencies.

FFM uses existing constructor options: controller, database_class and channel; channel is obtained from Controller when present. An explicit DspInterface object can be supplied for isolated tests. Registered operations without the required Controller fail safely. Verify initialization paths when applying policies to framework bootstrapping DBs.

## Interface

Policies receive `__construct(Controller $ctl, string $channel)`. Constructors only retain dependencies; do not open DBs or authorize there. Read current verified identity and project-specific organization/delegation from Controller during each operation. No separate ContextProvider is required.

Implement all methods of `fbp/interface/DspInterface.php`:

- authorizeInsert(newRow): check the normalized values that FFM will save, including its generated ID.
- authorizeRead(request): inspect method, IDs, query fields/values, sort and limits. Forbid searching/sorting secret fields unless an explicit exception exists. Inspect grouped field arrays too.
- inspectRead(row): return DspReadDecision(visible, allowedFields). null fields means all fields. Explicit fields also govern id and _id_enc; specify them intentionally.
- authorizeUpdate(before, after, submittedFields): use the persisted before-image, normalized after-image and submitted field names. Protect ownership/organization and immutable fields even when the submitted value is unchanged.
- authorizeDelete(before): use the persisted target and state.

The channel values are admin/public/mcp/api/cron/cli (unknown if no runtime context). The runtime chooses the entry; do not accept a channel from GET/POST. Internal class calls keep the entry's channel. Legacy public classes must call set_check_login(false) before opening protected DBs; constructor-time policies freeze channel. Naming conventions cover public_* and *_api, while nonconventional API entry points must declare their channel through framework-owned routing or Controller::set_dsp_channel before protected access. Channel does not grant authority.

For MCP, Controller::get_dsp_mcp_subject() supplies validated call authentication only during function execution; do not substitute browser session identity for an OAuth subject. Determine the relevant subject type and organization using the project's existing authentication rules. CLI and cron are not automatically administrators.

## Lock and purity rules

Declare DB dependencies in registry before the target's operation. FFM may reacquire existing locks when adding another DB; do not rely on values read before preopening all dependencies. Judgment methods must only compare the supplied row with prepared identity/scope values. FFM reads (including unprotected DBs), writes, closes, external calls and new DB opens are prohibited during judgment. Do not resolve parent records with get/select/filter from a judgment.

DspRuntime temporarily enables Controller::set_prohibit_new_db(true) and restores the previous value with finally. Controller::db permits only cached handles during this guard. Controller close APIs and api() also reject lock changes. The connection guard alone permits writes to existing DBs, so it can also be used in normal application critical sections. Separately, DspRuntime tracks active judgments and FFM rejects writes during those judgments, including writes to unprotected dependency DBs. The guard is a consistency aid, not a sandbox against arbitrary code.

For parent/organization information not stored on the target row, implement optional `DspPreparedInterface::prepareContext(callable $snapshot)`. This runs once before each public read or mutation, after all dependencies are open, with writes/new connections/lock changes prohibited. Call `$snapshot('project', 'common')` to obtain a trusted ID-keyed persisted map of a declared, preopened dependency. This preparation-only internal path does not invoke dependency DSPs: use it only to prepare authorization facts, never return its contents to users. Ordinary reads of protected DBs during preparation are rejected to prevent recursion. Put project identity preparation in a helper; no separate authentication Provider is required. Do not fix an identity for the lifetime of a policy. Preparation errors fail closed.

Dependency maps are cached only while FFM locks remain held. Mutations and lock release/reacquisition invalidate the maps. Read decisions are reused for visibility and field projection only within one public read, and cleared before the next operation. Keep maps bounded to necessary dependencies; add volume tests before registering large dependencies.

FFM internally uses raw records for mutations and queries, evaluates matching candidates once after original query conditions and before limits, and projects fields at the public boundary. get/get_many reject explicitly requested hidden rows. Scans skip hidden rows. match also requires permission to return id. Check all read APIs, including next/before/match/neighbors/iterate_filter; callbacks must never receive hidden fields.

## policy.md

Record target DBs, principal ID namespaces, identity source, allowed/forbidden operations, visible rows, returned fields, writable/immutable fields, organization/delegation, channel differences, exceptions, related DBs, stable rule IDs, unresolved business questions and allow/deny tests. Do not guess ambiguous business rights. Preserve existing policies and explain why a change is needed.

For reservations, check stored owner against authenticated actor; verify management rights for the target organization. On insert require authenticated ownership; on update forbid owner replacement unless explicitly specified. Read rules, proxy creation and ownership transfer are separate business decisions.

## Exceptions and audit

Throw `new DspException('stable_rule_id', 'reason_code')`. Keep details to identifiers/codes; never put raw records, tokens or passwords in exceptions. External messages are generic; internal DSP logs contain operation, DB, row ID, channel, rule/reason and trace ID. Unexpected exceptions are wrapped as evaluation errors and stop the operation.

Audit is read-only: compare policy.md, implementations, normal FFM call sites and undefined DBs; run allow/deny tests only on isolated disposable data. Do not change code/docs/configuration, sync or release unless the user changes the task scope. In support task type 9, request a separate policy/change task for fixes. Missing policies and unverified tests must be reported, not treated as a clean audit.

## Introduction and verification

1. Read the project's relevant docs and existing auth/data access. Inventory targets and currently undefined DBs.
2. Confirm business rules, create/update policy.md, registry and implementation for confirmed targets only.
3. Synchronize via the environment's approved workflow. classes/app/_dsp is included by normal application synchronization and release. Do not add a separate DSP payload or old-path fallback; replacing application code also replaces its policies.
4. Test undefined compatibility, allowed CRUD, denied CRUD, other owners/orgs, immutable fields, read projection/query restrictions, load/evaluation errors and connection guard restoration.
5. Test production-like parent/child volumes and assert decision counts are linear (no dependency DSP calls or duplicate row judgment). Run meaningful multi-process tests for locks. FFM does not provide multi-DB rollback.
6. Report actual results and unresolved rules. Use the existing project support permissions; policy creation does not authorize production release by itself.

Choose regression checks for the changed behavior; the 1,000-case dsp_matrix.php suite is no longer mandatory and must not be run automatically. Keep it available for an explicit request. If used, provide an isolated workspace and a baseline preserving its relative interface dependency. Run checks from the test environment, not the source tree. Use isolated test fixtures; app-soshikikaikaku currently has no project DSP while its rules are reconsidered.
