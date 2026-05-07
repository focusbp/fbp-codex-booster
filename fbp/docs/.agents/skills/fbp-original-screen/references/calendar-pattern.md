# Weekly calendar pattern

`Original` 種別で、標準カレンダーより自由度の高い週表示画面を作る。

## variant choice

- 最小版:
  週移動と CRUD だけを最短で確認する。`assets/minimal_calendar_original_management/`
- 実運用版:
  上部1行レイアウト、タイムゾーン、`month_day_format`、専用 CSS、`travel_before / travel_after` を使った移動時間表示まで含める。`assets/sample_calendar_original_management/`

## file layout

```text
classes/app/<tb_name>_original_management/
├── <tb_name>_original_management.php
└── Templates/
    ├── calendar.tpl
    ├── calendar_area.tpl
    ├── add.tpl
    ├── edit.tpl
    └── delete_confirm.tpl
```

## implementation shape

1. `run()` で週カレンダー領域を表示する
2. カレンダー本体は `calendar_area.tpl` に分離する
3. 週移動は `set_week()` で `previous / next / current / jump` を受ける
4. 追加 / 編集は `show_multi_dialog()`、削除は確認ダイアログを使う

## design defaults

- クラス名は `<tb_name>_original_management`
- カレンダー領域 id は `<tb_name>_original_management_calendar_area`
- 週の開始は月曜を標準にする
- 上部は `左: 前週 / 今週 / 次週 / 日付ジャンプ / 期間 / タイムゾーン`、`右: 追加` を標準にする
- `Jump` はテキストボタンよりアイコン型を標準にする
- `calendar.tpl` には余計な検索エリアを置かず、必要な場合だけ後から足す
- 予定カードは `row_style` を使い、編集・削除は右上アイコンに寄せる
- 期間表示は `date_format`、日ごとの見出しは `month_day_format` を使う
- カレンダー見た目は専用 `style.css` を持ち、`db_exe` の週カレンダー相当をベースにする
- 標準カレンダーの UI をそのまま再現するより、業務に必要な情報を見せることを優先する

## recommended first scope

- 週表示
- 追加 / 編集 / 削除
- `datetime` と `duration` を使った時間帯表示
- `setting.month_day_format` を使った日別タイトル表示

## travel time option

移動時間が業務上重要なら、実運用版では `db_exe` 週カレンダーに近い次の構成を最初から採用してよい。

- 追加項目:
  `travel_before`, `travel_after`
- 行データで作る派生値:
  `travel_start_time`, `travel_end_time`
- カレンダー補助配列:
  `occupied_travel`, `assigned_travel`

使い方は次の通り。

- `travel_before` 分だけ開始前を占有帯として塗る
- `travel_after` 分だけ終了後を占有帯として塗る
- 最初の該当時間帯に `移動開始 HH:MM` / `移動終了 HH:MM` を出す
- 表示レンジの `startHour` / `endHour` も移動時間込みで広げる

このパターンは、訪問、面談、現地作業など「予定本体の前後に拘束時間がある」ノートで有効。

## required fields for travel pattern

移動時間パターンを使う場合は、少なくとも次の項目をノートに用意する。

- `datetime`
- `duration`
- `travel_before`
- `travel_after`

必要なら予定カードには次もよく載せる。

- `status`
- `detail`
- `parent_id` に紐づく親名称

## extension ideas

- 担当者別色分け
- 終日予定の別レーン表示
- 親 / 親の親データの同時表示
- クリックで右ペイン詳細表示
- ドラッグ移動

## code source

最小骨組みは `assets/minimal_calendar_original_management/`、実運用寄りの雛形は `assets/sample_calendar_original_management/` を使う。  
将来は月表示やドラッグ移動パターンを別 assets で増やす。
