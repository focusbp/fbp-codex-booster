# 独自管理画面のレスポンシブ対応

最初に [標準画面のCSS方針](../../fbp-standard-screen/references/responsive-css-policy.md) を読む。ここではその方針を、独自の検索・一覧・操作を維持したまま組み込む。

## テンプレート

実運用版CRUDサンプルの以下3ファイルを組として使う。

- [list.tpl](../assets/sample_note_original_management/Templates/list.tpl): 画面マーカー、CSS読込、自動検索、一覧領域。
- [list_area.tpl](../assets/sample_note_original_management/Templates/list_area.tpl): `row_title` / `row_value`、操作セル、0件表示。Ajaxでもこの構造を返す。
- [responsive_style.tpl](../assets/sample_note_original_management/Templates/responsive_style.tpl): 表示モード付きCSS。案件側の `Templates/` へコピーし、画面外枠から一度includeする。Skillのファイルを実行時に直接参照しない。

サンプルのノート名・クラス名・フォームID・更新領域ID・項目を案件へ合わせる。新規画面では既存のPHPサンプルと組み合わせる。既存画面の表示調整では、現在の入口・権限・検索・保存・集計・決済などを維持し、テンプレート部分を移植する。`screen_build_type` やクラス名を変える必要はない。

## DOMとCSSの組込み

1. メイン画面外枠へ `original_screen_responsive_page` を付け、`responsive_style.tpl` をincludeする。このマーカーをダイアログやサイドパネルへ付けない。
2. 一覧へ `original_screen_table` を付け、業務セルを `.row_style > .row_title + .row_value` の形にする。値は `fields_view_direct` を優先する。集計済み・複合表示などhelperで表せない値だけ個別にエスケープして出力する。
3. 操作セルへ `original_screen_action_cell`、0件セルへ `original_screen_empty_row` を付ける。アイコンのみのボタンにも `aria-label` を付け、元の `data-class` / `data-function` / `data-id` を維持する。
4. 検索は `search_box` / `search_form_flex` / `search_form_item` を使う。日付範囲は `search_date_range`、日時範囲は `search_datetime_range` で囲む。既存の自動検索か明示的検索かを維持する。
5. 新CSSと競合する旧モバイルCSS・列固定幅を対象画面の範囲で整理する。`table.moredata`、`.multi_dialog`、すべての `table` などをアプリ全体で一括上書きしない。`#main_table` や `db_exe_*` を借りる必要はない。

サンプルは通常の一覧用。thead付き表では、モバイルでtheadを隠しても各セル内に項目名を残す。ソート画面では専用ハンドルセルを別扱いにし、[sort-pattern.md](sort-pattern.md) と共通CSS方針に従う。ダイアログの入力グリッドも調整が必要なら、別の専用クラスでモバイル時1列にする。全ダイアログの入力幅を一括変更しない。

## 確認

- 320または375px、700px、701px、PC幅で、カード／表の切替とPC→スマホ→PCの復元を確認する。701px以上の既存の最小幅は維持する。
- 「レスポンシブ」「パソコン」の両設定を確認する。パソコン設定では狭い画面でも表形式を維持し、テストで変えた設定は戻す。
- 0件・複数件・長いメール／URL／日本語・複数操作ボタンを確認し、モバイルでページ全体の横はみ出しや文字つぶれがないことを測る。
- 検索、追加読込、編集ダイアログを開いて閉じる操作、画面移動後のCSSの影響範囲を確認する。並び替えを持つ画面はカード状態での操作とPCへ戻した列幅も確認する。
- CLIだけで表示崩れの解消を判定しない。アプリへ適用したときは `fbp-playwright` に従って画面検証する。Skillのみを更新したときは、サンプルの構文・参照整合性を確認し、案件への適用済みとは扱わない。
