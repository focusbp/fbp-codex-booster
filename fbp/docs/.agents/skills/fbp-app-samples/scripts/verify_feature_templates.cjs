// Real FBP chart rendering and management PDF download. Never contacts Vimeo.
// Env: FEATURE_TEST_URL, FEATURE_TEST_OUTPUT_DIR, APP_LOGIN_ID/APP_PASSWORD (or FEATURE_STORAGE_STATE).
const { chromium } = require('playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

(async () => {
  const url = process.env.FEATURE_TEST_URL, out = process.env.FEATURE_TEST_OUTPUT_DIR;
  assert.ok(url && out, 'Set FEATURE_TEST_URL and FEATURE_TEST_OUTPUT_DIR');
  await fs.mkdir(out, { recursive: true });
  const browser = await chromium.launch({headless: true, executablePath: process.env.FEATURE_BROWSER_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox']});
  try {
    const options = {viewport: {width: 1280, height: 1000}, acceptDownloads: true};
    if (process.env.FEATURE_STORAGE_STATE) options.storageState = process.env.FEATURE_STORAGE_STATE;
    if (process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME) options.httpCredentials = {
      username: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME, password: process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD,
      origin: new URL(url).origin
    };
    const context = await browser.newContext(options);
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 30000});
    await page.waitForLoadState('load');
    if (!process.env.FEATURE_STORAGE_STATE && process.env.APP_LOGIN_ID) {
      // The entry URL may first load a redirect shell before rendering the login form.
      await page.locator('input[name="login_id"]').waitFor({state: 'visible', timeout: 20000});
    }
    if (await page.locator('input[name="login_id"]').count()) {
      assert.ok(process.env.APP_LOGIN_ID && process.env.APP_PASSWORD, 'Provide login through environment');
      await page.fill('input[name="login_id"]', process.env.APP_LOGIN_ID);
      await page.fill('input[name="password"]', process.env.APP_PASSWORD);
      await Promise.all([page.waitForURL(/class=base|base\*page/, {timeout: 20000}), page.locator('#login_form button,button[type="submit"]').first().click()]);
    }
    await page.waitForLoadState('load');
    await page.waitForFunction(() => typeof window.appcon === 'function');
    async function invoke(cls, fn) {
      await page.evaluate(([cls, fn]) => {
        const data = new FormData(); data.append('class', cls); data.append('function', fn);
        window.appcon('app.php', data);
      }, [cls, fn]);
    }
    await invoke('advanced_chart_sample', 'run');
    await page.locator('canvas[id^="trend_"]').waitFor({state: 'visible'});
    await page.waitForFunction(() => [...document.querySelectorAll('canvas[id^="mixed_"],canvas[id^="trend_"]')].length === 2
      && [...document.querySelectorAll('canvas[id^="mixed_"],canvas[id^="trend_"]')].every(el => window.Chart?.getChart(el)));
    const charts = await page.locator('canvas[id^="mixed_"],canvas[id^="trend_"]').evaluateAll(els => els.map(el => {
      const chart = window.Chart.getChart(el);
      return {id: el.id, width: el.width, height: el.height, axes: Object.keys(chart.scales),
        datasets: chart.config.data.datasets.map(d => ({type: d.type, axis: d.yAxisID, data: d.data}))};
    }));
    assert.equal(new Set(charts.map(c => c.id)).size, 2);
    assert.ok(charts.every(c => c.width > 0 && c.height > 0));
    assert.ok(charts[0].axes.includes('sales') && charts[0].axes.includes('count'));
    assert.equal(charts[1].datasets[1].data.length, 8);
    await page.evaluate(() => {
      for (const el of document.querySelectorAll('canvas[id^="mixed_"],canvas[id^="trend_"]')) {
        const chart = window.Chart.getChart(el); chart.stop(); chart.update('none');
      }
    });
    await page.screenshot({path: path.join(out, 'advanced-charts.png'), fullPage: true});
    await invoke('vcard_qr_sample', 'run');
    const button = page.locator('.download-link[data-class="vcard_qr_sample"]');
    await button.waitFor({state: 'visible'});
    const download = new Promise((resolve, reject) => {
      const watched = new Set();
      const timer = setTimeout(() => { cleanup(); reject(new Error('No PDF download')); }, 20000);
      const received = d => { cleanup(); resolve(d); };
      const watch = p => { watched.add(p); p.on('download', received); };
      function cleanup() { clearTimeout(timer); context.off('page', watch); for (const p of watched) p.off('download', received); }
      context.pages().forEach(watch); context.on('page', watch);
    });
    await button.click();
    const file = await download;
    const pdf = path.join(out, 'vcard-qr.pdf'); await file.saveAs(pdf);
    assert.ok((await fs.readFile(pdf)).subarray(0, 5).toString() === '%PDF-');
    const text = execFileSync('pdftotext', [pdf, '-'], {encoding: 'utf8'});
    assert.ok(text.includes('サンプル') && text.includes('taro@example.invalid'));
    execFileSync('pdftoppm', ['-scale-to', '1800', '-singlefile', '-png', pdf, path.join(out, 'vcard-qr')]);
    await invoke('vimeo_video_template', 'upload');
    await page.getByText('対象がないか、操作が許可されていません。', {exact: true}).waitFor({state: 'visible'});
    assert.equal(await page.locator('#vimeo_upload_form').count(), 0);
    const anonymous = await browser.newContext({httpCredentials: options.httpCredentials});
    try {
      const response = await anonymous.request.post(url, {form: {class: 'vcard_qr_sample', function: 'download'}});
      assert.ok(!(response.headers()['content-type'] || '').includes('application/pdf'));
      assert.notEqual((await response.body()).subarray(0, 5).toString(), '%PDF-');
    } finally { await anonymous.close(); }
    console.log(JSON.stringify({ok: true, charts, pdf, downloadedFilename: file.suggestedFilename(), anonymousPdfDenied: true,
      vimeoDefaultDenied: true, note: 'Decode vcard-qr.png separately; no Vimeo requests made.'}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
