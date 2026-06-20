---
name: fbp-playwright
description: Verify FBP application screens with Playwright browser automation. Use when Codex needs to take screenshots, inspect DOM/layout metrics, login to an FBP test app, reproduce UI interactions, or debug visual behavior in a browser without re-discovering local Playwright setup.
---

# fbp-playwright

## Core rule

Use `~/scripts/playwright_node.sh` as the Playwright entrypoint. Do not start with raw
`node`, `npx playwright test`, or `@playwright/test`; this environment may only have the
`playwright` package inside the npx cache, and the wrapper sets `NODE_PATH` correctly.
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
3. Pass login values through environment variables. Do not write passwords, API keys, cookies,
   or generated session details into scripts, docs, or final answers.
4. Save screenshots under `/home/nakama/Screenshot/<appcode>/`, using clear filenames.
5. Use `page.screenshot({ path, fullPage: true })` by default.
6. Always print relevant DOM metrics for layout bugs, such as rendered width, `data-*`
   attributes, `scrollWidth`, and `clientWidth`.
7. Keep any temporary Playwright script or test output under `~/scripts/tmp/...`, never under
   `~/` directly.
8. Remove temporary Playwright scripts after use unless the user explicitly asks to keep them.

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
  const browser = await chromium.launch({
    headless: true,
    executablePath: "/usr/bin/google-chrome",
    args: ["--no-sandbox"]
  });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(20000);
  await page.goto(process.env.APP_TEST_URL, { waitUntil: "networkidle" });

  if (await page.locator('input[name="login_id"]').count()) {
    await page.fill('input[name="login_id"]', process.env.APP_LOGIN_ID || "");
    await page.fill('input[name="password"]', process.env.APP_PASSWORD || "");
    await Promise.all([
      page.waitForURL(/class=base|base\*page|app\.php/, { timeout: 20000 }).catch(() => null),
      page.click("#login_form button")
    ]);
  }

  await page.screenshot({ path: out, fullPage: true });
  await browser.close();
  console.log(JSON.stringify({ ok: true, out }, null, 2));
})();
```

## Verification

After running, inspect the image with `view_image` when visual quality matters. Include the
absolute screenshot path and the key measured metrics in the final response.
