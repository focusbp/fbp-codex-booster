---
name: fbp-side-panel
description: Design, implement, or adjust FBP side panels for Standard Screen and Original Screen. Use when users ask for child records in a side panel, parent-list side panel icons, list_on_side fields, side panel widths, side panel row buttons, side panel bottom buttons, rows_child handlers, show_second_work_area, reload_side_panel, or post-action handling for side panel add/edit/delete flows.
---

# fbp-side-panel

## trigger conditions
- 親一覧から子ノートをサイドパネルで開く。
- サイドパネル内の一覧項目、追加、編集、削除、検索を調整する。
- `list_on_side` の項目設定が必要。
- 親一覧で子テーブルをアイコン表示したい。
- サイドパネルの幅、下部ボタン、行ボタン、再読み込みを調整する。
- Standard Screenのサイドパネルか、Original Screenのサイドパネルかを判断する。
- `rows_child()` / `show_second_work_area()` / `reload_side_panel()` を扱う。
- サイドパネル内の保存・削除後に `post_action_class` 相当の副作用が必要。

## terminology
- `サイドパネル`: 画面右側または左側に開く第二作業エリア。実装上は主に `show_second_work_area()` / `#work_area_second`。
- `標準画面サイドパネル`: Standard Screen の `db_exe::rows_child()` で表示する子ノート一覧。
- `オリジナル画面サイドパネル`: 子ノートの `<tb_name>_original_management::rows_child()` で表示する独自サイドパネル。
- `一覧エリア`: サイドパネル内の子レコード一覧。
- `サイドパネル内行ボタン`: サイドパネル内の各行に置く対象レコード単位の操作ボタン。
- `サイドパネル下部ボタン`: サイドパネル下部に置く追加、保存、閉じる、次へなどの操作ボタン。
- `メニュー画面`: `show_sidemenu()` / `#sidemenu`。サイドパネルとは別UIとして扱う。

## decision flow
1. 子ノートの通常CRUDで足りるなら Standard Screen サイドパネルにする。
2. サイドパネル内に独自検索、複雑な集計、特殊な行レイアウト、手動並び替え、専用ワークフローが必要なら Original Screen サイドパネルを検討する。
3. 子ノートが Original Screen の場合でも、`rows_child()` が不要なら標準サイドパネルへフォールバックできる。ただし意図が曖昧になるため、Original Screenでサイドパネルを作る要求なら `rows_child()` を明示実装する。
4. 保存・削除・状態変更で親集計、通知、連携、ログなどの副作用が必要なら、画面専用処理に閉じず `post_action_class` など共通経路へ寄せる。

## standard screen side panel
- 子ノートは親ノートと `parent_tb_id` / `parent_id` で接続する。
- 親一覧から開く子ノートは、子ノート側の `show_icon_on_parent_list` を Show にする。
- 子ノートを単独メニューに出す必要がなければ `show_menu=0` にする。
- `parent_id` 連結で親削除に追従させるなら `cascade_delete_flag=1` を検討する。
- 幅は子ノートの `list_width` を使う。`600`〜`1200` の範囲を目安にする。
- サイドパネルに表示する一覧項目は `screen_fields` の `list_on_side` に設定する。
- `list` に追加しただけでは、通常一覧には出てもサイドパネル一覧には出ない。
- `list_on_side` は横幅が狭い前提で主要3〜5項目程度に絞る。
- 長文、画像、大きいfile項目、多数列は避ける。
- 子レコード単体を判別できる項目を優先する。例: 名称、状態、日時、担当、金額。

## standard screen fields
| 用途 | 設定先 |
|---|---|
| サイドパネル内の一覧表示 | `list_on_side` |
| サイドパネルから追加する入力項目 | `add` |
| サイドパネルから編集する入力項目 | `edit` |
| サイドパネルから削除確認する表示項目 | `delete` |
| サイドパネル内検索 | `search` |

## standard screen side effects
- Standard Screenの追加・編集・削除は標準保存経路に乗る。
- 共通副作用は対象ノートの `post_action_class` に実装する。
- 親側の件数、合計、状態などを更新する場合も、Standard Screen、Original Screen、MCP Note CRUDで共通化できる経路へ寄せる。

## original screen side panel
- 子ノートを `screen_build_type=Original Screen` にする。
- `classes/app/<tb_name>_original_management/<tb_name>_original_management.php` に public `rows_child(Controller $ctl)` を実装する。
- `rows_child()` は標準サイドパネルと同じ最低限のPOSTを受ける:
  - `db_id`: 子ノートのDB ID
  - `parent_id`: 親レコードID
- `show_second_work_area("rows_child.tpl")` で表示する。
- 明示幅を渡さない場合、フレームワークから渡される子ノートの `list_width` が使われる。
- 明示幅を渡す場合は、要件上必要なときだけにする。
- 検索、ページング、追加、編集、削除、行ボタン、下部ボタン、再読み込みは自前で実装する。
- テンプレートには安定したwrapper IDを置き、必要な範囲だけ `reload_area()` できるようにする。

## original screen db_id caution
- 親一覧の `db_additionals` などから子サイドパネルを開く場合、POSTの `db_id` が親ノートのDB IDになっていることがある。
- `rows_child()` 内と、サイドパネル内の保存・削除処理前に、必ず子ノートのDB IDへ正規化する。
- `_side_panel["db_id"]` が親DB IDのままだと、`reload_side_panel()` が標準 `db_exe::rows_child()` に誤ルーティングすることがある。

## original screen side effects
- 子ノート変更時に親集計、状態更新、通知、外部連携、ログなどが必要なら、対象ノートの `post_action_class` があるか確認する。
- `post_action_class` に共通処理がある場合は、必要に応じて同じ副作用経路を呼ぶ。
- 画面内に同じ集計・通知処理を重複実装しない。
- 既存の `post_action_class` が標準保存前提で直接呼びにくい場合は、共通メソッドへ切り出して Standard Screen / Original Screen / MCP Note CRUD から呼べる形を検討する。
- 副作用を呼ばない判断をする場合は、なぜ不要かを説明できる状態にする。

## original screen forms and buttons
- 入力欄の横に保存・追加ボタンを置かない。操作ボタンは下部の操作行にまとめる。
- `サイドパネル内行ボタン` は対象レコード単位の操作だけに使う。
- `サイドパネル下部ボタン` は追加、保存、次へ、一括実行など、サイドパネル全体の操作に使う。
- バリデーションエラーは `res_error_message()` で返して即 `return` する。
- エラー時に `show_multi_dialog()`、全体 `reload_work_area()`、不要な全再描画をしない。
- 保存・削除後は `reload_side_panel()` または安定したwrapperへの `reload_area()` で更新する。

## permissions
- 親画面が見えることと、子サイドパネルを開けることは別に考える。
- 子ノートに権限制御が必要なら `<tb_name>_visibility_filter` を検討する。
- ボタンを非表示にするだけでは権限制御にならない。保存・削除・状態変更の実行時にも権限確認する。

## verification
- 親一覧からサイドパネルが開く。
- サイドパネル幅が `list_width` または指定幅どおり。
- `list_on_side` の項目が表示され、`list` だけに入れた項目へ依存していない。
- 子の追加・編集・削除後、サイドパネルが正しく更新される。
- 親側の件数、合計、状態などがある場合は更新される。
- `post_action_class` の副作用が必要な場合は実行される。
- 非許可ユーザーでサイドパネルを開けない、または操作できない。
- 親DB IDと子DB IDの取り違えがない。
- Playwright確認では `#work_area_second` の幅、表示有無、ボタンのクリック可否、`scrollWidth/clientWidth` を確認する。

## related skills
- DB定義、`list_width`、親子関係、`screen_fields`: `fbp-db`
- Standard Screen項目設定、標準保存、副作用: `fbp-standard-screen`
- Original Screen実装、`rows_child()`、テンプレート: `fbp-original-screen`
- ブラウザ確認: `fbp-playwright`
