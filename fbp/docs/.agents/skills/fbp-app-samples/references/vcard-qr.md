# vCard入りQR付きPDFテンプレート

`assets/vcard-qr/` は架空の連絡先をvCard 3.0へ変換し、QRと氏名等をPDFへ印刷する。DB・外部APIは不要。PDF取得経路は `fbp-pdf` の管理側標準に従う。

```sh
php <skill-dir>/scripts/install_feature_template.php vcard-qr <app-source-root>
```

同期後、管理Ajaxで `vcard_qr_sample/run` を開き、「PDFをダウンロード」を操作する。通常の管理ログインを維持する。`document($ctl, $contact)` は生成だけを行い、`download()` が `download_pdf()` で応答する。

- 入力は `name` 必須、`family_name`・`given_name`・`phone`・`email`・`organization`・`note` が任意。姓がなければ表示名をNの姓成分へ使う。実データでは姓・名を明示する。
- [RFC 2426](https://www.rfc-editor.org/rfc/rfc2426) に沿ったvCard **3.0**のサンプルであり、最新のvCard 4.0実装ではない。VERSION/FN/N、CRLF、テキストの改行・バックスラッシュ・セミコロン・カンマのエスケープ、UTF-8を分断しない75オクテット折返しを備える。
- 業務データへ適用するときは `download()` 内でレコードを取得し直し、閲覧・印刷権限を確認する。POSTされた氏名・電話を正本にせず、管理ログインを外さない。QRに連絡先そのものが入るため、印刷・配布する項目は利用目的に合わせて限定する。
- QRは45mm・余白を保ち、1500バイトを超える連絡先は短縮を促す。上限以内でも情報量・印刷品質・端末により読取りや連絡先インポートが異なる。実データを無断で外部QRサービスへ送らない。

検証: `scripts/test_feature_templates.php <framework-fbp-root>` で制御文字・改行注入・日本語折返しを確認。3件を配置したテストアプリ用の `scripts/verify_feature_templates.cjs` は環境変数 `FEATURE_TEST_URL / FEATURE_TEST_OUTPUT_DIR / APP_LOGIN_ID / APP_PASSWORD`（または `FEATURE_STORAGE_STATE`）を受け、実ボタンから取得し、PDFヘッダー・氏名・未ログイン拒否を確認、`pdftotext / pdftoppm` で解析・画像化する。QRリーダーで復号結果を `sample_vcard_builder::build()` と照合する。スマートフォンの連絡先登録確認は端末を用意できた場合に別途行い、QR復号だけで登録互換性を保証しない。
