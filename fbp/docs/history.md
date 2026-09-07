# app-framework5 History

## 2026-09-07
- Added an enabled/disabled selector and save button to the setting screen's MCP Server URL dialog. Saving validates the state and changes only enabled/updated_at on existing records; other server settings remain read-only. Missing configurations display disabled and are initialized only on save. Verified both states, rejected invalid input, preserved other fields, and restored the test state.
- Removed the OAuth URLs row from the setting screen's MCP Server URL dialog. The MCP endpoint remains visible; OAuth behavior is unchanged.

## 2026-09-06
- Replaced Web and CLI template constant preloading with one definition scan and one sorted value scan, grouping values and colors in memory. Preserves name/assignment order, table_fields-last behavior, legacy duplicates and text keys, empty colors, and live table references without persistent caching. Regression fixtures compare all assignments against the old loop; a 54-set/331-value fixture reduced preload time from roughly 200–330ms to 2ms.

## 2026-09-06
- Fixed DB API `describe` rejecting valid four-column FFM definitions with `IDX`. Responses now include `indexed`, matching FFM behavior (including the implicit ID index exception), while invalid options remain rejected.

## 2026-08-31
- Added output schemas to the fixed public MCP `function_list` and `function_call` Tools. The catalog result explicitly returns `functions` and `count`; the dispatcher accepts the selected internal function's structured object result.

## 2026-08-31
- Fixed the public MCP tool surface to `function_list` and `function_call`. Registered `mcp_<function_name>` classes remain an authenticated internal function catalog, while legacy `mcp_tools`, Note CRUD, and App Action registrations are no longer executable or publishable through MCP.

## 2026-08-27
- Implemented the single MCP Server function registry with `mcp_functions`, deterministic `mcp_<function_name>` class loading, `McpFunctionInterface`, standard `tools/list` and `tools/call` dispatch, singleton management UI, CLI registration, and migration-only fallback to legacy tools when an app has no registered functions.
- Removed the standalone blocking read-only preflight lock and routed Standard Screen read-only opens through the existing globally ordered FFM lock path before format validation. Missing data files and format changes now request an ordered writable reopen, and a two-process reverse-order regression test verifies that shared/read-write opens cannot form the former circular wait.
- Documented the planned MCP redesign around one Server per project, standard `tools/list` / `tools/call`, a dedicated function registry, deterministic `mcp_<function_name>` classes, task-management migration, and mandatory cleanup of legacy specifications, Skills, samples, and compatibility code after migration.

## 2026-08-14
- Added opt-in `IDX` fields to fixed file formats, exact-match candidate indexes for `select()` and exact `filter()`, CRUD/change-format index maintenance, and safe full-scan fallback for missing, dirty, or invalid indexes.
- Added the `db_fields.index_flag` management option and preserved the existing behavior for every field without `IDX`.
- Added CLI-only FFM index, concurrency, performance, existing-corpus, and pre/post implementation differential tests under the fixed file manager directory.
- Added 16 irregular index tests and automatic writable-mode rebuilds for missing or structurally invalid indexes; dirty state remains a full-scan condition requiring explicit integrity review.
- Added a daily-use benchmark with 100,000 customers and 1,000,000 child histories, including CRUD, cold-request, memory, and eight-reader concurrency measurements.
- Added an isolated 128-shard JSON versus fixed-binary benchmark for one million index IDs, covering cold, spread, hot-cache, memory, and concurrent-reader behavior.
- Replaced the opt-in FFM JSON index with a versioned 128-shard fixed-binary index using 64-bit IDs, per-shard checksums, binary-search lookup, touched-shard-only CRUD updates, legacy JSON migration, and safe full-scan fallback.
- Expanded irregular tests to 19 binary-index cases and repeated the one-million-row and 100,000-customer/one-million-history benchmarks, reducing cold-open allocation from 53 MiB to 2 MiB per PHP process.
- Re-ran the pre-binary JSON implementation and current binary implementation as a release preflight, including strict IDX-free byte comparison, five existing `.dat` copies, five framework regression tests, one-million-row benchmarks, and a scan confirming no existing test-app `.fmt` currently enables `IDX`.
- Enabled Standard Screen filters to use opt-in indexes for numeric equality conditions while preserving text partial matching, range and OR fallbacks; one million rows improved from 2.487848 seconds to 0.027545 seconds with identical results.
- Added internal 4 MiB block scanning for T-field partial matching without external commands or text index files, with fixed-field boundary checks, candidate-limit fallback, and final legacy-condition revalidation; one million rows improved by about 7.7–9.9x with identical results.
- Opened Standard Screen display-only requests with shared read-only FFM locks so concurrent lists and searches do not serialize, while write functions retain exclusive locks and missing/stale formats safely use the existing writable initialization path.
- Changed Standard Screen rendering to ignore stale `screen_fields` links without deleting configuration during a display-only request, preventing read-only side panels and lists from failing when a field was removed first.

## 2026-08-05
- Changed setting-generated `.htaccess` and `robots.txt` writes to throw an exception when `file_put_contents()` fails, so setting screen/API saves cannot silently report success after a write failure.

## 2026-08-04
- Changed `get_APP_URL()` to generate standard `?key=value` query strings by default, preventing LINE and other external clients from dropping the first parameter.
- Kept the former `&key=value` output available through the explicit `query_format=legacy` option; existing legacy links remain routable without `.htaccess` changes.

## 2026-06-10
- Investigated the existing `show_menu_homepage` setting before changing the homepage menu behavior.
- The current menu link is controlled by `show_menu_homepage` and points to the app root generated by `get_APP_URL()`.
- The existing homepage field is `website_url`; it is only shown on the setting screen and is empty in the checked test apps.
- Checked apps with the menu option enabled or recently enabled: `app-miclubpayment`, `app-nb`, `app-tomi`, and `app-wordgritty`.
- Planned behavior: show the homepage menu only when `show_menu_homepage` is enabled and `website_url` contains a valid `http` or `https` URL, and use `website_url` as the menu link target.

## 2026-06-13
- Added the first framework-level MCP foundation: `mcp_manage` in the development panel owns server/tool/field settings, and `mcp_server` exposes the public MCP/OAuth endpoints.
- MCP OAuth tokens are linked to existing `user.id`; every tool call re-checks the user record so deleting or invalidating the user also invalidates the OAuth connection.
- Added MCP server/tool/field setting data to project release packages while excluding MCP OAuth tokens, auth codes, and call logs.

## 2026-06-16
- MCP Note CRUD `create` and `update` now accept `file` / `image` fields as base64 upload objects, and `list` / `get` return file metadata plus public download/view URLs for saved media.
