# Scoped integration settings

The `integration_settings` signed endpoint provides `catalog`, `status` and `apply` operations for mail, OpenAI, LINE, Square, Google, Vimeo and external keys. Local CLI invocation uses the same implementation. It never contacts providers or validates credentials.

- `IntegrationSettings::definitions()` is the field allowlist. `status` returns non-secret display values and registration booleans, with a keyed revision fingerprint. Secret values are omitted entirely.
- `catalog` returns `key`/`title` only. The orchestration layer supplies the canonical test catalog to production. Stable key strings map environments; record IDs do not. Clearing a value preserves its catalog entry.
- `apply` accepts a service, request ID, expected revision, catalog revision and explicit set/delete operations. Empty secret input preserves its value. Storage types/lengths and the editable destination scope are enforced, without credential or connection preflight checks.
- Requests are idempotent. Reusing an ID with different content is rejected. A stale revision is rejected before writes. The local coordinator retries uncertain transport outcomes with the same request ID.
- Existing setting/external-key storage formats remain unchanged. Settings secrets are masked in FFM operation logs; raw setting snapshots are excluded. Queued payloads are also omitted from operation logs.
- External keys, orchestration requests and target apply receipts are excluded from release archives in both directions. Application releases cannot transfer these environment-specific values or receipts.

The consuming application owns project access, environment resolution, the canonical catalog, encrypted transit queue, user interface and task resumption. This endpoint does not grant public access to arbitrary system settings. Deploy the framework before enabling the consuming application's settings worker.
