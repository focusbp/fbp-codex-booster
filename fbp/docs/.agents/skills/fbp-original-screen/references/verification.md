# verification

`Original` 管理画面は自由度が高い分、最低限の CLI 検証を固定化する。

## base checks

- 表示確認
  `~/scripts/fbp_cli_check.sh --app app-xxx app_call <tb_name>_original_management run`

- 一覧部分更新確認
  `php /home/nakama/web/app-xxx/fbp/cli.php app_call --json='{"class":"<tb_name>_original_management","function":"apply_filter","post":{...}}'`

- 追加結果確認
  `~/scripts/fbp_cli_check.sh --app app-xxx data_get <tb_name> <id>`

- 一覧確認
  `~/scripts/fbp_cli_check.sh --app app-xxx data_list <tb_name> 100`

## expected checks

- `run` で対象テンプレートが描画される
- `apply_filter` が `reloadarea` で一覧領域だけ返す
- 追加 / 更新 / 削除後に `data_get` / `data_list` で反映が見える
- `res_error_message()` を使う項目には対応する `error_項目名` がある

## caution

- `cli` は必ず `web/app-xxx` 側を対象に実行する
- `Original` 種別でも URL は直書きせず `$ctl->get_APP_URL()` を使う
