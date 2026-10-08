---
name: fbp-dsp
description: Implement or audit FBP Database Security Policy rules that compensate for authorization mistakes in AI-generated code through fixed_file_manager, including project policies, Controller/channel context, row and field restrictions, and compatibility tests.
---

# Database Security Policy

## Scope

DSP complements normal FFM data access; it is not isolation from arbitrary PHP code. Do not expand an ordinary DSP task into direct-file, malicious-bypass, policy-tampering, maintenance-operation or OS-isolation work unless separately requested.

Undefined DBs preserve existing behavior. Registered policies allow or throw DspException. A registered policy's missing implementation, invalid registry or evaluation failure stops safely; never downgrade an error to undefined.

## Project layout and registration

Place policy.md, registry.php and policy classes in classes/dsp. Shared DspInterface and DspException belong to the framework; do not duplicate them in the project.

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

Exact physical partition keys such as `common_12/reservations` take precedence; `common/reservations` covers Controller-managed partitions of that class. A direct FFM construction should pass database_class when using partitions, plus controller. The runtime resolves classes/dsp from the classes/data path. Nonstandard data roots require an explicit `dsp` option. Avoid circular policy dependencies.

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

Declare DB dependencies in registry before the target's operation. FFM may reacquire existing locks when adding another DB; do not rely on values read before preopening all dependencies. Policies must only read existing DBs; no DB writes, closes, external calls or new DB opens during judgment.

DspRuntime temporarily enables Controller::set_prohibit_new_db(true) and restores the previous value with finally. Controller::db permits only cached handles during this guard. Controller close APIs and api() also reject lock changes. The guard is a consistency aid, not a sandbox against arbitrary code.

FFM internally uses raw records for mutations and queries, applies row visibility before limits, and projects fields at the public boundary. get/get_many reject explicitly requested hidden rows. Scans skip hidden rows. match also requires permission to return id. Check all read APIs, including next/before/match/neighbors/iterate_filter; callbacks must never receive hidden fields.

## policy.md

Record target DBs, principal ID namespaces, identity source, allowed/forbidden operations, visible rows, returned fields, writable/immutable fields, organization/delegation, channel differences, exceptions, related DBs, stable rule IDs, unresolved business questions and allow/deny tests. Do not guess ambiguous business rights. Preserve existing policies and explain why a change is needed.

For reservations, check stored owner against authenticated actor; verify management rights for the target organization. On insert require authenticated ownership; on update forbid owner replacement unless explicitly specified. Read rules, proxy creation and ownership transfer are separate business decisions.

## Exceptions and audit

Throw `new DspException('stable_rule_id', 'reason_code')`. Keep details to identifiers/codes; never put raw records, tokens or passwords in exceptions. External messages are generic; internal DSP logs contain operation, DB, row ID, channel, rule/reason and trace ID. Unexpected exceptions are wrapped as evaluation errors and stop the operation.

Audit is read-only: compare policy.md, implementations, normal FFM call sites and undefined DBs; run allow/deny tests only on isolated disposable data. Do not change code/docs/configuration, sync or release unless the user changes the task scope. In support task type 9, request a separate policy/change task for fixes. Missing policies and unverified tests must be reported, not treated as a clean audit.

## Introduction and verification

1. Read the project's relevant docs and existing auth/data access. Inventory targets and currently undefined DBs.
2. Confirm business rules, create/update policy.md, registry and implementation for confirmed targets only.
3. Synchronize via the environment's approved workflow. classes/dsp must be included in synchronization and release archives; old archives without DSP leave existing policies intact.
4. Test undefined compatibility, allowed CRUD, denied CRUD, other owners/orgs, immutable fields, read projection/query restrictions, load/evaluation errors and connection guard restoration.
5. Run meaningful multi-process tests for locks. FFM does not provide multi-DB rollback.
6. Report actual results and unresolved rules. Use the existing project support permissions; policy creation does not authorize production release by itself.

For framework changes run existing FFM tests and the deterministic dsp_matrix.php suite (1,000 cases including comparisons with pre-change FFM). Provide an isolated workspace and a baseline file preserving its relative interface dependency. Run from the test environment, not the source tree. The integrated verification fixture in app-soshikikaikaku is restricted to the test environment and its registered test DB; it does not define business policies for existing notes.
