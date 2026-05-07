# db_additionals and post_action_class migration

`Standard Screen` で使っていた拡張機能を `Original Screen` へ移すときの指針。

## db_additionals

### basic policy

- 原則として `db_additionals` をそのまま引き継がない
- 必要なボタンや処理は `Original` クラス内に寄せる
- `ajax-link` と同一クラス `invoke-function` で閉じる

### migration steps

1. `db_additionals` の一覧を取得する
2. 各ボタンの `place` を確認する
3. 行単位か、一覧上部か、詳細内かを分類する
4. 呼び出している class/function と受けている引数を確認する
5. `Original` 画面のどこに置くか決める
6. 同等の関数を `<tb_name>_original_management` に実装する

### typical mapping

- 一覧上部ボタン
  `list.tpl` の上部アクションへ移す
- 行ボタン
  `list_area.tpl` の右端アイコン列や補助列へ移す
- ダイアログ内ボタン
  `add.tpl` / `edit.tpl` 内へ移す

### caution

- 標準画面の選択行依存は、そのままでは移せない
- `id`, `parent_id`, `child_id` など必要な値は明示的に渡す
- 既存の見た目より、処理責務を同クラスへ集約することを優先する

## post_action_class

### basic policy

- 保存後処理は「残す / 呼ぶ / 吸収する」のどれにするかを先に決める
- 小さな補助処理なら `Original` クラス内へ吸収する方が保守しやすい
- 他画面と共有されている処理なら既存 class を呼ぶ選択肢もある

### migration steps

1. どの保存タイミングで呼ばれているか確認する
2. 実際に何をしているか読む
3. `add / edit / delete` のどこへ組み込むか決める
4. バリデーション成功後だけ走るようにする
5. `close_multi_dialog()` と `reload_area()` の順序を崩さない

### decision guide

- 画面専用の軽い処理:
  `Original` クラスへ吸収する
- 複数画面で共有している処理:
  既存 class を呼ぶ
- 複雑で責務が重い処理:
  補助 class を残しつつ、`Original` から明示的に呼ぶ

### caution

- `res_error_message()` がある状態で副作用を走らせない
- エラー時に再描画や再呼び出しを足さない
- メール送信、child 更新、連携 API などの副作用は `data_get` だけでは確認不足になりやすい

## recommended output

移行前に次の対応表を簡単に作る。

- `db_additionals` 対応表
  `元ボタン名 / 元place / 元class.function / 新しい配置 / 新しいfunction`
- `post_action_class` 対応表
  `元class / 実行契機 / 処理内容 / 残すか / 吸収するか / 検証方法`
