---
name: fbp-new-project
description: Create a new FBP app project whose appcode starts with app-, using the single auto-createproject.sh entrypoint and waiting for it to finish without extra default tests.
---

# fbp-new-project

## trigger conditions
- ユーザーが `app-` で始まる新規プロジェクトの作成、設定、セットアップを依頼する。

## workflow
1. 対象 `appcode` をユーザー指定から確定する。
2. 次を実行して、コマンドが終了するまで待つ。
   `~/scripts/auto-createproject.sh <appcode>`
3. 成功終了を完了条件とする。追加の `app_call`、`app_check`、疎通確認、ログイン確認は基本的に行わない。
4. 失敗した場合だけ、表示されたエラーを起点に必要最小限で調査する。

## notes
- `auto-createproject.sh` はリモート作成、Let's Encrypt 完了待ち、SVN checkout、web 配置、初期設定まで含む正規入口。
- 下位 script を直接呼び分けない。
- `app-` で始まらないプロジェクトにはこの Skill を使わない。
