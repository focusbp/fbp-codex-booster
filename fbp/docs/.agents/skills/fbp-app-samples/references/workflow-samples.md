# 定番業務機能のサンプル

完成した小さな業務フローをコピーして、案件の項目名・権限・業務ルールに合わせて変更するための資産。データ投入機能を通常画面へ追加するものではない。

| 必要な機能 | サンプル | 参照 |
| --- | --- | --- |
| 顧客と対応履歴、親一覧から子サイドパネル | `parent-child-history` | [フロー](parent-child-history.md)・[DB](parent-child-history-db.md) |
| 未対応→対応中→完了、理由付き確認と履歴 | `status-transition` | [フロー](status-transition.md)・[DB](status-transition-db.md) |
| 未ログインの問い合わせ→内容確認→管理側受付 | `public-intake` | [フロー](public-intake.md)・[DB](public-intake-db.md) |

## 導入

3種類で `scripts/install_workflow_sample.php` を共有する。各 `assets/<sample>/<sample>.json` がファイル・DB・画面・選択肢の正本。外部サービス・秘密情報は不要。管理画面は管理者専用の Standard Screen。

```sh
# SAMPLE は上表から一つ選ぶ。SOURCE_ROOT / TEST_ROOT は対象環境で解決する。
php "$SKILL_ROOT/scripts/install_workflow_sample.php" "$SAMPLE" --code-root="$SOURCE_ROOT"
# 環境の正規手順でソースからテスト実行環境へ同期する。
php "$SKILL_ROOT/scripts/install_workflow_sample.php" "$SAMPLE" --configure-test-root="$TEST_ROOT"
```

- コード配置とテスト環境の定義登録は別操作。定義登録は指定した実行環境を作業ディレクトリにして `fbp/cli.php` を呼ぶ。ソース側でCLIを直接実行しない。
- 同名クラス・ノート・選択肢があれば変更前に停止する。既存実装への適応は差分を確認して行う。上書き、データ初期化、実行時の自動DB作成、サンプルデータ投入は行わない。
- `--configure-test-root` は対象がテスト環境かを自動判別できないため、環境情報から解決したテスト実行先を渡す。本番への導入・リリースはこのインストーラーの対象外。
- 定義登録途中の失敗は自動ロールバックしない。成功済みの定義をCLIで確認し、失敗箇所だけを復旧する。同じコマンドを盲目的に再実行しない。
- 開発中の架空データはテストサーバーだけで用い、検証終了後に今回の検証で作ったレコードだけを削除する。ヒヤリングで本番サンプルを希望した場合の「タスク4：本番環境へのテストデータ投入」は、開発・導入と分けて扱う。

## 変更して使う箇所

テーブル名を変える場合は、manifest・クラス名・DB呼出し・visibility filter・button classを一緒に変更する。所有者単位の公開範囲、担当者権限、承認ルート、外部通知などは案件ごとに設計する。既定の「管理者専用」を一般ユーザーへ広げるだけで権限制御を完成させたことにしない。

## 検証

```sh
# 存在する空の作業ディレクトリを指定する。FBPやネットワークは使わない。
php "$SKILL_ROOT/scripts/test_workflow_samples.php" "$EMPTY_SCRATCH_DIR"
```

単体検証は状態・履歴の一致、権限、理由未入力、古い確認・再送・改ざん、公開フォームのセッション境界、導入の上書き拒否を確認する。

実環境ではPHP構文・manifest読み込み、定義登録直後のデータ0件、既存定義の保持を確認する。`standard_screen_check` の親ノートの未使用 `list_on_side`、受付ノートの未使用 `add` / `delete` / `list_on_side` は意図した警告。ERRORは解消する。

実ブラウザでは [各サンプル](#定番業務機能のサンプル) の操作を確認する。認証済みブラウザを再利用し、公開側のみ別の未ログインセッションにする。スクリーンショットだけで保存成功を判定せず、保存結果も読む。検証結果を案件へ再利用する場合は、対象コード・定義が一致していることと、変更の影響範囲を確認する。

### 実ブラウザ検証スクリプト

3種類を導入済みのテスト環境で `scripts/verify_workflow_samples.cjs` を使う。Playwrightが使える環境で実行し、必要なら環境固有の起動wrapperを使う。

- `APP_TEST_URL`: 管理画面入口、`APP_LOGIN_ID` / `APP_PASSWORD`: 管理者ログイン（環境変数で渡し、ファイルに保存しない）。
- `SAMPLE_PUBLIC_URL`: `get_APP_URL('sample_public_intake','page')` で生成した公開URL。
- `SAMPLE_TEST_ROOT`: `fbp/cli.php` があるテスト実行環境。CLIの作業ディレクトリにも使用する。
- `SAMPLE_TEST_OUTPUT_DIR`: スクリーンショット・結果JSONの出力先。`SAMPLE_BROWSER_PATH` は任意。
- gateway認証がある環境は `PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME` / `PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD` を起動環境から渡す。

スクリプトは実際に架空の顧客・履歴・案件・受付を作り、今回の識別子と親IDに一致するレコードだけをfinallyで削除する。既存のサンプルレコードは変更しない。途中でプロセス自体が停止した場合は識別子を確認して手動回収する。公開フォーム以外は1つの管理ログインを再利用する。

検証するのは、子履歴追加・編集、案件登録と2段階の状態変更、理由エラー、公開入力・確認・保存・同一要求の再送、管理側の対応状態編集、PC/390px表示。構文・単体テストと異なり、このスクリプトにはテスト環境の書込みが伴う。
