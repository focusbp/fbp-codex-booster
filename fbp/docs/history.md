# app-framework5 History

## 2026-09-27 名刺管理ノート
- 専用ビルダーでテスト環境に `business_cards`（名刺管理）を作成。氏名・会社名・部署・役職・メールアドレス・電話番号・住所・名刺交換日・メモを登録し、検索＋一覧の標準画面を構築した。定義は `note-definitions/business-cards.json`。
- 作成前チェックと登録成功、検索画面のスクリーンショット1枚取得で完了。作成後テスト・サンプルデータ投入・本番反映は行っていない。

## 2026-09-27 Standard Screen builder workflow
- Standard Screen, DB, CLI and Playwright Skills now prefer the environment's validated create-only builder for supported new search/list notes. Normal completion is strict preflight, configuration creation and one search-screen screenshot; post-build checks and CRUD tests are omitted. Existing-note changes and unsupported features retain the ordinary workflow.
- Builder development checks cover supported field storage, invalid-input rejection, failure receipts, target collisions and screenshot capture/retry. No framework runtime or production deployment was changed.

## 2026-09-27 Scoped integration settings
- Added a signed/CLI settings endpoint for seven service groups, secret-free registration state, test-derived external-key catalog, scoped set/clear and idempotent apply receipts. No provider connection or credential preflight checks. Existing storage formats remain unchanged.
- Masked system-setting secrets in operation logs and excluded raw setting snapshots. Environment-specific queue/receipt data is excluded from release archives. Verified real test-runtime storage, isolation, retry/conflict handling and consuming application's worker/UI flows. Test environments synchronized; no production release.

## 2026-09-26 External integration keys
- Production framework release `2f512cd` completed on all selected servers (57 application entries). Nine relevant distribution-source files matched local SHA-256 on each server. Includes the Standard Screen ID-prefix feature. Individual application screen operation after deployment was not rechecked.
- Added a system-settings tab immediately after Vimeo for app-owned API credentials/settings: unique case-sensitive key, display title and values up to 8,192 bytes. Server-side `get_external_key` / `require_external_key` provide lossless reads; saved values are never repopulated in management forms, and blank edits preserve the current value.
- Records are environment-specific and excluded from release files; incoming older archives containing this data are also ignored. Access follows system-setting permissions, including the app management guard. FFM logs and browser debugging mask values; secret log snapshots are excluded. See `external-keys.md` for usage and storage semantics.
- Verified 56 storage/API/permission/release-policy checks and Chromium CRUD/search, tab placement and existing setting form ownership. The actual downloaded release excluded the data and fixture value; at 390px the panel measured 342px with no horizontal overflow. Test environment synchronized; production framework release completed as recorded above.

## 2026-09-26 Standard Screen record identifiers
- Added optional note setting `identifier_prefix` to the existing list ID display. Search/list and manual-sort rows show `[prefix:id]` when `show_id=1`; empty prefixes preserve numeric IDs. The setting is available in note settings and `db_tables_add/edit`, with shared validation and escaped, non-wrapping output. Other screens retain their existing behavior.
- Verified both list patterns, unset/cleared/hidden ID behavior, invalid input rejection, management-form save/read-back and standard-screen checks. Chromium checks at 1440px and 390px confirmed the identifier is the first data cell, with no page overflow. Applied `fbpdev` to the consuming app's free-registration list in the test environment. No production release.

## 2026-09-26 Inline saved media response
- Added `res_saved_media($filename, $options)` for content-detected image/video/audio delivery with private/no-store by default, optional public caching, single byte ranges and HEAD. Record authorization remains app-owned. Removed the unreleased opt-in saved-file guard from the previous Task 4378 change; existing image/download APIs return to their pre-guard behavior.
- Verified 33 isolated HTTP checks, 38 app-side access/legacy-entry checks and Chromium MP4 playback/seeking. Framework release 7bb1f49 completed successfully on all selected servers; the three changed runtime files matched the local hashes in each server's distribution source. Direct per-app hash inspection was unavailable with the verification account's permissions.

## 2026-09-26 Admin PDF delivery sample
- Added standalone fictional single/bulk invoice assets, manifest, overwrite-refusing installer and a browser verifier. Management login remains enabled; the sample rejects empty/unknown selections with HTML and uses object `download_pdf()` output. PDF Skills and README now point to actual reusable assets instead of an inline example.
- Verified CLI dialog output, PHP/JS syntax, installation/refusal and Chromium desktop/mobile-width downloads: single A/B, bulk two pages, selected one page, filenames/content, invalid selections and separate-session denial. Parsed all eight PDFs and inspected the rendered sample. Removed the temporary app installation after verification; no production release.

## 2026-09-26 Admin PDF Skill standard
- Standardized new admin PDF guidance on a download dialog, POST via `download-link`, object construction with `create_pdfmaker()`, and `download_pdf()`. Added a reusable PHP/template example and aligned PDF, app-sample, Standard Screen and browser-verification instructions. Template-based `show_pdf()` remains for compatibility or an explicit preview requirement; content-only maintenance does not force migration.

## 2026-09-26 PDF object output APIs
- Audited PDF, public-page, sample, browser-check, temporary-file and CSV/media Skills together. Clarified that public PDF GET delivery and object `download_pdf()` take precedence over generic file-download rules; application cleanup applies only when directly managing saved files. All six Skill validators passed.
- Added `pdfmaker_class::get_pdf_data()` for binary data without response output and `download_pdf()` for an attachment response with managed-file creation, complete-write checks, private/no-store headers and cleanup on failure/exit. Existing `create_pdf()` inline behavior is preserved. Fixed tFPDF string output to read its actual stream buffer instead of the obsolete empty string property.
- Updated the PDF sample and Skills to use the object download API. Verified real PDF parsing/two pages, repeated generation, inline equivalence, HTTP header/output silence, partial-save/send failures, concurrent downloads and exit cleanup; existing text-box tests also passed. Chromium desktop/mobile-width sample preview/download and the consuming app's invoice/receipt downloads passed. The app PDFs match prior text and rendered pixels. Test environments synchronized; production not released. Deploy the supporting framework before apps that call the new API.

## 2026-09-26 PDF delivery sample and required application
- Added a session-bound per-page download-grant sample with current identity/record/issuance rechecks, fail-closed authentication hooks, HTML denial pages, and managed-file initialization/cleanup. PDF/public-page Skills now require reading and adapting the actual delivery assets and distinguishing an unreproduced preventive change from a verified bug fix.
- Verified 49 contract checks, installation/overwrite refusal, CLI responses, and isolated Chromium desktop/mobile-width PDF retrieval, multiple tabs/documents, expiry, changed ownership, revoked issuance, logout and recovery. Parsed PDFs and confirmed cleanup after download exit; Safari/LINE-device behavior remains outside this verification. No customer app or production release was changed.

## 2026-09-25 Project portal robot guidance
- Added the existing Jean Lafitte image and a speech bubble directly below the Project Portal link in both the home menu and side menu. Japanese copy: 「プロジェクトサポートからシステムのご質問・変更依頼ができます。」. The visible menu and setting labels use 「プロジェクトサポート」 (Project Support); internal keys remain unchanged. The guide follows portal-link visibility and uses a framework-owned static image with Japanese/English translations.
- Verified the authenticated framework test screen at 1280px and 390px: home and side-menu placement, image loading, preserved external-link attributes, no horizontal overflow or JavaScript errors. Test environment synchronized; production not released.

## 2026-09-25 管理画面のレスポンシブSkill
- スマホ表示相談では画面種別を確認し、標準画面は「標準画面レスポンシブ」設定と共通機能で対応する方針を明記。独自画面には同じ700px境界・項目名付きカード・検索1列・表示モード尊重の方針と、範囲を限定したtplサンプルを追加した。
- Skill検証、Smartyによる空／データあり一覧の描画、共通CSSを使ったブラウザーfixtureの320／375／700／701／1280px・両表示モード・幅変更後の復元を確認。案件への適用とは分けて扱う。

## 2026-09-24 Authenticated framework test access
- Retrieved framework test-access metadata from the production management API's DB-free special case and verified browser login, management-page rendering, side-menu loading, settings-page Ajax rendering and logout. The settings menu link was outside the automation viewport, so settings rendering was checked through the existing frontend `appcon` path. No setting values or user records were changed.
- The authenticated flow completed with zero HTTP errors, JavaScript exceptions or React asset requests. This completes the previously unavailable management-screen smoke check after React removal and test-routing repair.

## 2026-09-24 Framework test routing and CLI regeneration
- The framework test entrypoint returned Apache 404 while another app on the same gateway returned 200; direct loopback access reproduced the framework-only failure. The generated root `.htaccess` used relative `fbp` as its subpath, producing `fbp/fbp` rewrites, and exactly matched the shared template with that incorrect input.
- Reproduced the incorrect subpath calculation with `SCRIPT_NAME=fbp/cli.php` in `setting::regenerate_setting_files()`. The observed file predated React removal; the specific historical writer was not established. CLI regeneration now preserves the existing valid URL prefix instead of interpreting filesystem paths as URLs; ambiguous or invalid prefixes fail before either generated file is written. Web saves continue to derive the prefix from the request's framework entrypoint.
- Added `setting_regenerate_files` with optional `htaccess_subpath` to explicitly repair routing without changing setting values; an empty string selects the site root. Repaired the framework test routing through this command and verified that relative and absolute CLI invocations retain the repaired prefix, invalid arguments leave it unchanged, and settings are preserved.
- PHP syntax, 23 URL-prefix regression cases and the generated-file write regression passed. Browser verification of the direct PHP entrypoint, root and login route returned HTTP 200 and rendered the Ajax login form. The public form and required-field Ajax validation also passed, with zero HTTP or JavaScript errors and no records saved. Runtime files match source; this fix has not been released to production.

## 2026-09-24 Unused React assets
- Removed the unused React, React DOM, React Flow, JSX runtime shim and React Flow CSS assets, plus their five shared-template references. Existing framework and app source searches found no dependent implementation; other frontend libraries remain unchanged.
- Verified source/runtime synchronization and absence of remaining runtime React references. Browser checks against the local runtime covered login-form Ajax rendering and public-form required-field validation without saving records: zero React requests, JavaScript exceptions or HTTP errors. Authenticated management screens were not verified because framework login credentials were unavailable.

## 2026-09-19 Standard Screen display mode
- Added the WEB panel's 「標準画面レスポンシブ」 selector: 0 keeps responsive lists/search/header; 1 preserves desktop tables/search/header and the 800px management minimum width on narrow screens. Missing/invalid values default to 0. Existing viewport settings and shared input controls remain intact; the shared header choice also applies while viewing custom management screens.
- Normalized setting saves, CLI edits and initial setup consistently. Gated mobile CSS by the management body's display mode and made manual-sort helper sizing follow the actual table/card layout.
- Verified old-format setting migration with every existing field preserved, persistence of both modes, PHP syntax and generated-setting-file regression. Browser checks covered UI save/reload, 375/700/701/1280px, normal/manual-sort lists, search redraw, horizontal scrolling on/off, edit/password dialogs, menu, logout, and real drag persistence followed by resizing. Mobile responsive width measured 375px without overflow; desktop mode retained 800px. Test settings were restored and fixture definitions/data removed; source and test runtime match.
- Released `a603e20` to the configured production framework targets. Set app-daitomiraku to desktop mode and verified the saved value, unchanged other settings and the live shared CSS against source.

## 2026-09-18 Error response privacy
- Removed exception messages, internal paths, stack traces and detail controls from shared page/Ajax and PDF error responses, including report failure and unconfigured reporting. Error IDs, status links and management-side reporting remain available; PHP display_errors is disabled in both entrypoints.
- Verified PHP syntax, nine response scenarios, and browser rendering/status-link navigation using the synchronized test code.

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
