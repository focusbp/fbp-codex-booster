---
name: fbp-send_email
description: Configure email_format templates and implement prepared-format email sending flows.
---

# fbp-send_email

## trigger conditions
- テンプレートメール送信を実装する
- `email_format` の追加・編集や置換検証が必要
- `send_mail_prepared_format` を扱う

## workflow
1. `email_format` を作成または更新。
2. 置換キーを検証。
3. 送信処理から `send_mail_prepared_format` を呼び出す。
4. テスト送信で件名・本文・宛先を確認。

## attachments
- 添付ファイルは従来どおり保存パス文字列で指定できる。この場合、メール上の添付名は保存ファイル名（例: `upload_file_17`）になる。
- メール上の添付ファイル名を指定したい場合は、添付指定を `["path" => "<saved_path>", "name" => "<download_name>"]` にする。複数添付ではこの配列を並べる。
- 例:
```php
$ctl->send_mail_prepared_format($to, "format_key", [
    ["path" => $saved_path, "name" => "契約書.pdf"],
]);
```
- `path` は `classes/data/upload/` 配下の保存パスを指定する。絶対パスやURLは渡さない。

## constraints
- 実運用アドレスへの誤送信を防ぐため、検証環境では宛先を固定化する。
- 本文や件名で日付/日時/年月を PHP 直書きする場合は `$ctl->create_ValueFormatter()` を使う。HTML helper の代替としては使わない。
