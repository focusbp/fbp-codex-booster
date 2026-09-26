---
name: fbp-pdf
description: Implement and repair FBP PDF display/download flows by applying the bundled delivery sample, including authenticated public invoices, broken download links, tpl-less generation, and media inclusion.
---

# fbp-pdf

## trigger conditions
- PDF出力機能を追加・修正する
- 新方式PDF（tplなし）で実装する
- ユーザーから印刷機能の実装を依頼される

## workflow
1. 出力要件とデータ取得元を確定。
2. 新規の帳票生成は管理側・公開側とも `$ctl->create_pdfmaker()` でオブジェクトを作り、`addText()` / `addTable()` 等で組み立てる。
3. 管理側の標準は、`ajax-link` または `db_additionals` の入口 → `show_multi_dialog()` で取得条件・対象を表示 → ダイアログ内の `download-link`（`data-open_new_tab="true"`）でPOST → `$pdf->download_pdf($filename)`。このダイアログは取得操作用であり、Controllerの `show_pdf()` によるテンプレートプレビューとは別のもの。
4. 新規または取得経路の修正では、下記「サンプル適用」を必ず実施する。公開側の直接取得はサンプルの通常GETリンク → `$pdf->download_pdf($filename)` を標準とする。管理側のPOSTは標準であり、例外理由の記録は不要。各標準から変更する場合は要件と理由を記録する。
5. 認証付き帳票は、本人確認・対象帳票・発行可否を表示時と取得時の両方で確認する。正常系だけでなく、サンプルの認証・異常系の検証を適用する。
6. CLIで応答・生成内容を補助確認する。`app_call` の `ok:true`、保存先が `.pdf`、ダイアログJSONが返ることだけではPDF取得成功と判定しない。
7. 新規のPDF表示・取得機能、表示ダイアログのデザイン、取得・認証経路を変更した場合は `fbp-playwright` に従い、実ボタンからPDF取得まで検証する。表示方式はダイアログとその後のPDF応答、直接方式はダウンロードまたは別タブのPDF応答を確認する。既存帳票の内容・計算・帳票内レイアウトだけの変更で取得経路に影響しない場合は、生成ファイルの内容・見た目を確認し、Playwrightの再実行は不要。
8. 生成・取得したPDFの `%PDF-`、PDF解析と期待する金額・件名等を確認する。HTTP経路を検証する場合はContent-Typeも確認する。新規経路は対象のPC・スマートフォン、既存変更は影響する端末・分岐に絞る。認証経路の新設・変更時はセッション切れ・権限不一致も確認する。Playwrightが必要な変更で実行できない場合は未検証範囲と理由を報告し、CLIだけで完了扱いにしない。

## サンプル適用（新規・取得経路の修正で必須）

- 管理側では **fbp-app-samples 配下**の `references/admin-pdf-delivery.md`、`assets/admin-pdf-delivery/pdf_delivery_admin/pdf_delivery_admin.php` と `Templates/download.tpl` / `error.tpl`、`scripts/verify_admin_pdf_delivery.cjs` を実際に読む。ダイアログと出力関数を一組でコピー・適応し、固定本文を業務データへ置き換え、取得時の権限確認と実ボタン検証を適用する。配置には `scripts/install_admin_pdf_delivery.php` を使える。
- 公開側では同referenceに加え、`assets/pdf-delivery/public_pages/public_pages.php` と採用する方式の `.tpl`、`scripts/verify_pdf_delivery.cjs` を **fbp-app-samples 配下から実際に読む**。認証付きの場合は `pdf_delivery_access.php` と `scripts/test_pdf_delivery.php` も読む。説明の参照だけで適用済みにしない。
- 該当方式のコードを出発点としてコピー・適応する。既存 `public_pages` 全体は上書きせず、必要な関数・テンプレートを統合する。取得方式・認証チェック・エラー応答・後片付け・検証を一組で適用する。帳票本文だけの変更には取得経路の移行を要求しない。
- 認証付き公開帳票は `protected_page` / `protected_download` の方式を使い、本人確認とDB取得のフックを実装する。未実装フックは拒否を維持する。公開固定データ用の `download()` を私有帳票に流用して認証を省略しない。
- アプリ固有の帳票生成API等で一部を変更する場合も、サンプルとの対応箇所・変更理由・同等性を確認した検証を記録する。「既存実装だから」「一度PDFが開いたから」だけでは方式を維持する理由にしない。サンプルに不足が判明した場合は、案件内だけの独自方式を増やさず、共通サンプルへの改善点を明示する。
- 不具合修正では変更前の失敗と変更後の成功を同じ操作条件で比較する。再現できない場合、予防的改善と原因修正を区別し、正常PDFを取得できただけで修正完了にしない。未検証のブラウザー・実際のメール入口等を明記する。

## PDFオブジェクトの出力

```php
$pdf = $ctl->create_pdfmaker();
$pdf->addText('請求書');
// 内容を組み立てた後、用途に応じて一つを呼ぶ。
$pdf->download_pdf('請求書.pdf'); // PDF本体を返し、応答を終了
// $bytes = $pdf->get_pdf_data(); // ヘッダー・本文を送らずPDFバイナリを取得
// $pdf->create_pdf();           // 既存互換のブラウザー内表示
```

- 直接取得の標準は `download_pdf()`。管理ファイルの一意な名前、初回保存領域の作成、PDFヘッダー、キャッシュ抑止、成功・例外・exit時の削除は内部で処理する。アプリ側に出力バッファ・一時保存・削除処理を再実装しない。
- `get_pdf_data()` はメール添付・複数帳票の加工等に使う。`create_pdf()` を `ob_start()` で捕捉しない。返るバイナリをAjax JSONへ混ぜない。
- 認証・発行可否の確認は呼び出し前にアプリ側で行う。`download_pdf()` は `Controller::create_pdfmaker()` で作成したオブジェクトで使い、`res_saved_file()` と同じく通常はexitする。
- `create_pdf()` と Controllerの `show_pdf()` / `save_pdf()` は既存互換用。新規の標準には選ばない。テンプレートによる画面内プレビューが明示要件の場合は `show_pdf()` を使える。既存帳票の本文だけの修正で方式を強制移行しない。新APIを使うアプリは、対応するフレームワークを先に反映してからリリースする。

## table samples
- 既存 `apppdf.php` のスマートフォン保存は `application/x-download` を返す場合がある。この既知の経路だけ許容し、取得した実ファイルのPDF解析と内容確認は省略しない。
- `addTable()` の列幅指定は `columnsize`、列ごとの寄せ指定は `columnalign` を使う。`aligns` ではない。
- `columnsize` は `%` 扱いなので合計 `100` にする。
- 数値列を右寄せしたい場合の例:
```php
$pdf->addTable($table, [
    "margintop" => 10,
    "columnsize" => [20, 50, 30],
    "columnalign" => ["L", "L", "R"],
]);
```
- 右寄せが効かないときは、まず `columnalign` というキー名になっているかを確認する。

## Code 39 barcode

- `addCode39($code, ["x" => 8, "y" => 10, "width" => 44, "height" => 7.3, "wide_ratio" => 2, "show_text" => false])` specifies bar width and height in mm.
- `width` must be positive and measures the first bar's left edge to the last bar's right edge. Quiet zones and printed code text are excluded; reserve quiet zones in the surrounding layout.
- `width` takes precedence over `baseline`. If omitted, the existing `baseline` behavior is preserved.
- With `barcode_align => "R"` and an explicit width, `x` is the last bar's right edge.

## Fixed text boxes

Use `addTextBox()` for text that must stay inside an absolute rectangle:

```php
$pdf->addTextBox($memo, [
    "x" => 10, "y" => 29.5, "width" => 40, "height" => 6, // mm
    "fontsize" => 5, "fontname" => "gothic", // font size in pt
    "lineheight" => 2, "padding" => 0, // mm; padding applies to all four sides
    "align" => "L", "valign" => "top", "overflow" => "ellipsis",
]);
```

- Pass the full text; do not pre-wrap by character count. The box measures glyph widths in the selected font and wraps Japanese/Latin text, preserving explicit newlines. `wrap => false` disables automatic wrapping.
- `x`, `y`, positive `width` and `height` are required. Padding must leave a positive inner rectangle. Unlike `addText()`, no implicit cell padding is added.
- `align`: `L`/`C`/`R`; `valign`: `top`/`middle`/`bottom`.
- `overflow`: `clip` (default, hides everything outside the inner rectangle), `ellipsis` (whole lines with a final ellipsis), `shrink` (reduce the font), or `error` (throw `OverflowException` before drawing the box).
- `clip` with `complete_lines => true` omits vertically incomplete lines; horizontal overflow is still clipped.
- `shrink` uses `min_fontsize` (default the smaller of 6pt and the starting size). At that limit, `shrink_overflow` selects `ellipsis` (default), `clip`, or `error`.
- `lineheight` is the baseline advance at the starting font size and scales during shrinking. It must accommodate the selected font's ascent/descent; omitted values are calculated from the font size and metrics.
- A box does not advance the flow cursor or automatically add pages. Subsequent fields retain their positions. Explicit `addPage()` still works. All modes apply a PDF clipping rectangle as a final containment guard.
- Existing `addText()` behavior is unchanged. Deploy the supporting framework before deploying an app that calls this API.

## constraints
- ユーザーからの印刷機能の実装は、HTMLの印刷ではなく必ずフレームワークのPDF出力機能を使用する。
- 文字化け・画像パス・ページ崩れを優先チェックする。
- PDF本文で日付/日時/年月を PHP 直書きする場合は `$ctl->create_ValueFormatter()` を使う。HTML 表示 helper の代替としては使わない。
- `show_pdf()` の入口は `ajax-link` を使う。`show_pdf()` はダイアログJSONを返すため、通常リンクや `download-link` から直接呼ばない。
- PDF本体を直接返す関数の入口には `ajax-link` を使わない（ダウンロードデータを扱えないため）。
- `download-link` の `data-class` は明示的に実クラス名を指定する（`{$class}` 依存を避ける）。
- PDFダウンロードの `download-link` は `data-open_new_tab="true"` を基本とする。例外時は理由を実装コメントかPR説明に残す。
- `addTable` の `columnsize` は合計 `100` にする（%指定として扱うため）。
- `addText()` などで安易に `bold => true` を使わない。既定フォントでは `Undefined font` になることがあるため、太字が必要な場合は `migmix-1p-bold` など登録済みの太字フォントを `fontname` で明示する。
- PDF生成や `pdfunite` / `zip` などでアプリ独自の作業ファイルを作る場合は `fbp-temp-files` に従い `$ctl->get_temp_dir()` を使う。`save_pdf()` / `res_saved_file()` を直接使う場合、管理ファイルは各API所定のアップロード領域を使い、一時用途なら応答後・例外時・exit時に削除する。`download_pdf()` を使う場合は内部で削除するため、アプリ側の削除処理は不要。保存先をアプリ側で固定パスにしない。
