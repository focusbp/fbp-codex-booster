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
4. 反映経路を確認する。`constant_array` 変更は `classes/data/constant_array/*.dat` に入るため、`copy_to_web.sh` では同期されず、release では対象に含まれる。

## color policy
- `constant_values` に新規項目を追加する場合は、表示用の色を必ず同時に設定する。
- 色未設定（空/null）のまま登録しない。既存項目に未設定がある場合は、更新時に補完する。
- 追加・更新後は一覧表示で「ラベルと色」が一致していることを確認する。

## constraints
- 定数キー変更時は参照箇所の互換性を確認する。
- 項目追加時に色設定を省略しない。
- `constant_array` に定義された選択肢ラベルはハードコードしない。実装側では必ずフレームワーク取得（例: `$ctl->get_constant_array("<name>", false)` や `fields_view_direct`）を使う。
- `constant_array` / `constant_values` を `web` 側 CLI で更新した場合、その変更は `NetBeansProjects` には戻らない。`copy_to_web.sh` でもテスト環境へ配られないため、release まで含めた反映経路を意識して扱う。
