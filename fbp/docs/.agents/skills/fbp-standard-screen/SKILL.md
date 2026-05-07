---
name: fbp-standard-screen
description: Maintain or adjust legacy standard FBP screens using screen_fields and helper-first patterns. Use mainly for existing screens; new development should usually prefer Original Screen.
---

# fbp-standard-screen

## trigger conditions
- 既存の Standard Screen を保守・微修正する
- 標準画面（list/add/edit/delete/list_on_side）を構築・修正する
- helper利用方針（fields_form_direct等）の判断が必要

## workflow
1. まず「この対応は Standard を維持すべき既存保守か」を確認する。新規制作なら `fbp-original-screen` を優先する。
2. `screen_fields` で実現可能か判定する。
3. 入力は `fields_form_direct`（非DBは `fields_form_original`）を優先。
4. 表示は `fields_view_direct` を優先。
5. 反映範囲を `list/add/edit/delete` で確認し、親ありなら `list_on_side` も確認。

## terminology
- 画面上のユーザー向け文言では、`テーブル` は `ノート` と表現する前提で扱う。
- 画面上のユーザー向け文言では、`フィールド` は `項目` と表現する前提で扱う。
- `screen_fields`、`db_fields_*`、`parameter_name` など実装用語をそのままUIへ出さない。UI文言追加時は既存の `ノート` / `項目` 表記に合わせる。

## constraints
- 新規画面をこの Skill 起点で増やさない。既存 Standard Screen の保守・部分改修を主用途とする。
- 手書き `<input>/<select>/<textarea>` は例外時のみ。
- 手書きの表示値展開（`{$row.xxx}` 直書き等）は例外時のみとし、原則 `fields_view_direct` を使う。
- 例外時は理由を明示可能な状態にする。
- `constant_array` にある選択肢ラベル（status/type等）はハードコードしない。`$ctl->get_constant_array()` または `fields_view_direct` でフレームワーク定義を参照する。
- URL生成は `$ctl->get_APP_URL()` を必須とし、`app.php?class=...` や `$_SERVER` 連結での直書きURLを増やさない。
- `screen_fields` 登録前に、日付項目のDB型が `date` になっていることを確認する。
