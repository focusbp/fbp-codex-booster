# Manual sort pattern

`Original` 種別で、標準マニュアルソートより自由度の高い並び替え画面を作る。

## variant choice

- 最小版:
  並び替えと CRUD の骨組みだけを確認する。`assets/minimal_sort_original_management/`
- 実運用版:
  `db_exe` 風の行 UI と右寄せアイコンで、そのまま案件に流用しやすくする。`assets/sample_sort_original_management/`

## file layout

```text
classes/app/<tb_name>_original_management/
├── <tb_name>_original_management.php
└── Templates/
    ├── list.tpl
    ├── list_area.tpl
    ├── add.tpl
    ├── edit.tpl
    └── delete_confirm.tpl
```

## implementation shape

1. `run()` で並び替え画面を表示する
2. 一覧本体は `list_area.tpl` に分離する
3. 行のドラッグ完了時に `sort_save()` へ `log` を送る
4. 追加 / 編集は `show_multi_dialog()`、削除は確認ダイアログを使う
5. `sort` 項目は追加時に末尾採番し、並び替え時に 1 から振り直す

## design defaults

- クラス名は `<tb_name>_original_management`
- 一覧領域 id は `<tb_name>_original_management_list_area`
- ノート側には整数の `sort` 項目を用意する
- 一覧取得は `sort ASC, id ASC` を標準にする
- ハンドルは `swap_vert` アイコンを使い、`cancel:"button"` を付ける
- 操作列は右寄せアイコンにし、削除だけ赤にする
- マスター向けなら検索は付けず、`追加 / 並び替え / 編集 / 削除` に絞る

## recommended first scope

- `sort` 項目を使った 1 列ドラッグ並び替え
- 追加 / 編集 / 削除
- タイトル 1 項目か、タイトル + 補足 1 項目

## extension ideas

- 親データ名や件数を同じ行に表示する
- 有効 / 無効切替をアイコン列に足す
- グループ単位で sort 保存する
- 並び替え後に toast や更新時刻表示を足す

## code source

最小骨組みは `assets/minimal_sort_original_management/`、実運用寄りの雛形は `assets/sample_sort_original_management/` を使う。  
標準 `db_exe` のマニュアルソート UI を下敷きにしつつ、表示項目や関連情報は自由に広げる。

## tested example

- 実装確認済みサンプル:
  `classes/app/branch_master_original_management/`
- ノート例:
  `branch_master`
- 最小構成:
  `branch_name / note / sort`
- 確認済み導線:
  `run / add_save / edit_save / sort_save / delete_save`
