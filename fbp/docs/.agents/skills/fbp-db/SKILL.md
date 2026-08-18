---
name: fbp-db
description: Manage the FBP DB lifecycle from CLI-based table/field design through release of DB definitions, automatic production data-file creation and format updates, relations, field length policy, and screen_fields reflection.
---

# fbp-db

## trigger conditions
- テーブル/フィールドの追加・編集・削除を行う
- 親子relation、`screen_build_type`、`list_type`、manual sortの設定が必要
- サイドパネル用の `list_width`、親子関係、`list_on_side` を設定する場合は `fbp-side-panel` も使う
- DB変更時の画面反映漏れを防ぎたい

## workflow
1. `db_schema` と `db_tables_list` で現状確認。
2. `db_tables_*` / `db_fields_*` でスキーマ変更。新規ノートでは `screen_build_type` を先に決める。
   - DB管理テーブルの項目は `db_fields_*` / CLI を正本にし、`classes/data/_common/fmt/*.fmt` を直接編集しない。
   - `.fmt` の直接編集は、DB管理外の固定fmtを扱う場合だけに限定する。
3. 新規ノート画面は、まず `screen_build_type=Standard Screen` にする。ユーザーが「Original Screen指定」と明示した場合、または標準画面で実現できない業務UIが必要な場合だけ `Original Screen` を選ぶ。
4. `screen_build_type=Original Screen` なら `screen_fields` を前提にせず、同じ作業内で `<tb_name>_original_management` 実装へ進む。
5. `screen_build_type=Standard Screen` のときだけ、`screen_fields` を `list/add/edit/delete/search`（必要なら `list_on_side`）へ反映。サイドパネルの項目・幅・親子導線は `fbp-side-panel` に従う。
6. `data_*` で実データ確認。

## definition release and runtime lifecycle
- DB管理画面または `db_tables_*` / `db_fields_*` / `screen_fields_*` で作成した定義を正本にする。管理対象ノートの `.fmt` を手作業で作らない。
- 通常のFBPリリースは `db`、`db_additionals` などのリリース対象 `.dat` と、生成済み `classes/data/_common/fmt/*.fmt` をアプリコードと一緒に本番へコピーする。
- 本番に対象ノートの実データ `.dat` がまだ存在しない場合、FBPはコピー済み `.fmt` を使い、最初のDBアクセス時に `.dat` を自動作成する。新規配布用に `db_tables_add` / `db_fields_add` を再実行する常設migrationを追加しない。
- 既存の実データ `.dat` に対して定義が変わった場合、FBPは新しい `.fmt` に合わせて形式更新する。リリース前にテスト環境の既存データで `changeFormat()` が通ることを `describe` と `data_list` / `getall` で確認する。
- Standard Screenの配布ではテーブル・項目だけでなく、`screen_fields` と必要な `db_additionals` も同じリリース対象として確認する。
- 独自の配布scriptが通常のFBPリリースを使わない場合は、新規配布経路がDB定義と生成済み `.fmt` を初期配置することを確認する。定義が初期配置されるなら、個別のテーブル作成migrationは不要。
- 一時migrationを使うのは、標準リリースでは表現できないデータ変換や、定義配布前に存在する本番データの補正が必要な場合だけにする。全対象への適用完了後は通常リリースから外す。

### release verification
1. テスト環境で `db_schema`、`db_tables_list`、`db_additionals_list` を確認する。
2. Standard Screenは `standard_screen_check` で意図しない `ERROR` / `WARN` がないことを確認する。
3. 実データがある変更は `describe` と `data_list` / `getall` で形式更新とデータ保持を確認する。
4. 本番反映は環境指定の承認済みrelease scriptを使い、反映後に現在の定義とデータ読込を再確認する。

## terminology
- `メニュー画面`: `show_sidemenu()` で開く UI。DOM は `#sidemenu`。
- `サイド画面`: `show_second_work_area()` で開く UI。DOM は `#work_area_second`。
- 両者は別UIとして扱い、スクロール検知・`ajax-auto`・閉じる処理を分離して実装する。
- 画面上でユーザー向けに `テーブル` を表現する場合は、原則 `ノート` と読む。実装・CLI・DB定義上の `table` / `tb_name` / `db_tables_*` とは区別して扱う。
- 画面上でユーザー向けに `フィールド` を表現する場合は、原則 `項目` と読む。実装・CLI・DB定義上の `field` / `parameter_name` / `db_fields_*` とは区別して扱う。
- 仕様確認・文言追加・UI実装では、ユーザー向け文言に `テーブル` / `フィールド` を不用意に出さず、既存画面に合わせて `ノート` / `項目` を優先する。

## table width policy
- `db_tables_add` / `db_tables_edit` では `list_width`（Side Panel Width）と `edit_width`（Dialog Width）を必ず明示設定する。サイドパネル用途の幅判断は `fbp-side-panel` も参照する。
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
- 手動並び替えを行うテーブルでは、`sort` 項目を追加または維持し、`sortkey=sort` と `list_type=1` を揃える。
- 手動並び替えを行わないテーブルでは、安易に `sort` 項目を追加しない。既存の `sort` がある場合だけ、通常の `Search and Table` を選んでよい。
- `Manual Sort` を使うテーブルでは、`sort` 項目を `screen_fields` に入れない。
- 並び替えは画面の `Manual Sort` 操作で行う前提とし、`list` / `add` / `edit` / `search` に `sort` を出さない。

## state field policy
- `enabled` / `is_active` / `active` / `有効` などの有効状態項目は、数値テキスト入力ではなく dropdown または checkbox として扱える型・定数にする。
- 0/1 の dropdown にする場合は `constant_array` を用意し、検索・追加・編集で選択式になることを確認する。

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

## field type policy
- `db_fields_add` / `db_fields_edit` の前に、対象frameworkの `fbp/app/db/db.php` にある `type_opt` を確認し、一覧にある型だけを指定する。`format_check` の選択肢を `type` として指定しない。
- メールアドレスは `type=text`、`format_check=email` を使う。`type=email` は使用しない。

## field length policy
- 固定長DBの `db_fields.length` は 0 以下にしない。
- CLI、DB管理画面、管理APIでは、型ごとの既定長を使って `length <= 0` を補正する。
- 時刻項目は `type=time`、`length=8` を使う。`HH:MM` を基本にし、秒付き互換も考慮して8バイトを確保する。
- `db_fields` を変更したら `.fmt` 再生成後に `describe` でフィールドサイズを確認し、実データがあるテーブルでは `data_list` / `getall` で `changeFormat()` が通ることも確認する。
- 旧 `.dat` ヘッダに `length=0` が残る場合、通常読込で握りつぶさず、`changeFormat()` の旧フォーマット変換中だけ空欄として読み込む。

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
- `sort` 項目があるテーブルでは、手動並び替えが必要かを明示的に確認する。必要なら `list_type=1` / `sortkey=sort` にし、不要なら通常画面の `screen_fields` に `sort` を出さない。
- 日付項目を `text` 型で新規追加しない（必ず `type=date` を使う）。
- 使用可能な `type` を確認せずにDB項目を追加・変更しない。特にメールアドレスの `type=email` 指定は禁止し、`text + format_check=email` にする。
- `text` / `textarea` 項目の文字数指定を、そのまま DB length として設定しない。
- メニューリンク追加で `/common/menu.tpl` は使わない。メニュー追加は DB追加 / Dashboard登録 / 設定の「ホームページを表示」で行う。
