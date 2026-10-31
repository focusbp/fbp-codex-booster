---
name: fbp-passkey
description: Implement and maintain FBP administrator passkeys, password-login opt-out, and confirmed CLI-only emergency recovery when all administrator passkeys are lost.
---

# fbp-passkey

FBP管理ユーザーのパスキー認証・パスワード認証停止・全紛失時の復旧を扱う。サービス側の独立会員認証とは区別する。環境固有の接続・同期・リリースは対象環境の手順を使う。

## 実装の位置とデータ

- 現在の先行実装は `app-soshikikaikaku` の `classes/app/passkey`、`classes/app/login`、`classes/app/password_reset`、`classes/app/_dsp`。共通フレームワークへ移植済みとは扱わず、対象で利用可能なクラス・配置・配布状況を確認する。
- 既存userの固定形式は変更しない。`passkey/identity` はuser_idと1対1で、user_handle、epoch、password_login_disabledを保持する。`passkey/user_passkey` はuserと1対多で、RP IDごとの資格情報を保持する。
- user_handleはユーザー再作成時の旧鍵流用を防ぎ、epochはセッション・remember-meの失効に使う。両方を認証時に最新値と照合する。
- ドメインごとにRP IDとoriginを検証する。パスキーは登録先RPの範囲で使い、パスワード認証停止はユーザー単位で全RPに適用する。
- 複数DBの判定・更新は [fbp-db](../fbp-db/SKILL.md) に従い、全参加DBを先に開いてから最新値を読む。独自flockを追加しない。

## パスワード認証停止

- 成功したパスキーログインの後、併用中のユーザーに「ID/Passwordのログインを停止しますか？」と「停止する（安全）」「併用を続ける」を表示する。停止済みなら省略する。
- 停止変更はCSRF、期限内の署名検証済み本人確認、未消費のログイン直後の選択状態を必須とする。認証方法をPOST値だけで信用しない。
- 停止後はパスワード照合、remember-me、メールトークン復旧からの迂回を拒否する。epochを更新して旧セッションを失効し、現在のパスキーセッションは更新して継続する。
- 停止後の鍵追加・削除ではパスキーで再認証する。challengeは通常ログインと用途を分離する。各RPの最後の有効鍵は削除できない。

## 管理者の全パスキー紛失時

サーバーへのアクセス権を持つ運用者が、専用PHPをCLIで実行して復旧する。Webから解除するためのファイル設置方式やuser.dat削除を復旧手順にしない。復旧コマンドの実行は認証状態を変更するため、実装・文書化の依頼だけで実アカウントをリセットしない。

先行実装の入口は `classes/app/passkey/reset-admin-cli.php`。配布されたアプリのルートで次のように実行する。IDは対象の有効な管理者のものを指定し、1を一般的な固定値として扱わない。

```sh
php classes/app/passkey/reset-admin-cli.php <admin-user-id>
```

### 必須動作

1. PHP_SAPIがcli以外なら、bootstrap・DBアクセスより前に403で拒否する。処理本体もCLI限定とし、通常のWeb関数呼出し・通常のapp_callから実行できないようにする。
2. 対象環境、管理者ID、変更内容を表示し、完全一致の `YES` 入力を求める。小文字、空白付き、空入力、EOFは変更せず中止する。確認待ちの間はDBを開かない。
3. userを残し、現在のパスワード、権限、有効状態を維持する。有効な管理者であることをロック取得後に再確認する。パスワードを忘れた場合の再発行は別手順とする。
4. identityのepochとuser_handleを更新し、旧セッション・Cookie・旧ハンドルの鍵を無効化する。全RPの既存パスキーを失効させ、発行済みパスワード再設定トークンを消去する。その後でpassword_login_disabledを0へ戻す。
5. DSPの例外はCLIチャネル、専用クラス・関数、YES確認済みのプロセス内状態に限定する。POST・GET・環境変数だけで確認済み状態を作らない。通常のCLIへ一律の許可を与えない。
6. 秘密を含めず実行と対象IDをサーバーログへ記録する。処理未完了・異常時は非0で終了する。CLIラッパーがJSONの内側の失敗を終了コード0にしてしまう場合も検出する。

FFMは複数DBの一括ロールバックを提供しない。失効を先に行い、認証再開を最後にする。途中失敗ではエラーを確認して再実行できる構造にする。復旧後はID／Passwordでログインし、パスキーを再登録してからパスワード認証を停止する。

## 検証

- 実アカウントを使わず、隔離した実FFMデータでユーザー・パスワード保持、全RPの鍵失効、epoch・handle更新、停止解除、トークン消去を確認する。
- 実際の専用CLIでYES成功、NO・EOF中止、存在しないID・管理者以外の拒否、異常時の非0終了を確認する。
- HTTPの直接アクセス拒否と、通常のapp_callからの拒否を確認する。DSP ONで実CLIチャネルを使い、admin固定のモックだけで許可判定を済ませない。
- 通常ログイン・署名検証・旧セッション拒否の既存テストを使って回帰を確認する。CLI確認は [fbp-cli](../fbp-cli/SKILL.md)、DSP変更は [fbp-dsp](../fbp-dsp/SKILL.md) に従う。
