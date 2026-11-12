# PPTX → PDF

`lib/PptxToPdf.php` の `PptxToPdf::convert()` はLibreOffice ImpressでPPTXを変換し、PDFバイナリを返す。HTTP応答や永続保存は行わない。

```php
require_once $ctl->dirs->appdir_fw . '/../lib/PptxToPdf.php';
$pdfBytes = PptxToPdf::convert($pptxPath, $ctl->get_temp_dir(), 60);
```

- 実行環境にLibreOffice Impress、GNU timeout、fontconfig、日本語フォント、PHP zip拡張、利用可能なproc_openが必要。導入パッケージはサーバー構築手順で管理する。
- 入力は読取り可能なローカルPPTX。保存時に拡張子が変わったファイルも処理できる。入力は100 MiB以下、ZIP内の展開サイズは512 MiB以下、エントリ数は20,000以下。暗号化ファイルは非対応。
- タイムアウトは1〜300秒（既定60秒）。作業領域とLibreOfficeプロファイルは変換ごとに分離し、マクロ実行を無効にする。成功・例外・リクエスト終了時に一時領域を削除する。
- 失敗は例外で返す。LibreOfficeの終了コードだけで成功とせず、出力とPDFヘッダーも検査する。返却バイナリはAjax JSONへ直接混ぜない。
- PPTXと生成PDFの閲覧権限・認証・保存・配信は呼出元で実装する。管理用プレビューは認証付き取得経路を使う。
- フォント・図形・配置などはPowerPointとの完全一致を保証しない。実際のテンプレートで表示を確認する。外部リンク先の取得を完全に遮断するOSサンドボックスは提供しない。

専用検証アプリの `tests/pptx_pdf_test.php` で日本語・画像・2ページ、入力保持、並列変換、不正入力、変換タイムアウト、作業領域の削除を確認する。
