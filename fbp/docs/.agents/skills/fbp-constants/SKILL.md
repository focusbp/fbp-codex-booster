---
name: fbp-constants
description: Manage constant_array and constant_values definitions used by dropdowns and shared option sets.
---

# fbp-constants

## trigger conditions
- dropdown候補や共通定数を追加・修正する
- `constant_array` / `constant_values` を操作する

## workflow
1. `constant_array` の対象を特定。
2. `constant_values` を追加・編集・削除（追加時は色を必ず設定）。
3. 参照側画面で選択肢表示を確認。
4. 反映経路を確認する。`constant_array` 変更は `classes/data/constant_array/*.dat` に入るため、コード同期だけでは反映されない。リリース対象データに含める必要がある。

## color policy
- `constant_values` に新規項目を追加する場合は、表示用の色を必ず同時に設定する。
- 色未設定（空/null）のまま登録しない。既存項目に未設定がある場合は、更新時に補完する。
- 追加・更新後は一覧表示で「ラベルと色」が一致していることを確認する。

## key and storage policy
- 新規 `constant_array` の key は、特別な理由がなければ `0`, `1`, `2` のような整数キーにする。
- `enabled` / `is_active` / `active` / `有効` などの 0/1 状態を dropdown にする場合は、整数キーの `0` / `1` を使い、ラベルは画面文脈に合わせて `無効` / `有効` などにする。
- WEB/CLI の新規追加・編集では、`constant_values.key` は `0` または先頭ゼロなしの非負整数だけを許可する。
- 整数キーのみを参照する dropdown フィールドは、先頭ゼロや外部コードとしての意味がない場合、fmt上は `N` 保存を優先する。
- Text key の新規採用は原則避ける。やむを得ず必要な場合は、整数キーにできない理由、既存データ、検索コード、表示、release対象データへの影響を確認してから例外扱いする。
- 既存の Text key / `T` 保存を使える仕様は維持する。既存項目を `T` から `N` へ変更する場合は、既存データ、検索コード、表示、release対象データの影響を確認してから行う。
- dropdown をコードで検索する場合、保存型に合わせた値で検索する。`T` 保存なら文字列、`N` 保存なら数値を基本にする。

## constraints
- 定数キー変更時は参照箇所の互換性を確認する。
- 項目追加時に色設定を省略しない。
- `constant_array` に定義された選択肢ラベルはハードコードしない。実装側では必ずフレームワーク取得（例: `$ctl->get_constant_array("<name>", false)` や `fields_view_direct`）を使う。
- `constant_array` / `constant_values` を実行環境側 CLI で更新した場合、その変更はソース側には自動で戻らない。データリリースまで含めた反映経路を意識して扱う。
