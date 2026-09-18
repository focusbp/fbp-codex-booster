---
name: fbp-playwright
description: Verify new FBP screens and user operations, visual changes, and changed browser interactions with Playwright. Use for layout checks, UI reproduction, and download flows; do not run routinely for content-only or backend-only changes without browser impact.
---

# fbp-playwright

## Core rule

### When to run

変更したファイルの種類ではなく、利用者の画面・操作への影響で判断する。

- 新規の画面・ユーザー操作を追加した場合は、Playwrightで入口から完了までの代表的な操作を確認する。
- 位置・色・大きさ・レスポンシブ対応などのデザイン変更は、影響する画面をPlaywrightで確認する。
- ボタン、画面遷移、別タブ、ダウンロードなどの操作経路を変更した場合は、その実操作を確認する。
- 計算・データ変更でも、結果によってボタンの表示や遷移先などが変わる場合は、影響する操作を確認する。
- 画面を持たないAPI・バッチ、画面操作に影響しない計算・保存・検索条件・DB定義は、CLIや処理テストを基本にする。
- 文言・帳票の住所や金額など内容だけの変更は出力内容を確認し、原則Playwrightは不要。文言変更で折返しや配置に影響する場合は見た目も確認する。
- Skill・手順書だけの変更は差分・整合性を確認する。明示的なブラウザー検証依頼やブラウザー固有の不具合調査は、その依頼に従う。
- 確認は変更箇所と直接影響する範囲に絞る。毎回全機能・全端末・全異常系を実行しない。検証後に対象コード・設定が変わっておらず、新たな懸念もなければ同じ検証を繰り返さない。低影響の見た目変更をユーザーが確認済みなら、その確認を採用する。

Use `~/scripts/playwright_node.sh` as the Playwright entrypoint. Do not start with raw
`node`, `npx playwright test`, or `@playwright/test`; this environment may only have the
`playwright` package inside the npx cache, and the wrapper sets `NODE_PATH` correctly.
The wrapper also exports the current rotated test-gateway Basic-auth values as
`PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME` and `PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD`, and keeps them
stable for the lifetime of the browser process.
Do not run Playwright from `~/` in a way that creates default output directories such as
`~/test-results` or `~/playwright-report`. Put temporary scripts and artifacts under
`~/scripts/tmp/...`, and put retained screenshots under `/home/nakama/Screenshot/<appcode>/`.

Write scripts with:

```js
const { chromium } = require("playwright");
```

For Chromium, prefer the system browser when launching:

```js
const browser = await chromium.launch({
  headless: true,
  executablePath: "/usr/bin/google-chrome",
  args: ["--no-sandbox"]
});
```

## Workflow

1. Resolve app targets with the normal local rule first. For an explicit `app-xxx`, run
   `MGMT_API_MODE=production ~/scripts/sftp_api.sh get app-xxx` and use its `test_url`,
   `test_login_id`, and `test_password` values.
2. If checking source changes, sync before browser verification:
   `~/scripts/copy_to_web.sh app-xxx`.
3. Pass FBP login values through environment variables. For a `gw.soshiki-kaikaku.com` test URL,
   create a browser context with the wrapper-provided `PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME` and
   `PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD` as `httpCredentials`. Do not substitute the app's FBP
   login ID/password for HTTP Basic authentication, and do not write either credential into a
   script, manifest, doc, or final answer.
4. Save screenshots under `/home/nakama/Screenshot/<appcode>/`, using clear filenames.
5. Use `page.screenshot({ path, fullPage: true })` by default.
6. Always print relevant DOM metrics for layout bugs, such as rendered width, `data-*`
   attributes, `scrollWidth`, and `clientWidth`.
7. Keep any temporary Playwright script or test output under `~/scripts/tmp/...`, never under
   `~/` directly.
8. Remove temporary Playwright scripts after use unless the user explicitly asks to keep them.
9. Run related checks in one browser session and reuse the authenticated page. Do not repeat
   browser startup and login for each assertion or screenshot.
10. If the user visually confirms a low-risk display-only change after source-to-test sync,
    accept that confirmation and finish with source/test identity and diff checks. Do not repeat
    the same browser check unless another behavior remains unverified.

## Fast-fail rules

- Use separate timeout budgets: `20000`-`30000` ms for login/navigation and `5000`-`10000` ms
  for a specific element or Ajax result.
- After login, wait for the destination URL and page readiness before calling `appcon()`. Do not
  hide a failed login wait with `.catch(() => null)` and then spend another full timeout later.
- For tabbed dialogs, wait for the target with `state: "attached"` first. If it exists but is
  hidden, inspect its nearest `.ui-tabs-panel`, click the matching tab, and only then wait for
  `state: "visible"`.
- When a ScreenLog is available, replay its class, function, dialog target, and relevant POST
  values through `appcon()` instead of navigating menus manually.
- On the first timeout, print the current URL, locator count, visibility, nearest hidden ancestor,
  and enclosing tab ID. Change the hypothesis before retrying; do not rerun an identical
  long-timeout script.

Reusable hidden-tab handling:

```js
const target = page.locator("#target");
await target.waitFor({ state: "attached", timeout: 5000 });

if (!(await target.isVisible())) {
  const tabId = await target.evaluate((el) => el.closest(".ui-tabs-panel")?.id || "");
  if (tabId) {
    await page.locator(`a[href="#${tabId}"]`).click();
  }
}

await target.waitFor({ state: "visible", timeout: 5000 });
```

## FBP interaction pattern

Prefer real browser clicks when they are reliable. If an FBP `.ajax-link` click is blocked by
layout, viewport, animation, or side menu positioning, use the same frontend Ajax path by calling
`appcon()` in the page context with `FormData`. This avoids bypassing FBP request handling while
keeping browser verification deterministic.

Example:

```js
await page.waitForFunction(() => typeof window.appcon === "function");
await page.evaluate(() => {
  const fd = new FormData();
  fd.append("class", "db_exe");
  fd.append("function", "page");
  fd.append("db_id", "1");
  window.appcon("app.php", fd);
});
```

For child side panels, also follow `fbp-side-panel` verification points:

```js
await page.evaluate((parentId) => {
  const fd = new FormData();
  fd.append("class", "db_exe");
  fd.append("function", "rows_child");
  fd.append("db_id", "2");
  fd.append("parent_id", parentId);
  window.appcon("app.php", fd);
}, parentId);
```

## Minimal script template

Run this shape with `~/scripts/playwright_node.sh -`:

```js
const { chromium } = require("playwright");

(async () => {
  const appcode = process.env.APP_CODE;
  const out = `/home/nakama/Screenshot/${appcode}/check.png`;
  const testUrl = process.env.APP_TEST_URL;
  const browser = await chromium.launch({
    headless: true,
    executablePath: "/usr/bin/google-chrome",
    args: ["--no-sandbox"]
  });
  const contextOptions = { viewport: { width: 1440, height: 900 } };
  if (new URL(testUrl).hostname === "gw.soshiki-kaikaku.com") {
    const username = process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME || "";
    const password = process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD || "";
    if (!username || !password) {
      throw new Error("test gateway Basic-auth credentials are unavailable; use playwright_node.sh");
    }
    contextOptions.httpCredentials = { username, password, origin: new URL(testUrl).origin };
  }
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto(testUrl, { waitUntil: "domcontentloaded" });

  if (await page.locator('input[name="login_id"]').count()) {
    await page.fill('input[name="login_id"]', process.env.APP_LOGIN_ID || "");
    await page.fill('input[name="password"]', process.env.APP_PASSWORD || "");
    await Promise.all([
      page.waitForURL(/class=base|base\*page/, { timeout: 20000 }),
      page.locator('#login_form button, button[type="submit"]').first().click()
    ]);
  }
  await page.waitForFunction(() => typeof window.appcon === "function", null, {
    timeout: 10000
  });

  await page.screenshot({ path: out, fullPage: true });
  await browser.close();
  console.log(JSON.stringify({ ok: true, out }, null, 2));
})();
```

## Verification

### PDF verification

PDFの新規の表示・取得機能、表示ダイアログのデザイン、取得・認証経路を変更した場合は、Playwrightで実際の画面のボタン／リンクをクリックし、PDF取得まで確認する。この場合、CLIの `ok:true`、リンクの存在、ダイアログが開くこと、スクリーンショットだけでは完了としない。既存PDFの記載内容・計算・帳票内レイアウトだけの変更は、取得経路に影響しなければ生成ファイルの内容・見た目の確認でよく、Playwrightの再実行は不要。

- 基本の `show_pdf()` 方式: `ajax-link` をクリックし、PDF表示ダイアログと、その中のiframeまたは保存リンクから返るPDFを確認する。最初のAjax応答はJSONで正常。後続のPDF応答を検証する。
- 直接方式: `res_saved_file()` 等へ進む実リンクをクリックする。クリック前にdownload／popup／responseの待受を登録する。`download` イベントだけに限定せず、別タブやiframeにPDFが表示される場合も扱う。
- HTTP応答は成功ステータスと `Content-Type: application/pdf` を確認し、取得ファイルは先頭の `%PDF-` とPDF解析で検証する。`pdftotext` 等で金額・件名・宛名等の期待値を確認する。JSON・HTMLを `.pdf` 名で保存したものは失敗にする。
- 既存 `apppdf.php` のスマートフォン保存に限り `application/x-download` も許容する。実際のdownloadイベントから保存したファイルを解析し、Content-Typeだけで成功判定しない。
- 新規の取得経路では対象のPC・スマートフォン表示を確認する。既存経路の変更は影響する端末・分岐に絞る。認証経路の新設・変更ではセッション切れ・権限不一致で帳票を返さないことも確認する。スマートフォン模擬だけでLINEアプリ固有の動作確認済みとはしない。
- 検証用ファイルは指定の一時出力先に保存する。実行できない場合は理由と未検証範囲を報告する。

再利用可能な2方式の実装とブラウザーテストは `../fbp-app-samples/references/pdf-delivery.md` を参照。

After running, inspect the image with `view_image` when visual quality matters. Include the
absolute screenshot path and the key measured metrics in the final response.
