// Run through the environment's Playwright entrypoint. Requires pdftotext.
const { chromium } = require('playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');

async function verifyPdf(file) {
  const bytes = await fs.readFile(file);
  assert.equal(bytes.subarray(0, 5).toString(), '%PDF-');
  const text = execFileSync('pdftotext', [file, '-'], { encoding: 'utf8' });
  for (const expected of ['請求書', '4,000', '懇親会費', 'サンプル株式会社']) {
    assert.ok(text.includes(expected), `PDF missing ${expected}`);
  }
}

function waitDownload(context) {
  return new Promise((resolve, reject) => {
    const pages = new Set();
    const finish = (error, download) => {
      clearTimeout(timer);
      context.off('page', attach);
      for (const page of pages) page.off('download', received);
      error ? reject(error) : resolve(download);
    };
    const received = download => finish(null, download);
    const attach = page => { pages.add(page); page.on('download', received); };
    const timer = setTimeout(() => finish(new Error('PDF download was not received')), 15000);
    context.pages().forEach(attach);
    context.on('page', attach);
  });
}

(async () => {
  const url = process.env.PDF_SAMPLE_URL;
  const out = process.env.PDF_TEST_OUTPUT_DIR;
  assert.ok(url && out, 'Set PDF_SAMPLE_URL and PDF_TEST_OUTPUT_DIR');
  await fs.mkdir(out, { recursive: true });
  const browser = await chromium.launch({ headless: true,
    ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE } : {}),
    args: ['--no-sandbox'] });
  try {
    for (const mobile of [false, true]) {
      const label = mobile ? 'mobile' : 'desktop';
      const options = { acceptDownloads: true, viewport: mobile ? { width: 390, height: 844 } : { width: 1440, height: 900 } };
      if (mobile) Object.assign(options, { isMobile: true, hasTouch: true,
        userAgent: 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/130.0.0.0 Mobile Safari/537.36' });
      if (process.env.PDF_STORAGE_STATE) options.storageState = process.env.PDF_STORAGE_STATE;
      if (process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME) options.httpCredentials = {
        username: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME,
        password: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD,
        origin: new URL(url).origin };
      const context = await browser.newContext(options);
      try {
        const page = await context.newPage();
        page.setDefaultTimeout(10000);
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        // Listen before clicking: show_pdf first returns JSON, then apppdf returns PDF.
        const responsePromise = context.waitForEvent('response', {
          predicate: r => new URL(r.url()).pathname.endsWith('/apppdf.php'), timeout: 15000 });
        // Avoid an unhandled rejection if the actual button is missing.
        responsePromise.catch(() => {});
        const mobileDownload = mobile ? waitDownload(context) : null;
        if (mobileDownload) mobileDownload.catch(() => {});
        await page.locator('#pdf-preview').click();
        if (mobile) await page.locator('a.pdf_downloadbutton').click();
        else await page.locator('iframe[src*="apppdf.php"]').waitFor({ state: 'visible' });
        const response = await responsePromise;
        assert.ok(response.ok(), 'Preview HTTP error');
        // Existing apppdf.php smartphone attachment uses tFPDF's x-download MIME.
        const previewMime = mobile ? /^application\/(pdf|x-download)(;|$)/i : /^application\/pdf(;|$)/i;
        assert.match(response.headers()['content-type'] || '', previewMime);
        // Browser PDF viewers may not expose response bodies; retrieve the observed
        // PDF URL with the same cookies after verifying the actual click/response.
        const preview = path.join(out, `${label}-preview.pdf`);
        if (mobile) {
          const download = await mobileDownload;
          assert.equal(await download.failure(), null);
          await download.saveAs(preview);
        } else {
          const fetched = await context.request.get(response.url());
          assert.ok(fetched.ok());
          assert.match(fetched.headers()['content-type'] || '', previewMime);
          await fs.writeFile(preview, await fetched.body());
        }
        await verifyPdf(preview);
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        const directUrl = await page.locator('#pdf-download').evaluate(el => el.href);
        const [download, directResponse] = await Promise.all([
          page.waitForEvent('download'),
          page.waitForResponse(r => r.url() === directUrl),
          page.locator('#pdf-download').click()]);
        assert.ok(directResponse.ok());
        assert.match(directResponse.headers()['content-type'] || '', /^application\/pdf(;|$)/i);
        assert.equal(await download.failure(), null);
        assert.equal(download.suggestedFilename(), 'sample-invoice.pdf');
        const direct = path.join(out, `${label}-direct.pdf`);
        await download.saveAs(direct);
        await verifyPdf(direct);
        console.log(`${label}: preview and direct PDF passed`);
      } finally { await context.close(); }
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
