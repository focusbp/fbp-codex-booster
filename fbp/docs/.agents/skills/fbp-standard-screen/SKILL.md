---
name: fbp-standard-screen
description: Build, maintain, or adjust FBP Standard Screen note management using screen_fields, db_additionals, post_action_class, and helper-first patterns. This is the default for new CRUD-style note screens unless Original Screen is explicitly requested or required.
---

# fbp-standard-screen

## trigger conditions
- 新規ノート管理画面を作る
- 既存の Standard Screen を保守・微修正する
- 標準画面（list/add/edit/delete/list_on_side）を構築・修正する
- 標準画面サイドパネルの詳細設計は `fbp-side-panel` も使う
- helper利用方針（fields_form_direct等）の判断が必要
- 既存 Standard Screen だけでは足りず、`db_additionals` の add/edit/list 運用が必要
- MCP Note CRUD と同じ保存・副作用経路に寄せたい

## workflow
1. 新規ノート管理画面は、まず Standard Screen で作れるか確認する。ユーザーが「Original Screen指定」と明示した場合、または標準画面では足りない業務UIが必要な場合だけ `fbp-original-screen` へ切り替える。
2. `screen_fields` で実現可能か判定する。
3. 入力は `fields_form_direct`（非DBは `fields_form_original`）を優先。
4. 表示は `fields_view_direct` を優先。
5. 反映範囲を `list/add/edit/delete` で確認し、親ありなら `list_on_side` も確認する。サイドパネル固有の設計・検証は `fbp-side-panel` に従う。
6. 保存時の共通副作用は、画面専用処理ではなく対象ノートの `post_action_class` に寄せ、Standard Screen、Original Screen、MCP Server の Note CRUD で同じ動きにする。
7. `screen_fields` 登録・変更後は必ず `standard_screen_check` を実行し、フラグの選択式化、内部項目/ raw ID の露出、空の画面定義を確認する。

## default screen_fields policy
- ユーザーから項目指定がない場合、Standard Screen の `screen_fields` は次の方針で設定する。
- `add` / `edit`: 全業務項目を入れる。除外するのは `id`, `parent_id`, `sort`, `created_at`, `updated_at` など、システムまたはフレームワークが自動管理する項目。
- `delete`: 削除確認に必要なメイン項目を1〜2項目だけ入れる。例: 名称、タイトル、日時、番号など、ユーザーが削除対象を判別できる項目。
- `list`: 主要項目を5項目程度入れる。多すぎる一覧にせず、識別、状態、日時、担当、金額など業務上の確認頻度が高い項目を優先する。
- `list_on_side`: 子ノートのサイドパネル一覧に表示する項目。主要3〜5項目程度に絞る。詳細は `fbp-side-panel` に従う。
- `search`: 検索条件として自然な項目だけ入れる。名称、状態、担当、日付、カテゴリなどを優先し、長文本文や自動計算値は原則入れない。
- `sort` は Manual Sort 用の内部項目として使い、`screen_fields` には入れない。
- 並び順を画面で変更する仕様なら、`sort` は追加しても `screen_fields` には入れず、対象テーブルの `list_type` を `Manual Sort` にする。
- 並び順を画面で変更しない仕様なら、安易に `sort` を追加しない。既存の `sort` がある場合も、業務項目として見せる明確な理由がない限り `screen_fields` から外す。
- `status` や `type` はシステム項目扱いにしない。業務上見たい状態・分類なら `list` / `add` / `edit` / `search` の対象にする。
- `enabled` / `is_active` / `active` / `有効` などの有効状態項目は、数値テキストボックスではなく dropdown または checkbox として表示する。検索条件に入れる場合も選択式にする。
- file/image 項目は業務上入力・確認が必要な場合だけ `add` / `edit` に入れる。`list` では原則避け、必要なら代表画像など最小限にする。
- checkbox 項目は配列値として扱う。表示・保存の副作用は `post_action_class` へ寄せ、画面ごとの手書き処理に閉じ込めない。

## db_additionals workflow
1. `db_additionals` は Standard Screen 側の拡張として扱う。新規 Original Screen の代替として使わない。
2. まず `screen_fields` や標準機能で足りるか確認する。
3. `db_additionals_list` で既存重複と `dialog_width` を確認する。
4. 追加機能を実装し、`db_additionals_add` または `db_additionals_edit` で登録する。
5. `app_call` / `app_check` と必要な `data_*` 確認で動作を検証する。

## dialog width policy
- `db_additionals_add` / `db_additionals_edit` では `dialog_width` を必ず明示設定する。
- 幅は px の実数値として扱い、最小 `600`、最大 `1200`、`clamp(600, auto_calculated_width, 1200)` で決める。
- 目安は、少量なら `600`、中程度なら `800`〜`1000`、情報量が多い場合は `1200`。
- `9` などの1桁/異常に小さい値は使わない。既存値が異常なら `600` 以上へ補正してから作業を続ける。
- 個別の入力部品や `select` への固定 `width` は、ユーザーから明示要求がある場合だけ追加する。

## terminology
- 画面上のユーザー向け文言では、`テーブル` は `ノート` と表現する前提で扱う。
- 画面上のユーザー向け文言では、`フィールド` は `項目` と表現する前提で扱う。
- `screen_fields`、`db_fields_*`、`parameter_name` など実装用語をそのままUIへ出さない。UI文言追加時は既存の `ノート` / `項目` 表記に合わせる。
- `一覧上部ボタン`: ノート一覧の上部に配置する全体操作ボタン。実装上は主に `db_additionals` の一覧上部向けボタンとして扱う。
- `行ボタン`: ノート一覧の各行に配置する対象レコード単位の操作ボタン。実装上は主に `db_additionals` の行操作ボタンとして扱う。
- ユーザーが「一覧にボタン」と言った場合は、全体操作なら `一覧上部ボタン`、対象行ごとの操作なら `行ボタン` と呼び分ける。

## constraints
- 新規CRUD画面はこの Skill 起点を基本にする。Original Screen は明示指定または標準画面で不足する箇所だけに限定する。
- 手書き `<input>/<select>/<textarea>` は例外時のみ。
- 手書きの表示値展開（`{$row.xxx}` 直書き等）は例外時のみとし、原則 `fields_view_direct` を使う。
- 例外時は理由を明示可能な状態にする。
- `constant_array` にある選択肢ラベル（status/type等）はハードコードしない。`$ctl->get_constant_array()` または `fields_view_direct` でフレームワーク定義を参照する。
- URL生成は `$ctl->get_APP_URL()` を必須とし、`app.php?class=...` や `$_SERVER` 連結での直書きURLを増やさない。
- `screen_fields` 登録前に、日付項目のDB型が `date` になっていることを確認する。
- `screen_fields` 登録後は、`standard_screen_check --json='{"tb_name":"<tb_name>"}'` を実行し、必要に応じて `db_exe/page` / `rows` やブラウザ確認も行う。有効状態項目が選択式になっていること、`sort` が通常の list/add/edit/search に出ていないこと、Manual Sort が必要なテーブルでは `list_type=Manual Sort` になっていることを確認する。
- `standard_screen_check` の `empty_screen_fields` は警告扱い。意図的に空にしている画面なら無視してよいが、必要な `screen_fields` の登録漏れがないか確認する。
- PDF生成を `db_additionals` ボタンから実行する場合は、いったんダイアログを表示し、ダイアログ内 `download-link` でダウンロードさせる（`ajax-link` でPDFダウンロードは不可）。
