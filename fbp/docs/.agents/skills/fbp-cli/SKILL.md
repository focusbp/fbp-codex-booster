---
name: fbp-cli
description: Execute and verify FBP features through cli.php commands including app_call/app_check and schema/data inspection.
---

# fbp-cli

## trigger conditions
- 実装前後のCLI確認が必要
- `app_call` / `app_check` で画面導線を検証したい
- `db_*` / `data_*` / `cron_list` 等の状態確認をしたい

## workflow
1. 初動3点を確認: `db_schema`, `db_tables_list`, `db_additionals_list`。
2. 必要なら `cron_list`, `webhook_rule_list`, `embed_app_list` を確認。
3. 一括投入は「1コマンド1JSON」で実行し、各ステップの必須キーを事前検証してから流す。
4. 実装後は `app_call` で生レスポンス、`app_check` で期待値検証。
5. 更新系は `data_get` / `data_list` で結果を確認。

## quick commands
- 迷ったら `~/scripts/fbp_cli_check.sh --app app-xxx bootstrap` で `db_schema` / `db_tables_list` / `db_additionals_list` をまとめて確認する。
- 画面の生レスポンス確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx app_call setting page`
- 期待値検証:
  `~/scripts/fbp_cli_check.sh --app app-xxx app_check public_form index '{"get":{"key":"abc"}}'`
- 更新結果確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx data_get customers 1`
  `~/scripts/fbp_cli_check.sh --app app-xxx data_list customers 100`
- HMAC API 経由のデータ更新:
  `MGMT_API_MODE=test ~/scripts/db_api.sh add customers '{"data":{"name":"test"}}'`
  `MGMT_API_MODE=test ~/scripts/db_api.sh edit customers '{"data":{"id":1,"name":"updated"}}'`
  `MGMT_API_MODE=test ~/scripts/db_api.sh delete customers 1`
- 本番の実データを扱う場合:
  `MGMT_API_MODE=production ~/scripts/db_api.sh getall customers 10`
  `MGMT_API_MODE=production ~/scripts/db_api.sh add customers '{"data":{"name":"test"}}'`
- 追加 JSON が必要な場合は、`app_call` / `app_check` の第3引数に `post` / `get` / `files` / `output_file` をそのまま渡す。
- ラッパーで足りない CLI はそのまま透過実行できる:
  `~/scripts/fbp_cli_check.sh --app app-xxx cron_list '{"id":1}'`

## bulk execution safety
- 長い複合コマンドを `bash -lc '...'` に多重クォートして実行しない（特に `php -r` / ヒアドキュメント混在を禁止）。
- 一括処理は `/tmp` 等に実行スクリプトを作成し、`set -euo pipefail` 付きで `bash /tmp/<script>.sh` で実行する。
- JSON は各操作ごとに個別で組み立てる（`db_tables_add`, `db_fields_add`, `screen_fields_add`, `data_add` を混在させた巨大1発JSONを作らない）。
- 失敗時はその場で停止し、成功済みの確認コマンド（`*_list` / `data_list`）で再開位置を特定してから再実行する。

## purpose templates
- 初動確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx bootstrap`
- 画面1枚の確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx app_call <class> <function>`
- POST付き更新確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx app_call <class> <function> '{"post":{"id":1}}'`
  直後に `~/scripts/fbp_cli_check.sh --app app-xxx data_get <table> <id>` を実行する。
- 一覧反映確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx data_list <table> 100`
- 公開導線確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx app_check <class> <function> '{"get":{"key":"abc"}}'`
- screen_fields の確認:
  `~/scripts/fbp_cli_check.sh --app app-xxx screen_fields_list <tb_name> <screen_name>`
- 生の CLI でしか表せない場合:
  `php /home/nakama/web/app-xxx/fbp/cli.php <command> --json='{}'`

## required key checks
- `db_fields_add`: `db_id`, `parameter_name` は必須。
- `screen_fields_add`: `tb_name`, `screen_name`, `parameter_name` は必須。
- `db_tables_edit` / `db_fields_edit` / `screen_fields_edit`: `id` は必須。
- 一括投入前に、必須キー不足があるJSONを投入しない。

## known pitfalls
- `data_list` は `table` だけでなく `max` も要求される環境がある。`{"table":"x","max":100}` 形式で呼ぶ。
- `db_api.sh` は read-only 専用ではない。`add` / `edit` / `delete` があるが、調査・承認前は `get` / `getall` / `select` / `filter` / `find` だけに限定する。
- `db_api.sh add` は `data.id` を無視して新規IDを採番する。`edit` は `data.id` 必須、`delete` は数値ID必須。
- `db_fields_list` の結果に `tb_name` が含まれない環境がある。`db_id` と `db_tables_list.id` を対応させてテーブル名を特定する。
- `db()->insert()` / `update()` は参照渡し実装のため、配列リテラルを直接渡さず変数に入れてから渡す。
- `app_call` の戻りには `request.post` / `request.get` / `console_log` が含まれる。送信値の不整合確認はまずここを見る。
- アプリ受信後のPOST全体を確認したい場合は、対象関数に一時的に `$ctl->console_log($ctl->POST());` を入れると CLI の `console_log` に出る。
- shell 直打ち時の `--json='...'` クォート崩れが多い場合は、まず `~/scripts/fbp_cli_check.sh` に寄せる。

## constraints
- 実行ディレクトリは対象環境ルールに従う（環境固有は framework-development を参照）。
