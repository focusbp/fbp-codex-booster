---
name: fbp-release
description: Release FBP app projects or the shared app-framework5 framework to production quickly, including the minimal preflight checks, NetBeansProjects-to-web sync, SVN add/commit behavior, release_project.sh usage, and framework release ordering.
---

# fbp-release

## trigger conditions
- ユーザーが `本番リリース`, `リリースして`, `production release` を依頼する
- `app-xxx` のテスト環境から本番へ反映する
- `app-framework5` / `fbp` 本体変更を本番へ反映する

## fast path for app release
1. 対象 `appcode` を直近文脈またはユーザー指定から確定する。通常は `release_project.sh` が `sftp_api.sh` を内部で呼ぶため、手順確認だけのために `sftp_api.sh` や `release_project.sh` 本文を読み直さない。
2. 直前にコード変更した場合は、必要な最小検証だけ行う。
   - PHP変更: `php -l /home/nakama/web/<appcode>/classes/app/...php`
   - 公開/画面導線: 直前に使った `app_check` または `app_call`
3. `NetBeansProjects` の変更をまだ `web` に反映していない場合だけ実行する。
   - `~/scripts/copy_to_web.sh <appcode>`
4. SVN管理プロジェクトでは、新規ファイルに注意する。
   - `svn status /home/nakama/NetBeansProjects/<appcode>` で確認する。
   - `?` のうち今回作業で追加した必要ファイルだけ `svn add` する。
   - 無関係な `?` ファイルや別作業のディレクトリは追加しない。
   - `release_project.sh` は tracked 変更を自動 commit するが、`?` ファイルは自動追加しない。
5. 本番リリースを実行する。
   - `~/scripts/release_project.sh <appcode>`
6. 成功条件は `{"ok":true,"message":"リリースが完了しました。" ...}`。SVN revision が表示された場合は最終報告に含める。

## if new files were missed
- リリース後に今回作業の `?` ファイルが残っていると分かった場合は、必要ファイルだけ `svn add` し、`~/scripts/release_project.sh <appcode>` を再実行して、SVNと本番リリース内容を揃える。
- すでに1回目の release API が成功していても、再実行は同じリリース経路で上書き反映される。

## framework release
- `release_project.sh <appcode>` はアプリの `classes/app` とリリース対象データを送る。`app-framework5/fbp` 本体は含まない。
- framework 側の変更が本番に必要な場合は、アプリリリースより先に framework リリースを行う。
  - `~/scripts/release_fw5.sh`
- framework release は広範囲に影響するため、ユーザーが明示した場合、または現在の変更が framework 依存で本番動作に必須な場合にだけ実行する。

## output rules
- APIキー、パスワード、署名、接続情報はユーザーへ出さない。
- 通常報告は、対象 appcode、成功メッセージ、SVN revision、実施した検証だけでよい。
- 失敗時は HTTP status やスクリプトのエラーメッセージをそのまま要約し、途中まで成功した commit/release がある場合は明確に分けて報告する。
