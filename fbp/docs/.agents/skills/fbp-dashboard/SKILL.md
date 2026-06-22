---
name: fbp-dashboard
description: Build dashboard widgets with class registration, 3-column width rules, and panel-based management.
---

# fbp-dashboard

## trigger conditions
- Dashboard画面にウィジェットを追加・修正したい
- `show_dashboard_widget("template.tpl")` を使う実装を行う
- Dashboard登録（class/function/column_width/sort）の運用が必要

## workflow
1. ウィジェットクラスを作成し、`Templates/*.tpl` を用意する。
2. ウィジェット関数内で必要な `assign` を行い、最後に `show_dashboard_widget("xxx.tpl")` を呼ぶ。
3. `panel` の Dashboard管理から `class_name` / `function_name` / `column_width` を登録する。
4. 登録順は `sort` で制御し、必要なら並び替えを使う。
5. `app_call` で Dashboard表示を確認する。更新系を含む場合は `data_get` / `data_list` で反映確認する。

## recommended defaults
- `function_name` は原則 `dashboard` を使う。
- `column_width` の目安:
  - `1`: KPIカードや小さな数値表示
  - `2`: 通常サイズのチャート
  - `3`: 横幅を使うワイド表示

## constraints
- `show_dashboard_widget()` は各ウィジェット関数で必ず1回以上呼ぶ。
- 幅はDB側の `column_width`（1/2/3）を使う。関数側で幅を固定しない。
- `class_name` は `^[A-Za-z][A-Za-z0-9_-]*$`、`function_name` は `^[A-Za-z][A-Za-z0-9_]*$` を守る。
- `dashboard/page` をウィジェット登録しない（再帰防止）。
- 重い処理は避け、表示専用ロジック中心にする。
- メニューリンク追加で `/common/menu.tpl` は使わない。メニュー追加は DB追加 / Dashboard登録 / 設定の「ホームページを表示」で行う。
- Dashboard特有なのは「ウィジェット登録（dashboardテーブル）」と「呼び出し文脈」のみ。ダイアログ作成・フォーム処理・バリデーション・post_action連携は通常の管理画面クラスと同じ実装方針でよい。
- helper利用も通常の管理画面と同様に扱う（`fields_form_original` / `fields_form_direct` / `fields_view_direct`）。
- ウィジェット内でダイアログを使う場合も `show_multi_dialog()` を基準にし、固定操作は `fixed_bar_template` に分離する。
- バリデーションエラー時は `res_error_message()` を返して即 `return` し、`show_multi_dialog()` 再実行や `reload_area()` で再描画しない。
- `res_error_message()` を使う場合、表示先タグ（`error_項目名`）が存在することを事前確認する。表示タグを設置できない導線（フォーム未描画前・一覧ボタン直叩き等）では `show_notification_text()` を使う。

## layout rules
- 1行の幅上限は3。
- `current + next_width <= 3` なら同一行、超える場合は改行。
- 例: `1,2` は同一行。`1,3` は `1` の次に改行して `3`。

## chart widget example
- Chart.js は標準で読み込まれているため、ウィジェット側は `create_chart()` と `chart_draw()` を使う。
- `canvas id` は重複を避けるため毎回一意にする（例: `random_alphabet()` を利用）。
- 棒グラフでは、指定がない限り `backgroundColor` と `borderColor` を同じ色にする。輪郭だけ濃い色にすると意図せず古い印象になりやすい。

```php
function dashboard(Controller $ctl) {
    $canvas_id = "dashboard_chart_" . $ctl->random_alphabet(10);
    $chart = $ctl->create_chart();
    $chart->set_type("bar");
    $chart->set_labels(["Jan", "Feb", "Mar"]);

    $dataset = $chart->create_Dataset_Bar();
    $dataset->set_label("Orders");
    $dataset->set_data([12, 19, 9]);
    $dataset->set_backgroundColor("rgba(20, 184, 166, 0.62)");
    $dataset->set_borderColor("rgba(20, 184, 166, 0.62)");
    $chart->add_dataset($dataset);

    $ctl->chart_draw($canvas_id, $chart);
    $ctl->assign("canvas_id", $canvas_id);
    $ctl->show_dashboard_widget("sample.tpl");
}
```

## verification pattern
- 複数表示の改行確認は `column_width = 1, 2, 3` の3件で検証する。
- 期待値:
  - 1行目: `1 + 2`
  - 2行目: `3`

## troubleshooting
- Dashboardが1件しか見えない場合は、まず登録件数を確認する。
  - 例: `php fbp/cli.php data_list --json='{"table":"dashboard","max":100}'`
- 追加後に表示されない場合は、Dashboard画面へ再遷移またはブラウザ再読込を行う。
- ウィジェット内ボタンが反応しない場合:
  - `invoke-function` だけに依存せず、`<button class="ajax-link" data-class="WidgetClass" data-function="func">` を優先する。
  - Dashboard配下では呼び出し文脈が `dashboard` クラスになるケースがあり、`invoke-function` だけだと意図したウィジェット関数が呼ばれないことがある。
- ウィジェット内の file フィールドが 0 バイトでダウンロードされる場合:
  - `fields_view_direct` の file 表示は `download-link` に `data-class`/`data-function=download_file` を付けて呼ぶため、表示元クラスに `download_file(Controller $ctl)` 実装が必要。
  - 実装例: `path` を `decrypt` し、`is_saved_file` で検証後に `res_saved_file` を返す。
- ウィジェットやウィジェット内ダイアログで DB登録画像を `<img>` 表示する場合:
  - 保存パスや `public_media` をテンプレートから直接呼ばず、表示元クラスに `view_image(Controller $ctl)` を実装してそこを通す。
  - `view_image()` では `is_saved_file()` で検証後に `res_saved_image()` を返し、ダウンロードは `download_file()` で `res_saved_file()` を返す。
  - 画像CSSは原則 `max-width:500px;` を付け、縦サイズは固定しない。
