---
name: fbp-receive-mail
description: Implement POP/IMAP incoming mail processing with Controller receive_mail, explicit confirmation, and separate server-side deletion. Use for receiving messages, MIME bodies or attachments, and retry-safe incoming workflows in FBP apps.
---

# fbp-receive-mail

## 共通設定とAPI

引数省略時の受信は「システム設定 → メール」の単一の受信アカウントを使用する。送信SMTPとは独立しており、POP／IMAP・サーバー・ポート・暗号化・認証情報・IMAPフォルダーを設定する。空欄フォルダーはINBOX。SSL/TLSとSTARTTLSは証明書を検証する。

アプリは接続処理やUID管理を再実装せず、以下を使用する。

```php
$messages = $ctl->receive_mail(20);              // 件数は1〜100、既定20
$ctl->confirm_received_mail($message['id']);    // 処理が成功した後
$ctl->delete_received_mail($message['id']);     // サーバー削除が必要な場合だけ
```

- `receive_mail()` は未確定メールの配列を返す。初回はサーバーにあるメールを古い順（IMAPはUID順、POPはUIDL一覧順）に取得する。未読・既読とは別の管理であり、既読メールも対象。取得だけでは受信状態・既読・削除フラグを更新しない。
- `confirm_received_mail()` は指定した1通だけを処理済みにする。順不同・同じIDでの再確定に対応し、未確定の小さいUIDを飛ばさない。サーバーへ再接続して識別情報を検証する。既に消えた未確定メールは確定できない。
- `delete_received_mail()` は指定した1通をサーバーから削除し、処理済み状態は変更しない。削除済みの同じIDへの再試行は成功扱い。POPはUIDLで現在の番号を解決し、DELE後のQUITで確定。IMAPはUIDVALIDITYを照合し、UID STOREとUID EXPUNGEを使用する。UIDPLUSまたはIMAP4rev2がないサーバーでは、削除フラグを付けずに例外にする。他の削除済みメールを巻き込む通常のEXPUNGEへ迂回しない。
- 確定・削除は成功時`true`、設定・接続・認証・形式・状態・削除の問題は例外。取得は失敗時に部分結果を返さず例外になる。生のサーバー応答や認証情報を例外へ含めない。

## アプリ独自アカウント

3関数の最後に同じ指定を渡せる。省略時のシステム設定利用は従来どおり。

```php
$account = ['table' => 'mail_accounts', 'id' => 1, 'password_encrypted' => true];
foreach ($ctl->receive_mail(20, $account) as $mail) {
    persist_mail_once($ctl, $mail['id'], $mail);
    $ctl->confirm_received_mail($mail['id'], $account);
    // $ctl->delete_received_mail($mail['id'], $account);
}
```

`table`はcommonのアカウントノート、`id`は正の整数。レコードにシステム設定と同名の`receive_protocol/server/port/secure/user/password/folder/state`項目を設ける。`receive_state`の保存長は65535バイトとする。`password_encrypted:true`の場合は`$ctl->encrypt()`の値を保存し、共通関数が接続時だけ復号する。既定falseでは平文パスワードとして扱う。確定では受信状態だけを更新し、パスワードは書き戻さない。アカウント参照は認可済みの値をアプリ側で組み立て、利用者の任意POSTをそのまま渡さない。

設定DBと対象アカウントDBを開いてから最新行を読み、署名鍵はアプリのシステム設定から使用する。同じ接続先でも別ノート／別レコードのIDは互換にしない。独自アカウントの確定・削除はシステム設定の受信状態を変更しない。

完成した最小サンプルは [Incoming Mail](../fbp-app-samples/references/incoming-mail.md) を参照する。

## 取得結果

各要素の主な項目:

| キー | 内容 |
| --- | --- |
| `id` | 確定・削除に渡す署名付きの不透明な文字列。アプリ側の重複防止キーとして保存できる |
| `subject` | UTF-8の件名 |
| `from`, `to`, `cc` | `['email' => ..., 'name' => ...]` の配列。表示名はUTF-8 |
| `date`, `message_id` | 元ヘッダーの文字列。Message-IDは一意・正当とは限らない |
| `text`, `html` | 転送エンコーディングとcharsetを復号したUTF-8本文 |
| `attachments` | `name`, `mime_type`, `content_id`, `inline`, `size`, `content` の配列。contentはバイナリ文字列 |
| `raw` | 元のRFC822メッセージ |
| `protocol`, `uid`, `uidl`, `uidvalidity` | 調査用の識別情報。確定・削除には必ず`id`を使う |

添付はファイルへ自動保存しない。保存が必要なら既存のアプリのアップロード／媒体保存方式に合わせ、ファイル名を保存先パスとして信用しない。HTML本文は外部入力なので、そのまま管理画面へ埋め込まず、目的に応じてサニタイズまたはテキスト表示する。本文・添付・rawをデバッグログやCLI結果へ丸ごと出さない。

PHPのIMAP拡張（アドレス解析）、mbstring、iconv、暗号化接続ではOpenSSLが必要。接続・認証は共通のソケット実装を使用する。1通10MiB、1回の取得合計50MiBまで。上限超過・不正MIMEは例外で、受信状態は進まない。大きなメールの無視を暗黙に実装しない。

## アプリの処理順序

```php
foreach ($ctl->receive_mail(20) as $message) {
    // 業務DBで message['id'] による重複チェック・保存を行う。
    // この例の persist_mail_once はアプリ側で実装する処理。
    persist_mail_once($ctl, $message['id'], $message);
    $ctl->confirm_received_mail($message['id']);
    // 保管方針で削除が必要と決まっている場合だけ呼ぶ。
    // $ctl->delete_received_mail($message['id']);
}
```

取得→業務処理→確定→必要なら削除、の順で呼ぶ。業務処理に失敗したメールは確定しない。エラーを捕捉して続行する場合は、失敗したIDを再処理できるようにする。

取得はat-least-onceであり、同時取得や「業務保存後・確定前」の異常終了では同じメールが再び返る。業務DBで`id`を使った冪等性を確保する。DBを事前オープンし、FFMロック再取得後に重複確認する手順は [fbp-db](../fbp-db/SKILL.md) を使う。外部送信等の取り消せない副作用も、アプリ側で重複実行を防ぐ。

削除失敗時の再試行には同じ`id`を保管する。確定後は`receive_mail()`に出なくなるので、削除対象を受信一覧から再取得する設計にしない。設定変更・UIDVALIDITY変更後の古いIDは拒否されるため、新しい設定で取得し直して業務側で照合する。再試行を目的に署名を再生成したり受信状態を書き換えたりしない。

## 内部状態と検証

- `setting.receive_state`は共通関数だけが更新するJSON。POPは現存メールの確定UIDL、IMAPはUIDVALIDITYと確定UID範囲を保持する。画面POSTでは更新しない。アカウントやフォルダー・UIDVALIDITYが変わると以前の状態を流用せず、再取得対象になる。
- 状態は65535バイトまで。満杯なら確定を例外にし、切捨てや先への飛越しはしない。大量運用で不足する場合は共通保存方式の拡張を検討する。
- 受信関数は取得データをセッションへ保持しない。FFMで設定の同時確定を直列化し、セッションの古い設定値で状態を上書きしない。
- 定期取得を依頼された場合に [fbp-cron](../fbp-cron/SKILL.md) を使う。関数追加だけではcronを作成しない。
- 検証は隔離した受信アカウント／テストサーバーで行う。取得だけで状態が進まないこと、処理失敗後の再取得、確定後の除外、順不同の確定、削除対象の一致を代表確認する。実メールを削除する検証は、その対象と削除が依頼範囲に含まれる場合に限る。
