# Standard to Original migration

既存の `Standard Screen` を `Original Screen` へ移行するときの全体手順。

## goal

標準画面に分散していた設定と拡張を、`<tb_name>_original_management` へ再集約する。  
一覧の置き換えだけで終わらせず、`screen_fields`、`db_additionals`、`post_action_class`、関連導線まで棚卸ししてから移行する。

## migration flow

1. 先に棚卸しを行う  
   `references/migration-inventory-checklist.md` を埋める。
2. 画面パターンを決める  
   CRUD / Sort / Calendar のどれに寄せるかを決める。
3. 標準画面で再現している機能を分類する  
   `一覧 / 検索 / 追加 / 編集 / 削除 / 詳細 / 子テーブル / 独自ボタン / 保存後処理`
4. `db_additionals` と `post_action_class` を移行方針ごとに整理する  
   `references/migration-db_additionals-post_action_class.md` を使う。
5. `classes/app/<tb_name>_original_management/` を作る
6. 画面単位で順に移す  
   `list.tpl`, `list_area.tpl`, `add.tpl`, `edit.tpl`, `delete_confirm.tpl`
7. `screen_build_type` を `Original Screen` に切り替える
8. CLI で `run / add / edit / delete / 移行した独自機能` を確認する

## what to migrate

- `db`
  `tb_name`, `list_type`, `sortkey`, `sort_order`, `parent_tb_id`
- `db_fields`
  型、必須、format_check、constant_array、既定値
- `screen_fields`
  `list / add / edit / search / view`
- `db_additionals`
  一覧や編集画面の独自ボタン
- `post_action_class`
  add/edit/delete 後に走る処理
- related tables
  親、子、親の親、relation

## migration principles

- `screen_fields` は移行後の描画責務から外す
- `db_additionals` は原則そのまま持ち込まず、`Original` クラス内関数へ寄せる
- `post_action_class` は処理内容を確認し、必要なら同クラス内へ吸収する
- 標準画面と完全一致を目指すより、業務導線を整理し直す

## choosing a pattern

- CRUD:
  一覧、検索、追加、編集、削除が中心
- Sort:
  マスターの並び替えが中心
- Calendar:
  日付・週表示が中心

## completion criteria

- `db_exe/page` から `Original` クラスが呼ばれる
- `run` が通る
- add/edit/delete が通る
- 移行対象だった `db_additionals` / `post_action_class` 相当の機能が通る
- `data_get` または `data_list` で結果を確認できる
