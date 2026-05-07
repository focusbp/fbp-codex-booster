# Migration inventory checklist

`Standard Screen` から移行するときに、実装前に確認する項目。

## db

- `tb_name`
- `menu_name`
- `screen_build_type`
- `list_type`
- `sortkey`
- `sort_order`
- `parent_tb_id`
- `show_id`
- `show_search_id`
- `show_duplicate`
- `list_width`
- `edit_width`

## db_fields

- 全項目名
- 項目タイトル
- 型
- 必須
- `constant_array_name`
- `format_check`
- `default_value`
- `duplicate_check`

## screen_fields

- `list` に出ている項目
- `search` に出ている項目
- `add` に出ている項目
- `edit` に出ている項目
- `view` があればその項目
- `hidden` や補助表示の有無

## standard behavior

- 一覧の既定ソート
- キーワード検索の対象
- dropdown 検索の対象
- child table への導線
- duplicate ボタンの有無
- CSV / file / image の扱い

## db_additionals

- 何件あるか
- どの place に出ているか
- ボタン名
- 引数として何を受けているか
- 何の class/function を呼んでいるか
- 行選択依存かどうか

## post_action_class

- add 後に何が走るか
- edit 後に何が走るか
- delete 後に何が走るか
- メール送信や child 更新など副作用があるか

## related data

- 親テーブル
- 子テーブル
- relation
- 親の親
- 一覧に一緒に出したい関連データ

## migration decisions

- CRUD / Sort / Calendar のどれにするか
- 何をそのまま再現するか
- 何を簡略化するか
- 何を廃止するか

## verification list

- `run`
- add
- edit
- delete
- filter or sort
- migrated `db_additionals`
- migrated `post_action_class`
