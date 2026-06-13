# CRUD dashboard pattern

`Original` 種別の最初の標準パターンとして、一覧 / 追加 / 編集 / 削除 / 部分更新を持つ管理画面を作る。

## variant choice

- 最小版:
  構造理解、初回雛形、学習用。`assets/minimal_note_original_management/`
- 実運用版:
  UI 調整済み、自動検索あり、`ajax-auto` あり。`assets/sample_note_original_management/`

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

1. `run()` でフィルタフォームと一覧領域を表示する
2. 一覧本体は `list_area.tpl` に分離する
3. フィルタ変更時は `apply_filter()` で `reload_area("#..._list_area", "list_area.tpl")`
4. 追加 / 編集は `show_multi_dialog()` を使う
5. 削除は確認ダイアログを挟む
6. 一覧件数が多い場合だけ、一覧の下端に `ajax-auto` を置いて `rows_more()` で段階表示する

## design defaults

- クラス名は `<tb_name>_original_management`
- 一覧領域 id は `<tb_name>_original_management_list_area`
- 画面内検索はセッション保持にしてもよい
- 検索フォーム自体は固定し、一覧だけ更新する
- 検索ボックスは `db_exe` に寄せた `search_box + search_form_flex + hidden search_right button` 構成を標準にする
- 検索項目は最大5項目/行のグリッドにし、狭い画面では 4 / 3 / 2 / 1 項目へ折り返す
- 一覧を月などの必須条件で絞って全件表示する画面では、`ajax-auto` を使わず一括表示してよい
- 汎用一覧で件数が多いときは、初期表示 10 件 + `ajax-auto` による 10 件ずつの段階表示を検討する
- 一覧はテーブル見出しより、`row_style` を使ったカード風の横並び行を優先する
- 一覧テーブルは `width:100%` にし、行操作セルはPC表示で `display:table-cell` に戻して `listbutton + material-symbols-outlined` を右端に揃える。削除だけ赤にする
- 追加 / 編集ダイアログの項目間は `db_exe` に合わせて `margin-top:10px` を標準にする
- ダイアログ本文や詳細内の追加入力フォームは縦積みにし、保存・追加ボタンをフォーム部品の横に置かない。ボタンは入力部品の下で右寄せにする

## notes

- 標準画面の `screen_fields` には依存しない
- オリジナル画面の CRUD 導線は、原則そのクラス内に閉じる
- ダッシュボード風UIでも、通常管理画面でも、PHP側の責務分離は同じ

## code source

流用元の考え方は `app-soshikikaikaku` の `task_support_dashboard`。  
まず骨組みだけ欲しいときは `assets/minimal_note_original_management/`、案件へそのまま寄せたいときは `assets/sample_note_original_management/` を使う。
