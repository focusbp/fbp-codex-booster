# Admin PDF Delivery

管理側PDFの標準サンプル。通常の管理ログイン下で、取得ダイアログ → `download-link` によるPOST → `create_pdfmaker()` による帳票生成 → `download_pdf()` の応答を一組で提供する。

## Scope and installation

- `assets/admin-pdf-delivery/pdf_delivery_admin/pdf_delivery_admin.php` と `Templates/download.tpl` / `error.tpl` を実際に読み、コピー・適応する。manifestは `admin-pdf-delivery.json`。
- 架空の2帳票（A:4,000円、B:6,000円）の単票・一括・選択取得。DB定義、顧客データ、外部サービスは不要。業務データを書き換えない。
- `php scripts/install_admin_pdf_delivery.php <app-root>` で `classes/app` のある編集元に配置する。既存 `pdf_delivery_admin` は上書きしない。環境所定の方法で実行環境へ同期する。
- 管理側の `ajax-link` または `db_additionals` からクラス `pdf_delivery_admin`、関数 `run` を呼ぶ。ダウンロード先は `single` / `bulk`。CLIの `app_call` は `run` の応答を補助確認できる。
- 初期配置ではメニュー・DB・ボタン登録を自動変更しない。付属ブラウザー検証は管理ログイン後に通常の `appcon()` 経路でダイアログを開き、実ボタンをクリックする。

## Adaptation rules

- PHPの `DOCUMENTS` を業務DB取得へ置き換える。表示時・取得時の両方で対象と権限を確認し、取得時は全対象を再取得して発行可否を確認する。POSTされたIDは検索条件であり、権限を証明しない。金額・宛名をPOSTから受け取らない。
- 管理ログインを無効化しない。公開用クラスへ移動しない。顧客ごとの閲覧制限があれば、管理ログインに加えて業務権限を確認する。
- 単票と一括で同じ生成処理を使い、帳票ごとに `addPage()`、最後に一度だけ `download_pdf()` を呼ぶ。`create_pdf()` の捕捉や一時保存・削除をアプリに追加しない。
- `data-class` は実クラス名、`data-form` は対象フォームのID、`data-filename` はサーバーが決めるAPI引数と揃える。`data-open_new_tab="true"` を維持する。
- 未選択・不正IDは400のHTML案内で拒否する。直接取得のPOSTへ通知JSONを返さない。認可失敗は業務に合わせて403等にする。既存帳票移行では本文・金額・ページ数・描画結果を比較する。

## Verification

`scripts/verify_admin_pdf_delivery.cjs` をPlaywright環境で実行する。`pdftotext` と `pdfinfo` が必要。

| 環境変数 | 内容 |
| --- | --- |
| `ADMIN_PDF_URL` | 対象アプリの管理入口URL |
| `PDF_TEST_OUTPUT_DIR` | 取得PDFの出力ディレクトリ |
| `APP_LOGIN_ID` / `APP_PASSWORD` | 管理ログイン。スクリプトやdocsへ固定しない |
| `PDF_STORAGE_STATE` | 任意。ログイン済みPlaywright状態ファイル |
| `PDF_BROWSER_PATH` | 任意。Chromium実行ファイル。既定はシステムChrome |

テストゲートウェイを使う環境では、その環境のPlaywright起動ラッパー経由でHTTP Basic認証を渡す。管理ログインとは別の認証。

検証項目:

- 幅1440px・390pxで実ダイアログ内の単票A/B・一括2ページ・選択1ページを取得。
- HTTP 200、`application/pdf`、attachment、日本語ファイル名、no-store、PDF解析、帳票番号・宛名・金額・ページ数。
- 未選択・不正IDは400のHTML。別セッションで同じPOSTを再送してもPDFを取得できない。
- PHP lint、manifest解析、配置、既存クラス上書き拒否、CLIのダイアログ応答。保存・例外・exit時削除の共通API検証は `tests/pdfmaker_output_test.php` を参照。

ブラウザー幅390pxでの検証はSafari実機確認を意味しない。付属テストの架空本文の期待値は、実帳票へ適応するときに業務データへ置き換える。

2026-09-26: 独立した管理クラスとして配置し、CLIダイアログ応答、上書き拒否、Chromiumの幅1440/390pxで上記全項目を確認。取得した8ファイルのPDF解析に成功し、一時管理PDFの残存なし。単票の描画結果も確認した。検証配置は完了後に削除した。
