# CRUD dashboard pattern

`Original` 種別の最初の標準パターンとして、一覧 / 追加 / 編集 / 削除 / 部分更新を持つ管理画面を作る。

## variant choice

- 最小版:
  構造理解、初回雛形、学習用。`assets/minimal_note_original_management/`
- 実運用版:
  UI 調整済み、検索あり、`ajax-auto` あり。`assets/sample_note_original_management/`

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
6. 一覧の下端は `ajax-auto` で `rows_more()` を呼び、10件ずつ `max` を増やす

## design defaults

- クラス名は `<tb_name>_original_management`
- 一覧領域 id は `<tb_name>_original_management_list_area`
- 画面内検索はセッション保持にしてもよい
- 検索フォーム自体は固定し、一覧だけ更新する
- 一覧件数が多いときは、初期表示 10 件 + `ajax-auto` による 10 件ずつの無限スクロールを標準にする
- 検索ボックスは `db_exe` に寄せた `search_box + search_form_flex` 構成を標準にする
- 一覧はテーブル見出しより、`row_style` を使ったカード風の横並び行を優先する
- 行操作は `listbutton + material-symbols-outlined` で右寄せにし、削除だけ赤にする
- 追加 / 編集ダイアログの項目間は `db_exe` に合わせて `margin-top:10px` を標準にする

## notes

- 標準画面の `screen_fields` には依存しない
- オリジナル画面の CRUD 導線は、原則そのクラス内に閉じる
- ダッシュボード風UIでも、通常管理画面でも、PHP側の責務分離は同じ

## code source

流用元の考え方は `app-soshikikaikaku` の `task_support_dashboard`。  
まず骨組みだけ欲しいときは `assets/minimal_note_original_management/`、案件へそのまま寄せたいときは `assets/sample_note_original_management/` を使う。
