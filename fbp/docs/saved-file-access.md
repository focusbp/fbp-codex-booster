# アプリによる保存ファイルの配信制限

`Controller::res_saved_image()` と `res_saved_file()` は、配信前にアプリの
`saved_file_access_guard/saved_file_access_guard.php` があればその判断を適用する。
標準の画像・ダウンロード経路にはログインを省略する呼出し元があるため、
非公開ファイルの保護を画面やメニューの権限だけに依存させない。

```php
class saved_file_access_guard {
    public function authorize(Controller $ctl, string $filename, string $operation): ?bool {
        // $filename: upload領域に対する正規化済み相対パス
        // $operation: image または download
        // ファイル管理のノート識別子・レコード・所有者をサーバー側で照合する。
        return null; // このファイルは保護対象外
    }
}
```

- `null`: 従来の配信・キャッシュ設定を維持する。
- `true`: 閲覧許可。画像とファイルをno-storeで返し、画像のpublic／immutableキャッシュと304応答を無効にする。
- `false`: 403とno-storeで拒否する。ファイル内容は返さない。
- ガードの例外・不正な戻り値・メソッド不備は拒否扱い。秘密情報や例外本文を応答しない。
- ガードがあるアプリでは不正な保存パスを拒否し、別表記やシンボリックリンクを正規化してから照合する。upload領域外は拒否する。
- ガード未配置のアプリは従来どおり。配置だけでは特定ノートの保護は完了しないため、アプリ側の判定と匿名・権限なし・所有者の検証が必要。

動画のRange応答などをアプリで独自実装するときも、配信直前に同じアプリガードを適用する。
静的な公開ファイルや独自のreadfile処理を自動的に保護する機能ではない。
配布済みの公開キャッシュを遡って無効化しないため、既存公開メディアを非公開へ変更する場合は別途移行を判断する。
