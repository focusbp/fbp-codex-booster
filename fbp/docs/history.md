# app-framework5 History

## 2026-09-18 Mobile header
- At widths up to 700px, the management header hides the app title, tagline and login user name, placing the existing password-change and logout controls at the right of the menu bar. Controls remain single instances with their existing actions and 44px touch targets. The mobile bar measures 57px high; desktop presentation returns above the breakpoint.
- Verified 320/375/700px alignment and 701/1280px desktop restoration in a touch-enabled browser, plus menu opening/closing, password dialog opening and logout back to the login form. No JavaScript errors; source and test files match.

## 2026-09-18
- Standard Screen search/list and manual-sort main lists now use labeled cards at viewport widths up to 700px. Search fields and date ranges stack vertically; long values and action buttons wrap. Mobile cards override horizontal-scroll settings. Desktop tables, calendar/single-record screens and child side panels retain their existing layout. Sortable clones its drag helper so cell widths do not persist after resizing.
- Original searchable selects now size entirely through CSS, without copying the source select's inline or computed width. Custom widths belong on the field container or wrapper. Date/time/color/year-month panel widths moved from JavaScript markup to viewport-constrained CSS in both management and public styles; geolocation feedback follows its container through CSS. Internal picker controls and popup positioning retain their functional dimensions/JavaScript.
- Browser verification covered real Standard Screen lists at 320/375/700/701/1280px, search selection, text/select width equality after resizing, date selection, color panel bounds, geolocation success/failure feedback, and mobile-card drag payloads without writing shared record order. No horizontal overflow was present at the tested mobile widths; desktop retains its pre-existing 800px minimum above the mobile breakpoint.
- Also verified edit-dialog opening, mobile horizontal-scroll overrides, desktop drag payloads and unchanged original cell widths, time/year-month selection, and public-style date/time/color/year-month panel bounds at 320px. Browser runs reported no JavaScript errors.

## 2026-09-17
- Made original searchable select wrappers responsive through CSS instead of freezing computed widths at initialization; explicit inline widths remain supported. Capped text inputs, textareas and native selects at their parent width in management/public form styles. Browser fixtures using framework assets verified 1200→768→375→1200 viewport changes, relative/fixed widths, no horizontal overflow, and searchable selection.
- Added the WEB tab’s get_APP_URL protocol override below SSL: 0 preserves request-based detection, 1 forces HTTPS, and 2 forces HTTP. Missing values preserve existing behavior. Initial project setup accepts this setting with default 0; system setting data remains outside app releases. Verified protocol combinations, existing URL/query formats, setting form rendering and HTTPS URL generation in the test wizard.

## 2026-09-17
- Added `prohibit_url_check()` at the start of `app.php`, before session/library/DB initialization. Requests with `_next` or `.next` path segments (including percent-encoded forms and app subpaths) return HTTP 403 and the fixed Next.js rejection message as plain text. HEAD omits the body; query-only matches and similar ordinary names remain allowed. Extend the local rule list for future confirmed probe patterns. Only requests routed to `app.php` are covered.

## 2026-09-16
- Fixed cron execution-log writes after jobs close Controller-managed databases (ServerError #270/#277/#278). Cron retains its existing initial lock lifetime, reacquires the database through Controller and ordered FFM construction when needed, updates only the log of an existing job, and closes through Controller. Throwable failures are logged within the field byte limit and rethrown for ServerError reporting. Isolated real-FFM tests cover normal execution, closed connections, concurrent setting changes/deletion, exceptions, and reverse-order two-process lock acquisition. No standalone lock or automatic retry was added.

## 2026-09-10
- Added the Standard Screen single-record pattern (`list_type=3`): a main-area form using edit fields, insert-on-first-save, updates to the existing ID, and a success Notification. Retains standard validation, uploads, and post-action hooks; rejects multiple records and standard list/duplicate/delete/child operations.
- Supports top buttons only (`place=0`), enforced in button settings, CLI, and drag placement. Pattern conversion rejects parent notes, multiple records, and incompatible buttons. Updated screen-pattern labels, edit-field configuration, the Standard Screen checker, and reusable Skill guidance. Verified concurrent saves, invalid inputs, direct-operation guards, conversion checks, and browser save/notification/top-button behavior. Generic DB writes remain outside the screen-level singleton constraint.

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
