# PDFビューアーからの保存

別タブ・iframeでPDFを表示した後、利用者がビューアーの保存ボタンを使う方式に適用する。通常のattachment取得は既存のdownloadイベント検証を使う。

## 再現と対処

ChromeのPOST直接inline表示で、日本語の `Content-Disposition: inline; filename="contract.pdf"; filename*=UTF-8''...` が正しくても、ビューアー保存の `suggestedFilename()` が `app.php` となり、`saveAs()` もcanceledになる現象を確認した。POSTから303で認証付きGETへ移し、GETで現在版から同じPDFとファイル名を生成すると、ビューアー保存で日本語の契約書名と版番号が引き継がれた。

これはPOSTのattachment応答を禁止するものではない。inline表示を必要とする場合の対処とし、GETは通常の管理ログイン・権限・対象確認を必須にする。公開用の認証なしエンドポイントへ移さない。

## Playwrightでの確認例

アプリへのログインと取得ダイアログの表示後、実ボタンをクリックする。`context`、`page`、`pdfButton`、`expectedFilename`、`outputPath` は対象に合わせる。レスポンス待受はPDF以外の通信を拾わない条件に絞る。

```js
const assert = require('node:assert/strict');
const { readFile } = require('node:fs/promises');
const { execFileSync } = require('node:child_process');

const responsePromise = context.waitForEvent('response', {
  predicate: r => (r.headers()['content-type'] || '').startsWith('application/pdf'),
  timeout: 20000,
});
const popupPromise = page.waitForEvent('popup', { timeout: 20000 });
await pdfButton.click();
const [response, popup] = await Promise.all([responsePromise, popupPromise]);
assert.equal(response.status(), 200);
assert.match(response.headers()['content-disposition'], /^inline/);
await popup.waitForLoadState('load');

// Chromeの内蔵PDFビューアーは拡張機能フレーム内にある。
await popup.waitForFunction(() => document.readyState === 'complete');
let viewer;
const deadline = Date.now() + 10000;
while (Date.now() < deadline) {
  viewer = popup.frames().find(f => f.url().startsWith('chrome-extension://'));
  if (viewer) break;
  await popup.waitForTimeout(100);
}
assert.ok(viewer, 'Chrome PDF viewer frame unavailable');
const save = viewer.locator('#save'); // Chromeの版で異なる場合は実DOMの保存ボタンを調べる
await save.waitFor({ state: 'visible', timeout: 10000 });
const downloaded = popup.waitForEvent('download', { timeout: 10000 });
downloaded.catch(() => {}); // クリック失敗時に待受の未処理rejectを残さない
await save.click();
const file = await downloaded;
assert.equal(file.suggestedFilename(), expectedFilename);
await file.saveAs(outputPath);
assert.equal(await file.failure(), null);
assert.equal((await readFile(outputPath)).subarray(0, 5).toString(), '%PDF-');
execFileSync('pdfinfo', [outputPath]);
const text = execFileSync('pdftotext', [outputPath, '-'], { encoding: 'utf8' });
// 対象帳票の件名・金額等をtextで照合する。
```

繰り返し取得の修正では同じダイアログを保持してもう一度実行し、各回の名前と本文を確認する。最新内容を返す仕様を変更した場合は、ダイアログ表示後に対象を更新した条件でも最新の名前と本文になることを確認する。

Chromiumの結果をSafari実機等の確認済みとして扱わない。内蔵ビューアーを操作できない場合は、その未確認範囲を報告する。ヘッダーだけの確認に置き換えて保存名が正しいと判定しない。
