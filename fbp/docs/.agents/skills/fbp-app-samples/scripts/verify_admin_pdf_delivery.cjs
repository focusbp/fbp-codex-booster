// Run in a Playwright environment. Requires pdftotext and pdfinfo.
const { chromium } = require('playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');

function waitDownload(context) {
  return new Promise((resolve, reject) => {
    const pages = new Set();
    const finish = (err, file) => {
      clearTimeout(timer); context.off('page', attach);
      for (const page of pages) page.off('download', received);
      err ? reject(err) : resolve(file);
    };
    const received = file => finish(null, file);
    const attach = page => { pages.add(page); page.on('download', received); };
    const timer = setTimeout(() => finish(new Error('PDF download not received')), 20000);
    context.pages().forEach(attach); context.on('page', attach);
  });
}

(async () => {
  const url = process.env.ADMIN_PDF_URL;
  const out = process.env.PDF_TEST_OUTPUT_DIR;
  assert.ok(url && out, 'Set ADMIN_PDF_URL (management entry URL) and PDF_TEST_OUTPUT_DIR');
  await fs.mkdir(out, { recursive: true });
  const browser = await chromium.launch({headless: true, executablePath: process.env.PDF_BROWSER_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox']});
  try {
    for (const width of [1440, 390]) {
      const options = {viewport: {width, height: 1000}};
      if (process.env.PDF_STORAGE_STATE) options.storageState = process.env.PDF_STORAGE_STATE;
      if (process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME) options.httpCredentials = {
        username: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME,
        password: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD, origin: new URL(url).origin
      };
      const context = await browser.newContext(options);
      try {
        const page = await context.newPage(); page.setDefaultTimeout(10000);
        await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 30000});
        await page.waitForLoadState('load');
        if (await page.locator('input[name="login_id"]').count()) {
          assert.ok(process.env.APP_LOGIN_ID && process.env.APP_PASSWORD, 'Provide management login via environment or PDF_STORAGE_STATE');
          await page.fill('input[name="login_id"]', process.env.APP_LOGIN_ID);
          await page.fill('input[name="password"]', process.env.APP_PASSWORD);
          await Promise.all([page.waitForURL(/class=base|base\*page/, {timeout: 20000}), page.locator('#login_form button, button[type="submit"]').first().click()]);
        }
        await page.waitForLoadState('load');
        await page.locator('input[name="login_id"]').waitFor({state: 'detached'});
        await page.waitForFunction(() => typeof window.appcon === 'function');
        await page.evaluate(() => {
          const f = new FormData(); f.append('class', 'pdf_delivery_admin'); f.append('function', 'run');
          window.appcon('app.php', f);
        });
        try { await page.locator('#pdf_sample_bulk').waitFor({state: 'visible'}); } catch (e) {
          await fs.writeFile(path.join(out, 'failure.html'), await page.content());
          throw new Error('Admin dialog unavailable; inspect failure.html; loginForm=' + await page.locator('input[name="login_id"]').count());
        }
        const responseFor = fn => context.waitForEvent('response', {
          predicate: r => r.request().method() === 'POST' && (r.request().postData() || '').includes('pdf_delivery_admin') && (r.request().postData() || '').includes(fn), timeout: 20000
        });
        let authenticatedRequest;
        async function closeTabs() { for (const tab of context.pages()) if (tab !== page) await tab.close(); }
        async function download(label, selector, ids, fn) {
          const button = page.locator(selector); const expected = await button.getAttribute('data-filename');
          const dp = waitDownload(context); const rp = responseFor(fn); dp.catch(() => {}); rp.catch(() => {});
          await button.click(); const [file, response] = await Promise.all([dp, rp]);
          assert.equal(response.status(), 200); assert.match(response.headers()['content-type'], /^application\/pdf/);
          assert.match(response.headers()['content-disposition'], /^attachment/); assert.match(response.headers()['cache-control'], /no-store/);
          assert.equal(file.suggestedFilename(), expected); assert.equal(await file.failure(), null);
          const destination = path.join(out, `${width}-${label}.pdf`); await file.saveAs(destination);
          assert.equal((await fs.readFile(destination)).subarray(0, 5).toString(), '%PDF-');
          const text = execFileSync('pdftotext', [destination, '-'], {encoding: 'utf8'});
          const info = execFileSync('pdfinfo', [destination], {encoding: 'utf8'});
          assert.equal(Number(info.match(/Pages:\s+(\d+)/)[1]), ids.length);
          for (const id of ['A', 'B']) {
            assert.equal(text.includes('SAMPLE-' + id), ids.includes(id));
            if (ids.includes(id)) { assert.ok(text.includes('サンプル利用者' + id)); assert.ok(text.includes(id === 'A' ? '4,000' : '6,000')); }
          }
          const req = response.request();
          authenticatedRequest = {url: req.url(), headers: req.headers(), body: req.postDataBuffer()}; await closeTabs();
        }
        await download('single-A', '#pdf_sample_single_A button', ['A'], 'single');
        await download('single-B', '#pdf_sample_single_B button', ['B'], 'single');
        await download('bulk', '#pdf_sample_bulk button', ['A', 'B'], 'bulk');
        await page.locator('#pdf_sample_bulk input[value="B"]').uncheck();
        await download('bulk-selected', '#pdf_sample_bulk button', ['A'], 'bulk');
        await page.locator('#pdf_sample_bulk input[value="A"]').uncheck();
        for (const invalid of ['empty', 'unknown']) {
          if (invalid === 'unknown') await page.locator('#pdf_sample_bulk input').first().evaluate(el => { el.value = 'unknown'; el.checked = true; });
          const rp = responseFor('bulk'); rp.catch(() => {}); await page.locator('#pdf_sample_bulk button').click();
          const response = await rp; assert.equal(response.status(), 400); assert.match(response.headers()['content-type'], /^text\/html/);
          await closeTabs();
        }
        // Same download request in another app session must not disclose a PDF.
        const anonymousOptions = {...options}; delete anonymousOptions.storageState;
        const anonymous = await browser.newContext(anonymousOptions);
        try {
          const headers = authenticatedRequest.headers;
          const safeHeaders = Object.fromEntries(Object.entries(headers).filter(([k]) => !k.startsWith(':') && !['cookie', 'authorization', 'content-length'].includes(k)));
          const denied = await anonymous.request.post(authenticatedRequest.url, {headers: safeHeaders, data: authenticatedRequest.body});
          assert.doesNotMatch(denied.headers()['content-type'] || '', /application\/pdf/);
          assert.notEqual((await denied.body()).subarray(0, 5).toString(), '%PDF-');
        } finally { await anonymous.close(); }
        console.log(`${width}px: single A/B, bulk, selected subset, empty/unknown rejection and separate-session denial passed`);
      } finally { await context.close(); }
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exit(1); });
