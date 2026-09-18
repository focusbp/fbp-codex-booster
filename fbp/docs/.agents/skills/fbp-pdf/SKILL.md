---
name: fbp-pdf
description: Implement and test PDF generation flows in FBP, including modern tpl-less patterns and media inclusion.
---

# fbp-pdf

## trigger conditions
- PDF出力機能を追加・修正する
- 新方式PDF（tplなし）で実装する
- ユーザーから印刷機能の実装を依頼される

## workflow
1. 出力要件とデータ取得元を確定。
2. PDFクラスを実装（必要なら画像処理含む）。
3. 基本は `ajax-link` → `show_pdf()` でPDF表示ダイアログを開く。直接ダウンロードが必要な場合は `save_pdf()` + `res_saved_file()` などPDF本体を返す処理を使う。
4. 直接ダウンロードには `download-link`（原則 `data-open_new_tab="true"`）を使う。LINE内ブラウザー向けは `fbp-public-pages` に従ってGETリンクを使う。
5. 再利用サンプルは `../fbp-app-samples/references/pdf-delivery.md` と `../fbp-app-samples/assets/pdf-delivery/` を参照する。
6. CLIで応答・生成内容を補助確認する。`app_call` の `ok:true`、保存先が `.pdf`、ダイアログJSONが返ることだけではPDF取得成功と判定しない。
7. 新規のPDF表示・取得機能、表示ダイアログのデザイン、取得・認証経路を変更した場合は `fbp-playwright` に従い、実ボタンからPDF取得まで検証する。表示方式はダイアログとその後のPDF応答、直接方式はダウンロードまたは別タブのPDF応答を確認する。既存帳票の内容・計算・帳票内レイアウトだけの変更で取得経路に影響しない場合は、生成ファイルの内容・見た目を確認し、Playwrightの再実行は不要。
8. 生成・取得したPDFの `%PDF-`、PDF解析と期待する金額・件名等を確認する。HTTP経路を検証する場合はContent-Typeも確認する。新規経路は対象のPC・スマートフォン、既存変更は影響する端末・分岐に絞る。認証経路の新設・変更時はセッション切れ・権限不一致も確認する。Playwrightが必要な変更で実行できない場合は未検証範囲と理由を報告し、CLIだけで完了扱いにしない。

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
- PDF生成や `pdfunite` / `zip` などでアプリ独自の作業ファイルを作る場合は `fbp-temp-files` に従い `$ctl->get_temp_dir()` を使う。`save_pdf()` / `res_saved_file()` の管理ファイルは各API所定のアップロード領域を使い、一時用途なら応答後・例外時・exit時に削除する。保存先をアプリ側で固定パスにしない。
