---
name: fbp-menu
description: Design, implement, or adjust FBP side menu items, menu-only screens, internal/external menu links, homepage menu settings, parent/child menu rules, and permission-based menu visibility/access controls. Use when users ask to add something to the side menu, hide/show menu items by role or user, create menu entries for Standard Screen or Original Screen pages, add non-note custom functions to the menu, or control management/admin menu visibility.
---

# fbp-menu

## trigger conditions
- サイドメニューにノート、画面、独自機能、内部リンク、外部リンクを追加・整理する。
- `show_menu` / `menu_name` / `sort` / `menu_visibility` を設定する。
- ホームページURL、公開ページ、プロジェクトポータル、外部サービス、マニュアルなどをメニュー導線にしたい。
- 親テーブル・子テーブル・小テーブルのメニュー表示方針を決める。
- フィールドなし/メニュー用ノートを作り、Original Screenで独自画面を開く。
- 権限、ロール、ユーザーごとにメニュー項目を表示/非表示にする。
- メニュー非表示だけでなく、画面アクセス自体も拒否する必要がある。
- 管理者専用、オーナー専用、一部ユーザー専用の管理メニューを作る。

## workflow
1. メニュー項目の種類を決める。
   - 通常CRUDなら `fbp-standard-screen` を使う。
   - 画面自由度が必要、または「Original Screen指定」なら `fbp-original-screen` を使う。
   - Dashboardなら `fbp-dashboard` を使う。
   - DB定義や `show_menu` 等の変更が必要なら `fbp-db` を使う。
2. メニューに出す対象を決める。
   - ノート管理画面
   - ノート管理ではない独自機能
   - 内部リンク
   - 外部リンク
   - 管理者/オーナー/一部ユーザー向け機能
3. メニュー表示だけで十分か、アクセス制御も必要かを判定する。
4. 権限による制御では、必ず `can_show_menu()` と `can_access()` をセットで実装する。
5. 実装後はメニュー表示とURL直打ち/直接遷移の両方を検証する。

## menu item types
- `ノート管理画面`: 顧客、案件、タスクなどの一覧・追加・編集・削除を行う通常画面。
- `ノート管理ではない独自機能`: ダッシュボード、集計、外部連携操作、案内ページ、設定ウィザードなど。
- `内部リンク`: 同じシステム内の公開ページ、申込ページ、マイページ、テスト環境、本番環境などへの導線。
- `外部リンク`: ホームページ、予約サイト、ECサイト、Googleフォーム、マニュアル、外部SaaSなどへの導線。
- `メニュー用ノート`: データ項目を持たない、または最小項目だけを持ち、Original Screenの入口として使うノート。

## standard menu fields
- `show_menu`: サイドメニューに表示するノートは `1`。子テーブルや補助テーブルは原則 `0`。
- `menu_name`: ユーザーに見せるメニュー名。
- `sort`: 表示順。
- `menu_visibility`: フレームワーク標準の管理者向け表示制御に使う。`1` は app admin のみ表示。
- `screen_build_type`: Standard Screen / Original Screen の選択に使う。
- `show_icon_on_parent_list`: 小テーブルを親一覧でアイコン表示する場合に使う。
- `cascade_delete_flag`: `parent_id` 連結の小テーブルで、親削除に追従させる場合は Cascade Delete。

## parent child defaults
- 親テーブルは `show_menu=1`。
- 子テーブルは原則 `show_menu=0`。
- 小テーブルは親一覧でアイコン表示を検討する。
- 小テーブルが `parent_id` 連結なら `cascade_delete_flag=1` を検討する。
- 子テーブルを単独で業務画面として使う要件が明確な場合だけ、子でも `show_menu=1` を検討する。

## original screen menu entries
- 「サイドメニューに独自画面を追加したい」場合は、メニュー表示用ノートを作り、`screen_build_type=Original Screen` にして `<tb_name>_original_management/run` を実装できる。
- 単なるCRUDノートに Original Screen を使わない。Standard Screenで足りる場合は Standard Screen を使う。
- Original Screenにしたノートは、同じ作業で `<tb_name>_original_management/run` を必ず実装し、`Original management class not found` が出ないことを確認する。
- メニュー用ノートは名前で役割が分かるようにする。例: `sales_dashboard`, `external_links`, `system_guide`。

## homepage and links
- ホームページURLをメニューに出す場合は、アプリ設定の `website_url` と `show_menu_homepage` を使う。
- フレームワークの base menu は `setting.website_url` を `normalize_external_url()` し、`show_menu_homepage=1` の場合にホームページメニューを表示する。
- Project Portal URL は `project_portal_url` として base menu に渡される。
- 独自リンクを増やすときは、既存の base menu / DBメニュー / Dashboard登録 / 設定値で実現できるかを先に確認する。
- `/common/menu.tpl` の追加は既存互換用と考え、通常のメニュー追加では優先しない。

## permission based menu visibility
- FBPはテーブル名に対応する `<tb_name>_visibility_filter` を自動検出する。
- ファイル配置:
  - `classes/app/<tb_name>_visibility_filter/<tb_name>_visibility_filter.php`
- クラス名:
  - `<tb_name>_visibility_filter`
- メニュー表示制御:
  - `can_show_menu(Controller $ctl, string $table_name = "", array $db_setting = []): bool`
- 画面アクセス制御:
  - `can_access(Controller $ctl, string $function_name = "", string $table_name = "", array $db_setting = []): bool`
- メニューを隠すだけでは権限制御にならない。必ず `can_access()` でも拒否する。
- `can_show_menu()` がない場合、base menu は `can_access($ctl, "menu", ...)` を代替で呼ぶ。ただし意図を明確にするため、メニュー制御では `can_show_menu()` を実装する。
- `db_exe` 側は対象画面実行時に `can_access()` を確認し、拒否時は通知して処理を止める。

## visibility filter pattern
```php
<?php

require_once dirname(__FILE__) . "/../sample_access_guard/sample_access_guard.php";

class target_table_visibility_filter {

    function can_access(Controller $ctl, string $function_name = "", string $table_name = "", array $db_setting = []): bool {
        return sample_access_guard::can_view($ctl);
    }

    function can_show_menu(Controller $ctl, string $table_name = "", array $db_setting = []): bool {
        return sample_access_guard::can_view($ctl);
    }
}
```

## guard class pattern
- owner判定、ユーザー権限、許可テーブル参照は `<feature>_access_guard` に寄せる。
- 複数テーブルに同じ制御を入れる場合、各 visibility filter は guard を呼ぶだけにする。
- オーナー固定IDなど、プロジェクト固有の判断は docs に方針を残し、guard に集約する。
- 権限設定が必要なら、許可テーブルを作り、Original Screenで設定画面を作る。

Example responsibilities:
- `is_owner($ctl)`
- `can_view($ctl)`
- `can_manage($ctl)`
- `deny($ctl, $message)`
- `get_permission_row($ctl, $user_id)`
- `set_allowed($ctl, $user_id, $allowed)`

## admin menu control
- 管理者だけに見せるだけなら、まず `menu_visibility=1` が使えるか確認する。
- オーナーだけ、一部ユーザーだけ、契約プラン別などの条件がある場合は visibility filter を使う。
- 管理メニューの非表示と画面アクセス拒否は必ずセットにする。
- 権限設定画面自体も visibility filter または Original Screen側の guard で保護する。

## verification
- `db_tables_list` または対象データ確認で `show_menu`, `menu_name`, `sort`, `menu_visibility`, `screen_build_type` を確認する。
- 対象ユーザーでログインし、左メニューに出る/出ないを確認する。
- 非許可ユーザーでURL直打ちやメニュー以外の導線からアクセスし、拒否されることを確認する。
- Original Screenのメニュー用ノートは、`db_exe/page` が成功し `Original management class not found` が出ないことを確認する。
- ホームページURLなど外部リンクは、設定値が空の場合にメニューが出ないことも確認する。

## implementation notes
- URL生成は `$ctl->get_APP_URL()` を使う。
- visibility filter は表示制御の入口であり、データ行単位の絞り込みが必要な場合は `get_filter_conditions()` も検討する。
- 権限エラーは `show_notification_text()` で明確に返す。
- セキュリティ要求では、メニュー非表示だけで完了扱いにしない。
