# テンプレートメールの添付名指定

既存の`email_format`を使い、保存済みファイルにメール上の添付名を指定する例。
`$to`は検証用宛先、`format_key`は登録済みテンプレートのキー、`$saved_path`は保存済みファイルのパスへ置き換える。
本文・件名の置換キーは送信前に設定・確認する。

```php
$ctl->send_mail_prepared_format($to, "format_key", [
    ["path" => $saved_path, "name" => "契約書.pdf"],
]);
```

`path`は`classes/data/upload/`配下の保存パス。絶対パス・URLは渡さない。
検証環境では宛先を固定し、件名・本文・宛先・添付名を確認する。
このコードの取得だけではメールを送信しない。
