---
name: fbp-db
description: Manage FBP DB schema using CLI (db tables/fields), relation setup, text/textarea field length policy, and screen_fields reflection requirements.
---

# fbp-db

## trigger conditions
- テーブル/フィールドの追加・編集・削除を行う
- 親子relation、`screen_build_type`、`list_type`、manual sortの設定が必要
- DB変更時の画面反映漏れを防ぎたい

## workflow
1. `db_schema` と `db_tables_list` で現状確認。
2. `db_tables_*` / `db_fields_*` でスキーマ変更。新規ノートでは `screen_build_type` を先に決める。
   - DB管理テーブルの項目は `db_fields_*` / CLI を正本にし、`classes/data/_common/fmt/*.fmt` を直接編集しない。
   - `.fmt` の直接編集は、DB管理外の固定fmtを扱う場合だけに限定する。
3. 新規ノート画面は、まず `screen_build_type=Standard Screen` にする。ユーザーが「Original Screen指定」と明示した場合、または標準画面で実現できない業務UIが必要な場合だけ `Original Screen` を選ぶ。
4. `screen_build_type=Original Screen` なら `screen_fields` を前提にせず、同じ作業内で `<tb_name>_original_management` 実装へ進む。
5. `screen_build_type=Standard Screen` のときだけ、`screen_fields` を `list/add/edit/delete/search`（必要なら `list_on_side`）へ反映。
6. `data_*` で実データ確認。

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
- 新規テーブル作成時は、原則 `screen_build_type=Standard Screen` を選ぶ。
- `Original Screen` は、ユーザーが「Original Screen指定」と明示した場合、または標準の `list/add/edit/delete/search/list_on_side`、`db_additionals`、`post_action_class` では足りない業務UIが必要な場合だけ選ぶ。
- 標準CRUDノート、取込確認用ノート、一時的なデータ管理ノート、MCP Note CRUD と共通化したいノートは `Standard Screen` を選び、`screen_fields` を `list/add/edit/delete/search/list_on_side` に反映する。
- `list_type` は `Standard Screen` の一覧パターン、または `Original Screen` 実装時の補助設定として扱う。画面構築方式そのものを `list_type` に混ぜない。
- 新規テーブル作成時、`sort` 項目で手動並び替えを運用するテーブルは、`一覧タイプ` を `Manual Sort` に設定する。
- CLI では `db_tables_add` / `db_tables_edit` の `list_type=1` を使う。
- `sort` 項目があっても手動並び替え用途でない場合だけ、通常の `Search and Table` を選ぶ。
- `Manual Sort` を使うテーブルでは、`sort` 項目を `screen_fields` に入れない。
- 並び替えは画面の `Manual Sort` 操作で行う前提とし、`list` / `add` / `edit` / `search` に `sort` を出さない。

## menu and relation policy
- 新規ノートでは、親ノートは `show_menu=1`（Show）、子ノートは `show_menu=0`（Hide）を基本にする。
- 親子関係は `parent_tb_id` を正本にする。`parent_tb_id` を設定すると `parent_id` 項目が自動追加されるため、手動で重複追加しない。
- 親一覧から開く明細、履歴、コメントなどの小テーブルは、`show_icon_on_parent_list=0` を設定して親一覧にアイコンを表示する。FBP内部値は `0 = Show`, `1 = Hide` なので値を取り違えない。
- `parent_tb_id` / `parent_id` で親に従属する子ノートは、ユーザーから別指定がない限り `cascade_delete_flag=1`（Cascade delete）にする。
- 子ノートでも単独メニューから直接扱う業務上の意味がある場合だけ、理由を確認して `show_menu=1` を許可する。

## date field policy
- 日付項目（年月日を表す項目）は `db_fields.type = date` を必須とする。
- `text + format_check=date_yyyy_mm_dd` での日付実装は新規作成で禁止する。
- 既存が `text` の場合は、改修時に `date` へ移行可否を確認し、不可の場合のみ理由を作業ログに明記して暫定維持する。

## text field length policy
- ノートの `text` / `textarea` 項目で「500文字」など文字数で長さを指定された場合は、日本語3バイト想定で DB の length を `指定文字数 * 3` にする。
- 例: `500文字` 指定なら `length=1500`、`1000文字` 指定なら `length=3000`。
- 既存仕様がバイト数・DB length として明示されている場合は、その値を優先し、3倍変換しない。

## table dropdown policy
- `constant_array_name` に `table/<tb_name>` を使う項目では、`display_fields_for_dropdown` を必ず設定する。
- テンプレート記法は Smarty 形式の `{$name}`、`{$order_no}` を使う。
- `{{name}}` のような mustache 形式は使わない。

## constraints
- DB管理テーブルで項目を追加・変更・削除する場合は、直接 `.fmt` を変更しない。必ず `db_fields_*` / CLI で変更し、必要な `screen_fields` も同時に整理する。
- DB追加後の画面反映漏れを禁止。
- `Original Screen` を選んだ新規ノートで、対応する `classes/app/<tb_name>_original_management/<tb_name>_original_management.php` を未作成のまま終えない。
- `screen_build_type=Original Screen` のまま標準CRUDだけを期待しない。実装しないなら `Standard Screen` に戻し、`db_exe/page` を `app_call` して `Original management class not found` が出ないことを確認する。
- 型依存値（date/datetime等）は仕様どおりに扱う。
- `type=checkbox` は値を配列として扱う前提で実装する（単一値文字列前提で判定しない）。
- checkbox の有無判定は `count($value ?? [])` ベースで行い、必要なら `is_array` ガードを入れる。
- `db_tables_list` の結果で対象テーブルの `list_width` / `edit_width` が `600`〜`1200` に入っていることを確認する。
- 日付項目を `text` 型で新規追加しない（必ず `type=date` を使う）。
- `text` / `textarea` 項目の文字数指定を、そのまま DB length として設定しない。
- メニューリンク追加で `/common/menu.tpl` は使わない。メニュー追加は DB追加 / Dashboard登録 / 設定の「ホームページを表示」で行う。
