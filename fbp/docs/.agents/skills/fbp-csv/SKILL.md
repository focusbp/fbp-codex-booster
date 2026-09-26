---
name: fbp-csv
description: Implement and verify CSV export/import in FBP, including authenticated management downloads, upload validation, and CLI checks.
---

# fbp-csv

## trigger conditions
- CSVアップロード/ダウンロード機能を追加・修正する。
- CSVの文字コード、項目変換、取込時の検証を扱う。
- 保存済みの画像・動画・一般ファイルの配信は [fbp-media](../fbp-media/SKILL.md) を使う。

## workflow
1. 管理側CSVは通常のログイン認証を通す。クラスの既定の認証を維持し、ダウンロードのために `set_check_login(false)` を追加しない。出力対象のノート・レコード権限も確認する。
2. CSVダウンロードは `res_csv()` でヘッダ行 + データ行を返す。
3. CSVアップロードは `fields_form_original type="file"` + `upload_exe` で実装する。入力エラーは `res_error_message()` で返して即 `return` する。
4. `app_call` で出力/アップロードを検証し、`data_list` で反映確認する。認証の確認は実HTTP経由で行う。

## verification
- `fbp-cli` に従い `app_call` の `output_file` へCSVを出力し、文字コード、ヘッダ、行数、値を確認する。
- アップロードは `app_call` の `files` に `{ "file": { "path": "<input.csv>", "name": "input.csv", "type": "text/csv" } }` を渡す。
- CLIでは `is_uploaded_file()` が `false` になるため、CLI時のみ `is_file()` を許可して検証する。
- 管理側CSVの取得URLへ未ログインでアクセスした場合に、CSV本文が返らないことを確認する。

## constraints
- 日付/日時/年月のCSV文字列化が PHP 直書きになる場合は `$ctl->create_ValueFormatter()` を使う。`date()` の直書きは機械連携用の固定フォーマットCSVに限定する。
- `ValueFormatter` は CSV/PDF/Mail など PHP 直書き出力用。HTMLには `fields_*` / `html_*` helper を使う。
- `db()->insert()` / `update()` に配列リテラルを直接渡さず、変数化する（参照渡し対策）。
- エラー時に `show_multi_dialog()` 再実行や `reload_area()` をしない。
- 作業ファイルは `fbp-temp-files` に従い `$ctl->get_temp_dir()` を使い、応答後・例外時・exit時に削除する。
