# Scoped integration settings

The `integration_settings` signed endpoint provides `catalog`, `status` and `apply` operations for mail, OpenAI, LINE, Square, Google, Vimeo and external keys. Local CLI invocation uses the same implementation. It never contacts providers or validates credentials.

The comment dialog now uses `write`: only explicitly submitted changes are applied, without a prior status/catalog/revision fetch. The response is a request ID and write receipt for the submitted fields, never a settings snapshot. Unsubmitted fields are preserved; external-key writes require existing keys at the destination. Legacy `apply` retains its revision checks for existing callers.

- `IntegrationSettings::definitions()` is the field allowlist. `status` returns non-secret display values and registration booleans, with a keyed revision fingerprint. Secret values are omitted entirely.
- `catalog` returns `key`/`title` only. The orchestration layer supplies the canonical test catalog to production. Stable key strings map environments; record IDs do not. Clearing a value preserves its catalog entry.
- `apply` accepts a service, request ID, expected revision, catalog revision and explicit set/delete operations. Empty secret input preserves its value. Storage types/lengths and the editable destination scope are enforced, without credential or connection preflight checks.
- Requests are idempotent. Reusing an ID with different content is rejected. A stale revision is rejected before writes. Comment jobs make one apply attempt; unknown transport outcomes are returned to customer wait without reapplying.
- Existing setting/external-key storage formats remain unchanged. Settings secrets are masked in FFM operation logs; raw setting snapshots are excluded. Queued payloads are also omitted from operation logs.
- External keys, orchestration requests and target apply receipts are excluded from release archives in both directions. Application releases cannot transfer these environment-specific values or receipts.

The consuming application owns project access, environment resolution, the canonical catalog, encrypted transit queue, user interface and task resumption. This endpoint does not grant public access to arbitrary system settings. Deploy the framework before enabling the consuming application's settings worker.

## Fixed framework DSP

`DspIntegrationPolicy` takes precedence over application DSP registrations and explicit policies for the following internal paths. It is enforced even when application DSP is OFF. Application registry loading and dependencies are skipped for matching, already authenticated accesses.

| Entry | Storage | Allowed operations |
| --- | --- | --- |
| HMAC-verified `project_integration_api` on channel `api` | `project_integration/items` | Read, insert, update, delete queue/chunk records |
| Same | `project_integration/entries`, `project_integration/queue` | Keyed documents and validated comment-job bookkeeping; queue deletion denied |
| Same | `common/sftp` | Read target metadata |
| Same | `common/project` | Read; update unfinished count |
| Same | `common/task` | Read; status 0/3 transitions, claim flag 0/1 within these states, comment timestamp and review bookkeeping; other task fields remain denied |
| Same | `common/task_history` | Read; legacy saved-settings notice; integration metadata on existing role 1/3 comments without changing their body; role 3 result comments with integration metadata |
| HMAC-verified `integration_settings` on channel `api`, or its local PHP CLI entry | `setting/setting` | Read; update integration fields from `IntegrationSettings::definitions()` |
| Same | `external_keys/external_keys` | Read, insert, update |
| Same | `integration_settings/receipts` | Read, insert |

The override applies only to exact physical storage classes, never partitions or unrelated tables/entries. Cached bootstrap handles acquire the fixed policy after authentication. Each operation rechecks the verified subject; a revoked proof cannot keep using a bound policy. Request fields cannot supply this proof. Other accesses retain normal application DSP behavior. The endpoint still owns service/field validation, destination resolution, revision checks, secret-free responses and resumption conditions; the fixed DSP does not replace them.

Settings writes submit only the explicit integration fields to FFM. They do not call the generic system-settings saver, whose unrelated default normalization is outside the integration DSP scope. All unsubmitted fields, including legacy empty defaults, remain unchanged.
