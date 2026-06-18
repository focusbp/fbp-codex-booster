---
name: fbp-standard-screen
description: Build, maintain, or adjust FBP Standard Screen note management using screen_fields, db_additionals, post_action_class, and helper-first patterns. This is the default for new CRUD-style note screens unless Original Screen is explicitly requested or required.
---

# fbp-standard-screen

## trigger conditions
- 新規ノート管理画面を作る
- 既存の Standard Screen を保守・微修正する
- 標準画面（list/add/edit/delete/list_on_side）を構築・修正する
- helper利用方針（fields_form_direct等）の判断が必要
- 既存 Standard Screen だけでは足りず、`db_additionals` の add/edit/list 運用が必要
- MCP Note CRUD と同じ保存・副作用経路に寄せたい

## workflow
1. 新規ノート管理画面は、まず Standard Screen で作れるか確認する。ユーザーが「Original Screen指定」と明示した場合、または標準画面では足りない業務UIが必要な場合だけ `fbp-original-screen` へ切り替える。
2. `screen_fields` で実現可能か判定する。
3. 入力は `fields_form_direct`（非DBは `fields_form_original`）を優先。
4. 表示は `fields_view_direct` を優先。
5. 反映範囲を `list/add/edit/delete` で確認し、親ありなら `list_on_side` も確認。
6. 保存時の共通副作用は、画面専用処理ではなく対象ノートの `post_action_class` に寄せ、Standard Screen、Original Screen、MCP Server の Note CRUD で同じ動きにする。

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

## constraints
- 新規CRUD画面はこの Skill 起点を基本にする。Original Screen は明示指定または標準画面で不足する箇所だけに限定する。
- 手書き `<input>/<select>/<textarea>` は例外時のみ。
- 手書きの表示値展開（`{$row.xxx}` 直書き等）は例外時のみとし、原則 `fields_view_direct` を使う。
- 例外時は理由を明示可能な状態にする。
- `constant_array` にある選択肢ラベル（status/type等）はハードコードしない。`$ctl->get_constant_array()` または `fields_view_direct` でフレームワーク定義を参照する。
- URL生成は `$ctl->get_APP_URL()` を必須とし、`app.php?class=...` や `$_SERVER` 連結での直書きURLを増やさない。
- `screen_fields` 登録前に、日付項目のDB型が `date` になっていることを確認する。
- PDF生成を `db_additionals` ボタンから実行する場合は、いったんダイアログを表示し、ダイアログ内 `download-link` でダウンロードさせる（`ajax-link` でPDFダウンロードは不可）。
