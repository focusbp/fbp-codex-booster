# PDF Delivery

PDFの基本方式は `show_pdf()` による画面内表示。直接取得が必要な導線では `save_pdf()` + `res_saved_file()` を使う。

| 入口 | 呼び出し先 | 応答 |
| --- | --- | --- |
| `ajax-link` の「請求書を表示」 | `preview()` → `show_pdf()` | ダイアログJSON。その後 `apppdf.php` がPDFを返す |
| 通常リンクの「請求書を直接ダウンロード」 | `download()` → `res_saved_file()` | PDF本体 |

`download-link` や通常リンクから `show_pdf()` を直接呼ばない。逆にPDF本体を返す関数を `ajax-link` から呼ばない。通常の直接ダウンロードには `download-link` も利用可能だが、LINE内ブラウザーからの利用ではGETリンクを使う。

## Install

`assets/pdf-delivery/` は固定の架空データのみ。DB定義・外部サービス・認証情報は不要。実データへ応用する場合は表示・ダウンロードの両入口で利用者と対象帳票の権限を確認する。

編集元のクリーンなアプリに `php scripts/install_pdf_delivery.php <app-root>` で配置する。既存 `public_pages` は上書きしない。環境の同期手順で実行環境に反映し、`public_pages/page` を開く。

## Verify

- PHP lint、manifest解析、空の一時配置先へのインストールと既存ファイル上書き拒否を確認する。
- 実行環境のCLIで `public_pages/page` と `public_pages/preview` を呼び、後者がダイアログを返すことを確認する。これはPDF取得完了の検証ではない。
- `scripts/verify_pdf_delivery.cjs` をPlaywright環境で実行する。`PDF_SAMPLE_URL` にサンプルページのURL、`PDF_TEST_OUTPUT_DIR` に生成物の保存先を渡す。既存アプリで認証が必要なら `PDF_STORAGE_STATE` を指定する。
- 実ボタンをクリックし、プレビュー後のPDF応答と直接ダウンロードをそれぞれ確認する。HTTP成功だけでは不十分。`application/pdf`、`%PDF-`、PDF解析、金額・件名・発行元を確認する。
- PCとスマートフォンで検証する。スマートフォンの `show_pdf()` はダイアログ内の保存リンクを押して取得する。
- 顧客アプリに適用するときは、セッション切れ・非公開・権限不一致でPDFを返さず案内になることも確認する。固定公開サンプルにはセッション認証を持ち込まない。

PDF解析には `pdftotext` を使用。LINEアプリ固有の保存挙動はPlaywrightによるスマートフォン模擬だけでは保証しない。

## Regression verification

2026-09-17: 独立した一時実行環境とChromiumでPC・スマートフォンの両方式を検証済み。実ボタン、後続PDF応答、保存ファイル解析と記載内容を確認した。スマートフォンの既存 `apppdf.php` は `application/x-download` を返すため、この経路のみ許容し実ダウンロードファイルを解析する。

検出能力の確認は、破棄可能な検証環境だけで `download()` 内の `res_saved_file()` を `show_pdf()` に置き換えて行う。PDFの代わりにダイアログJSONが返り、実ダウンロードが発生せずテストが失敗することを確認済み。正常版へ戻した後、両方式を再検証する。顧客環境や原本サンプルにこの故障を入れない。
