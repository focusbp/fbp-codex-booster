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
3. `db_additional` 起点の場合は、まず `show_multi_dialog()` で確認ダイアログを表示する。
4. ダイアログ内の「ダウンロード」ボタンは `download-link` を使い、PDF生成関数を直接呼ぶ。
5. PDF用 `download-link` には原則 `data-open_new_tab="true"` を付ける。
6. `app_call` のファイル出力指定で生成テスト。
7. 保存ファイルと内容を確認。

## table samples
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

## constraints
- ユーザーからの印刷機能の実装は、HTMLの印刷ではなく必ずフレームワークのPDF出力機能を使用する。
- 文字化け・画像パス・ページ崩れを優先チェックする。
- PDF本文で日付/日時/年月を PHP 直書きする場合は `$ctl->create_ValueFormatter()` を使う。HTML 表示 helper の代替としては使わない。
- PDFダウンロード導線に `ajax-link` は使わない（ダウンロードデータを扱えないため）。
- `download-link` の `data-class` は明示的に実クラス名を指定する（`{$class}` 依存を避ける）。
- PDFダウンロードの `download-link` は `data-open_new_tab="true"` を基本とする。例外時は理由を実装コメントかPR説明に残す。
- `addTable` の `columnsize` は合計 `100` にする（%指定として扱うため）。
- `addText()` などで安易に `bold => true` を使わない。既定フォントでは `Undefined font` になることがあるため、太字が必要な場合は `migmix-1p-bold` など登録済みの太字フォントを `fontname` で明示する。
- PDF生成や `pdfunite` / `zip` など外部コマンド連携で一時ファイルを作る場合、`sys_get_temp_dir()` を前提にしない。本番アプリユーザーで `tempnam(sys_get_temp_dir(), ...)` が失敗することがあるため、一時ファイルもアプリユーザーが書き込み可能なアップロードディレクトリ配下に作成する。
