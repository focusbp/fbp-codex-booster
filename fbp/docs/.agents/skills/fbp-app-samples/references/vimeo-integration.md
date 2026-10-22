# Vimeo連携テンプレート

`assets/vimeo-integration/` は管理ログイン下のアップロード・動画紐付け・再生・確認付き削除の接続例。DB定義を固定せず、既存の動画レコードへ接続する。`fbp-media` と併用する。

```sh
php <skill-dir>/scripts/install_feature_template.php vimeo-integration <app-source-root>
```

## 有効化する箇所

初期状態は全操作拒否。コピーしたクラスの次のフックを実装する（テスト用サブクラスを本番へ配布しない）。

| フック | 実装する内容 |
|---|---|
| `canManage` | 現在の管理ユーザーがVimeoアカウントのトークンを知ってよい信頼済み運用者か |
| `loadRecord` | 現在ユーザーが操作できる実在レコードだけを返す。`id / revision / vimeo_id` を含める |
| `saveVideo` | FFMで版と空の紐付けを再確認して保存し、revisionを進める |
| `detachVideo` | FFMで版とVimeo IDを再確認して紐付けを解除し、revisionを進める |

入口は `upload`・`play`・`confirm_delete` へ `record_id` をPOSTする。クラス名変更時はPHPとtplの `data-class`、アップロードcallback名も揃える。保存フックの実装時には必要なDB定義・所有者・公開範囲・既存DSPを対象アプリで決める。

## アップローダーの境界

既存 `show_vimeo_uploader()` はアプリ設定 `vimeo_access_token` をAjax応答でブラウザーへ渡す。**一般会員向け・公開受付向けにはこのアップローダーを流用しない。** トークンを渡せる運用者に限って利用する。一般会員の投稿が必要なら、トークンをサーバーに保持するアップロード方式を別途設計する。

callbackはVimeo署名付き通知ではなくブラウザーPOST。base64のcallback引数だけで本人や所有権を判定しない。テンプレートは一回限りのセッションnonce・ログインユーザー・期限・ローカル版・空の紐付けを確認し、さらにAPIの `/me` と `/videos/{id}` の所有者を照合してから保存する。同じ操作種別では新しい確認を開くと古いnonceは置き換わる。アップロード後のタイトル等はリモート動画の状態が正本で、callback内容から自動PATCHしない。

## 更新・削除・再生

- サーバー側 `sample_vimeo_api::edit()` は所有者を確認し、name/descriptionだけをPATCHする。元サンプルで使った `create_vimeo()->edit()` はprivacy/embed設定も変えるため、既存動画のメタ情報更新へ無条件に転用しない。
- 削除はローカル保存済みVimeo IDだけを使い、確認nonceと現在権限を再検証する。HTTP 204の後にローカル紐付けを解除する。任意のPOST IDを `delete_vimeo()` へ渡したり、先にローカルDBを消したりしない。
- Vimeo呼出し中はFFMロックを保持しない。本番組込みでは外部処理中の予約状態を用意し、重複・並行操作を防ぐ。リモート成功後にローカル保存が失敗した場合やタイムアウト時は、両者を照合して復旧し、動画削除やアップロードを自動再送しない。404を無条件の削除成功にしない。
- 数値IDの `.vimeo` プレイヤーを用いる。限定公開のprivacy hashやドメイン制限をこのテンプレートが自動処理するとは扱わない。対象のプライバシー設定に合わせて再生経路を確認する。アプリ側の認証だけでVimeo側の共有を制限できるわけではない。
- トークンは設定から読む。コード・manifest・ログ・ブラウザーの保存状態ファイルへ固定しない。API例外はHTTP番号だけで、トークンやAPI生応答を画面へ出さない。

## 仕様・検証

2026-10-22に確認した公式資料: [認証とscope](https://developer.vimeo.com/api/authentication)、[アップロード](https://developer.vimeo.com/api/upload/videos)、[動画API](https://developer.vimeo.com/api/reference/videos)、[公開範囲](https://help.vimeo.com/hc/en-us/articles/12426199699985-About-video-privacy-settings)。必要scope・アップロード権限・アカウントの利用可能機能は導入時に再確認する。upload/edit/delete等は必要な操作だけに合わせる。

`scripts/test_feature_templates.php <framework-fbp-root>` は通信を差し替え、所有者不一致・HTTP失敗・紐付け改変・古い確認・別ユーザー・再送・初期拒否を確認する。実アカウントへはアクセスしない。実アップロード・トランスコード完了後の再生・確認後の削除は、対象アプリのフックと設定を用意し、許可された検証用動画だけで確認する。モック成功を実接続済みと報告しない。
