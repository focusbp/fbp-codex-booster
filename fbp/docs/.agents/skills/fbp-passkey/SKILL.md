---
name: fbp-passkey
description: Implement and maintain FBP administrator passkeys, password-login opt-out, and confirmed CLI-only emergency recovery when all administrator passkeys are lost.
---

# fbp-passkey

FBP管理ユーザーのパスキー認証・パスワード認証停止・全紛失時の復旧を扱う。サービス側の独立会員認証とは区別する。環境固有の接続・同期・リリースは対象環境の手順を使う。

## 実装の位置とデータ

- 共通実装はframeworkの `app/passkey`、`app/login`、`app/password_reset`。app-soshikikaikakuの実証版を基に移植し、app-fwtestで検証した。本番配布済みとは扱わず、対象のクラス優先解決・配置・配布状況を確認する。
- 共通認証のパスキーは常時有効で、有効／無効フラグを持たない。旧設定のenabledも利用可否には使わない。RP設定はアプリの `classes/app/passkey/config.php`。環境の登録済みドメインから新規作成・ドメイン追加の工程で自動生成する。共通配置の `config.example.php` を参考にし、共通版利用時はアプリ側に `passkey.php` を置かない。認証付き `release_api::passkey_config` で設定だけを保存・GET読戻しできる。独自login/passkeyのアプリでは保存を拒否する。環境側の配布元にも設定を保持し、次回app配布で消えないようにする。更新時は既存RPホスト・信頼TLS peerを保持し、任意のHost／転送ヘッダーを設定元にしない。設定が未作成なら許可ドメインの自動配置を修復し、リクエストから許可を推測しない。
- 管理セッションのepoch照合は標準loginが選ばれるアプリにだけ適用する。独自login/user/password_resetを持つアプリの認証条件を共通版へ置換しない。
- 管理セッション確認は管理入口と保護DB/DSPの認証境界で実行し、画面権限判定 `authorize_management_access` から分離する。DSP内ではDBを読まない。検証結果は同一Controller・同一user_id/epoch・ログイン継続中に限り再利用し、未検証を許可しない。adminの認証保証とデータ固有の権限制限は区別する。
- 既存userの固定形式は変更しない。`passkey/identity` はuser_idと1対1で、user_handle、epoch、password_login_disabledを保持する。`passkey/user_passkey` はuserと1対多で、RP IDごとの資格情報を保持する。
- user_handleはユーザー再作成時の旧鍵流用を防ぎ、epochはセッション・remember-meの失効に使う。両方を認証時に最新値と照合する。
- ドメインごとにRP IDとoriginを検証する。パスキーは登録先RPの範囲で使い、パスワード認証停止はユーザー単位で全RPに適用する。
- 複数DBの判定・更新は [fbp-db](../fbp-db/SKILL.md) に従い、全参加DBを先に開いてから最新値を読む。独自flockを追加しない。

## 標準URLと独自ドメイン

- 案内・文書では、アプリ作成時に用意されるURLを「標準URL」、後から追加する利用者所有のドメインを「独自ドメイン」と呼ぶ。標準URLを「旧URL」と呼ばない。独自ドメインの追加後も標準URLを残す運用とする。
- パスキーは登録先ホストごとのRPに紐づく。標準URLで登録した鍵は標準URLで継続利用し、独自ドメインでは別途登録する。許可ホストの追加だけで登録済みパスキーが移るとは案内しない。
- 当面は手動運用。パスワード併用中なら独自ドメインでログインして登録する。パスワード停止済みで独自ドメインの鍵がない場合、管理画面は標準URLの既存パスキーで利用する。独自ドメインでの認証も必要になったら、個別に移行方法を検討する。
- パスワード停止は全RP共通で、既存の「他の端末にパスキーを設定する」も発行元RP限定。標準URLから独自ドメインへ登録権限を引き継ぐ導線は未実装。案内だけで実アカウントの停止解除・鍵失効・CLI復旧を実行しない。
- 独自ドメイン追加は環境の専用設定工程でRP設定まで同期する。台帳のdomain欄の手編集だけではRP同期されない。詳細・経緯は [共通化計画と運用記録](../../../passkey-framework-plan.md) を参照する。

## パスワード認証停止

- 成功したパスキーログインの後、併用中のユーザーに「ID/Passwordのログインを停止しますか？」と「停止する（安全）」「併用を続ける」を表示する。停止済みなら省略する。
- 停止変更はCSRF、期限内の署名検証済み本人確認、未消費のログイン直後の選択状態を必須とする。認証方法をPOST値だけで信用しない。
- 停止後はパスワード照合、remember-me、メールトークン復旧からの迂回を拒否する。epochを更新して旧セッションを失効し、現在のパスキーセッションは更新して継続する。
- 停止後の鍵追加・削除ではパスキーで再認証する。challengeは通常ログインと用途を分離する。各RPの最後の有効鍵は削除できない。

## 管理者の全パスキー紛失時

サーバーへのアクセス権を持つ運用者が、専用PHPをCLIで実行して復旧する。Webから解除するためのファイル設置方式やuser.dat削除を復旧手順にしない。復旧コマンドの実行は認証状態を変更するため、実装・文書化の依頼だけで実アカウントをリセットしない。

共通版の入口は `fbp/app/passkey/reset-admin-cli.php`（先行アプリ版は `classes/app/passkey/reset-admin-cli.php`）。配布されたアプリのルートで次のように実行する。IDは対象の有効な管理者のものを指定し、1を一般的な固定値として扱わない。

```sh
php fbp/app/passkey/reset-admin-cli.php <admin-user-id>
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

## 共通版の検証資産

専用検証アプリのtests/passkeyにsecurity.php、config.php、cli-recovery.php、notification.php、browser.cjs、password-policy-browser.cjsを置く。実行はruntime側を対象にし、署名・CLI・HTTP fixtureは新規の隔離作業領域を使う。テスト用資格情報と実データを本番へ移さない。通常ログイン用の既存Cookie・セッションを独自互換処理で新epochへ引き継がない。
