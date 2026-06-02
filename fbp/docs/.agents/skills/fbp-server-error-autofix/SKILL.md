---
name: fbp-server-error-autofix
description: Use when handling server errors from a `[ServerError:123]` prompt, inspect related errors yourself, propose a fix first, then repair the real root cause in NetBeansProjects after user approval, sync to web, and verify minimally.
---

# fbp-server-error-autofix

## trigger conditions
- ユーザーが `[ServerError:74]` のように server error ID を明示して調査・対応を依頼した
- `file_path` / `http_host` だけでは framework か app かを決めきれず、修正実態ベースで `app_name` を決めたい

## manual flow for `[ServerError:xxxx]`
1. ユーザーが `[ServerError:74]` のように server error ID を伝える。
2. Codex はまず `/home/nakama/scripts/server_error.sh get 74` で単票を取得し、必要に応じて `list|get|search|raw_get` で related errors を確認する。
3. `app_name`、`file_path`、`http_host` だけで対象を決め打ちせず、必要なら `/home/nakama/scripts/sftp_api.sh get app-xxx` で source/test/release 情報を解決する。
4. この段階ではコード変更を始めず、server error の要約、想定 root cause、修正対象（Framework か app か）、最小確認方法をユーザーへ提案する。
5. ユーザー承認後にだけ実装し、`copy_to_web.sh` でテスト環境へ反映し、最小検証 1 回だけ行う。
6. ユーザー確認後にだけ本番反映、server error のステータス更新、関連コメントや完了処理へ進む。

## manual flow notes
- `[ServerError:74]` のような short prompt だけで来た場合の停止ポイントは「単票取得」「対象解決」「対応提案」までとする。
- ユーザー承認前はコード変更、`copy_to_web.sh` 実行、本番反映、ステータス更新を行わない。
- ユーザー確認前は本番反映や完了処理を行わない。
- 自動 claim や自動実装を前提にしない。手動で prompt に貼り付けて進める運用を正本とする。
- 本番リリースは手動運用とし、Codex が `release_fw5.sh` や `release_project.sh` を自動実行しない。

## workflow
1. 指定された 1 件は起点にすぎない。`/home/nakama/scripts/server_error.sh list|get|search|raw_get` を使って related errors を自分で集め、同根原因かどうかを判断する。
2. まず `file_path`, `class_name`, `function_name`, `message` から、直接対応するローカル実装を特定する。
3. `NetBeansProjects` 側だけを編集する。`web/*` を編集元にしない。
4. framework 修正は複数プロジェクト共通の欠陥と説明できる場合だけ選ぶ。単一 app の欠落や構成差は app 側で直す。
5. 修正後は `copy_to_web.sh` を必ず実行する。framework 修正は `copy_to_web.sh app-framework5` を使う。
6. 検証は最小限に留める。完了条件を満たしたら追加探索せずに結果を要約する。

## completion rules
- 基本の終了条件は `修正 -> web反映 -> 最小検証1回 -> 結果報告`
- `ParseError` / `syntax error` は、原則 `php -l` を主検証にする
- `public_pages` の単発不具合は、原則として対象 URL の `curl` 1 回を主検証にする
- template 欠落・Smarty 例外・画面 1 枚で閉じる不具合は、広い CLI 確認に広げない
- `app_call` / `app_check` は、その不具合の性質上どうしても必要な場合だけ 1 回に限って使う
- 関連 CLI や探索を広げすぎない
- `release_fw5.sh` や `release_project.sh` は自分では実行しない

## app_name rules
- `file_path` や `http_host` はヒントであって決定条件ではない
- 最終的な `app_name` は、実際に修正したコードと実行した `copy_to_web.sh` に合わせて決める
- 返却値は `Framework` / `app-xxx` / `app-xxx, Framework`
- コード変更が不要なら `app_name` は空でもよい

## hold guidance
- 既に修正済みでコード変更が不要なら `hold`
- 再現しない、または修正方法を確定できずコード変更で対応できない場合も `hold`
- `memo` には原因・変更内容・検証結果・未実施事項だけを簡潔に残す
