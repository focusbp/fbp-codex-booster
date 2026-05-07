---
name: fbp-db
description: Manage FBP DB schema using CLI (db tables/fields), relation setup, and screen_fields reflection requirements.
---

# fbp-db

## trigger conditions
- テーブル/フィールドの追加・編集・削除を行う
- 親子relation、`screen_build_type`、`list_type`、manual sortの設定が必要
- DB変更時の画面反映漏れを防ぎたい

## workflow
1. `db_schema` と `db_tables_list` で現状確認。
2. `db_tables_*` / `db_fields_*` でスキーマ変更。新規ノートでは `screen_build_type` を先に決める。
3. `screen_build_type=Original Screen` なら `screen_fields` を前提にせず、`<tb_name>_original_management` 実装へ進む。
4. `screen_build_type=Standard Screen` のときだけ、`screen_fields` を `list/add/edit/delete`（必要なら `list_on_side`）へ反映。
5. `data_*` で実データ確認。

## terminology
- `メニュー画面`: `show_sidemenu()` で開く UI。DOM は `#sidemenu`。
- `サイド画面`: `show_second_work_area()` で開く UI。DOM は `#work_area_second`。
- 両者は別UIとして扱い、スクロール検知・`ajax-auto`・閉じる処理を分離して実装する。
- 画面上でユーザー向けに `テーブル` を表現する場合は、原則 `ノート` と読む。実装・CLI・DB定義上の `table` / `tb_name` / `db_tables_*` とは区別して扱う。
- 画面上でユーザー向けに `フィールド` を表現する場合は、原則 `項目` と読む。実装・CLI・DB定義上の `field` / `parameter_name` / `db_fields_*` とは区別して扱う。
- 仕様確認・文言追加・UI実装では、ユーザー向け文言に `テーブル` / `フィールド` を不用意に出さず、既存画面に合わせて `ノート` / `項目` を優先する。

## table width policy
- `db_tables_add` / `db_tables_edit` では `list_width`（Side Panel Width）と `edit_width`（Dialog Width）を必ず明示設定する。
- 幅は px の実数値として扱い、最小 `600`、最大 `1200`、`clamp(600, auto_calculated_width, 1200)` で決定する。
- 自動決定の目安:
  - 項目数・情報量が少ない: `600`
  - 中程度: `800`〜`1000`
  - 項目数・情報量が多い: `1200`
- `9` などの1桁/異常に小さい値は入力・更新しない。既存値が異常な場合は `600` 以上へ補正してから作業を継続する。

## list type policy
- 新規テーブル作成時は、原則 `screen_build_type=Original Screen` を選ぶ。`Standard Screen` は既存保守や特殊事情がある場合だけ使う。
- `list_type` は `Standard Screen` の一覧パターン、または `Original Screen` 実装時の補助設定として扱う。画面構築方式そのものを `list_type` に混ぜない。
- 新規テーブル作成時、`sort` 項目で手動並び替えを運用するテーブルは、`一覧タイプ` を `Manual Sort` に設定する。
- CLI では `db_tables_add` / `db_tables_edit` の `list_type=1` を使う。
- `sort` 項目があっても手動並び替え用途でない場合だけ、通常の `Search and Table` を選ぶ。
- `Manual Sort` を使うテーブルでは、`sort` 項目を `screen_fields` に入れない。
- 並び替えは画面の `Manual Sort` 操作で行う前提とし、`list` / `add` / `edit` / `search` に `sort` を出さない。

## date field policy
- 日付項目（年月日を表す項目）は `db_fields.type = date` を必須とする。
- `text + format_check=date_yyyy_mm_dd` での日付実装は新規作成で禁止する。
- 既存が `text` の場合は、改修時に `date` へ移行可否を確認し、不可の場合のみ理由を作業ログに明記して暫定維持する。

## table dropdown policy
- `constant_array_name` に `table/<tb_name>` を使う項目では、`display_fields_for_dropdown` を必ず設定する。
- テンプレート記法は Smarty 形式の `{$name}`、`{$order_no}` を使う。
- `{{name}}` のような mustache 形式は使わない。

## constraints
- DB追加後の画面反映漏れを禁止。
- `Original Screen` を選んだ新規ノートで、惰性で `screen_fields` ベース実装へ戻さない。
- 型依存値（date/datetime等）は仕様どおりに扱う。
- `type=checkbox` は値を配列として扱う前提で実装する（単一値文字列前提で判定しない）。
- checkbox の有無判定は `count($value ?? [])` ベースで行い、必要なら `is_array` ガードを入れる。
- `db_tables_list` の結果で対象テーブルの `list_width` / `edit_width` が `600`〜`1200` に入っていることを確認する。
- 日付項目を `text` 型で新規追加しない（必ず `type=date` を使う）。
- メニューリンク追加で `/common/menu.tpl` は使わない。メニュー追加は DB追加 / Dashboard登録 / 設定の「ホームページを表示」で行う。
