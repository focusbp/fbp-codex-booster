---
name: fbp-original-screen
description: Build custom note management screens as the default FBP approach by using `screen_build_type=Original Screen` and a `<tb_name>_original_management/run` class, with reusable CRUD, sort, and calendar samples plus CLI verification patterns.
---

# fbp-original-screen

## trigger conditions
- 新規のノート管理画面を作る
- 既存の `Standard Screen` を `Original Screen` へ移行したい
- 標準画面の `検索 / マニュアルソート / カレンダー` では足りず、自由度の高い管理画面を作りたい
- ノートの `screen_build_type` を `Original Screen` にして、`<tb_name>_original_management/run` を呼びたい
- 一覧 / 追加 / 編集 / 削除 / 部分更新の管理画面パターンを再利用したい

## workflow
1. 新規画面は原則この Skill を第一候補にする。`Standard Screen` は既存保守や単純改修だけに限定する。
2. 既存移行なら先に `references/migration-standard-to-original.md` で棚卸し対象を洗い出す。
3. 対象ノートの `screen_build_type` を `Original Screen` にする。
4. `list_type` は Original 画面の中で使う UI パターンに応じて通常一覧系を選ぶ。
5. `classes/app/<tb_name>_original_management/<tb_name>_original_management.php` を作る。
6. `run(Controller $ctl)` で自由な画面を表示する。
7. 初回の骨組み確認なら `minimal_*`、案件流用なら `sample_*` を起点に流用する。
8. 検証は `references/verification.md` の `app_call` / `data_*` パターンで行う。

## naming rules
- クラス名は固定で `<tb_name>_original_management`
- 呼び出し関数は固定で `run`
- framework 側から `db_id` や検索条件は自動注入されない前提で作る

## constraints
- 新規制作の基本方針は Original Screen とする。特段の理由がない限り `screen_fields` 主体の新規画面へ戻さない。
- URL生成は必ず `$ctl->get_APP_URL()` を使う
- バリデーションエラーは `res_error_message()` を返して即 `return`
- エラー時に `show_multi_dialog()` 再実行や `reload_work_area()` をしない
- 一覧の Ajax 更新は、必要な領域だけ `reload_area()` する
- `fields_form_direct` / `fields_form_original` / `fields_view_direct` を優先する

## references
- Standard からの移行手順: `references/migration-standard-to-original.md`
- 棚卸しチェックリスト: `references/migration-inventory-checklist.md`
- `db_additionals` / `post_action_class` 移行: `references/migration-db_additionals-post_action_class.md`
- CRUD ダッシュボード実装: `references/crud-dashboard.md`
- マニュアルソート実装: `references/sort-pattern.md`
- 週カレンダー実装: `references/calendar-pattern.md`
- 検証手順: `references/verification.md`
- ひな形コード:
  - 最小版 CRUD: `assets/minimal_note_original_management/`
  - 実運用版 CRUD: `assets/sample_note_original_management/`
  - 最小版 Sort: `assets/minimal_sort_original_management/`
  - 実運用版 Sort: `assets/sample_sort_original_management/`
  - 最小版 Calendar: `assets/minimal_calendar_original_management/`
  - 実運用版 Calendar: `assets/sample_calendar_original_management/`

## extension policy
- 今後カレンダーなど別パターンを足すときは、`references/` に新パターンの手順を追加し、`assets/` に対応サンプルを増やす
- `Standard Screen` からの移行知見は、画面パターン本体ではなく migration 系 reference に寄せる
- `SKILL.md` には共通方針だけを残し、具体コードは `assets/` へ寄せる
