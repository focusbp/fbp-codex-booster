---
name: fbp-csv-media
description: Implement and verify CSV upload/download and file/image field flows in FBP.
---

# fbp-csv-media

## trigger conditions
- CSVアップロード/ダウンロード機能を追加・修正する
- `type=file` / `type=image` の保存・表示導線を作る
- CLIで `app_call` の `files` を使ってアップロード検証したい

## workflow
1. DB側に `file` / `image` フィールドを追加し、`screen_fields` を `list/add/edit/delete` へ反映する。
2. CSVダウンロードは `res_csv()` でヘッダ行 + データ行を返す。
3. CSVアップロードは `fields_form_original type="file"` + `upload_exe` で実装し、`res_error_message()` で入力エラーを返して即 `return` する。
4. file/imageは保存時に `save_files()` or `save_posted_files()` を使う（DB標準画面なら自動保存）。
5. `app_call` でCSV出力/アップロードを検証し、`data_list` で反映確認する。

## download links
- 管理側と、LINE Bot関係ではない通常の公開側ダウンロードは `download-link` を基本にする。
- LINE Botで送るURL、LINEメッセージから開く公開画面、LINE内ブラウザでの利用が主目的の公開側ダウンロードは、通常の `<a href>` でGETのダウンロードURLを開く。LINE内ブラウザではXHR/blob経由より、実URLのレスポンスヘッダを直接見せる方が安定する。
- 実ファイル応答は特別な理由がない限り独自header実装を作らず、`$ctl->res_saved_file($stored, $download_name)` を使う。`res_saved_file()` はMIME判定、`Content-Disposition`、日本語ファイル名、`X-Content-Type-Options: nosniff` を担う。
- 独自実装が許されるのは、Range対応、inline表示、外部ストレージのストリーミング、特殊な認証/監査ログなど、`res_saved_file()` では満たせない要件が明確な場合だけ。

### download-link sample
```smarty
{assign file $ctl->get_file_info($row.file_id)}
<button type="button"
        class="download-link"
        data-class="document"
        data-function="download_file"
        data-filename="{$file.filename|escape}"
        data-path="{$file.path|escape}">
	ダウンロード
</button>
```

```php
function download_file(Controller $ctl) {
	$path = $ctl->decrypt((string) ($ctl->POST("path") ?? ""));
	$download_name = (string) ($ctl->POST("filename") ?? "");
	if ($path === "" || !$ctl->is_saved_file($path)) {
		$ctl->res_saved_file("__missing_file__", $download_name);
		return;
	}
	$ctl->res_saved_file($path, $download_name);
}
```

### LINE/public href sample
```php
function page(Controller $ctl) {
	$code = (string) ($ctl->GET("code") ?? "");
	$ctl->assign("download_url", $ctl->get_APP_URL("public_document", "file_download", [
	    "code" => $code,
	    "download" => "1",
	]));
	$ctl->assign("download_filename", "document.pdf");
	$ctl->show_public_pages("page.tpl");
}

function file_download(Controller $ctl) {
	$code = (string) ($ctl->GET("code") ?? "");
	// codeを検証し、公開状態・権限・保存ファイルの存在を確認してから返す。
	$stored = $this->load_public_saved_path($ctl, $code);
	$download_name = $this->load_public_download_name($ctl, $code);
	if ($stored === "" || !$ctl->is_saved_file($stored)) {
		http_response_code(404);
		header("Content-Type: text/plain; charset=UTF-8");
		echo "ダウンロードできるファイルが見つかりません。";
		return;
	}
	$ctl->res_saved_file($stored, $download_name);
}
```

```smarty
<a class="button_link"
   href="{$download_url|escape}"
   download="{$download_filename|escape}">ダウンロード</a>
```

## cli checks
- CSV出力:
  - `php cli.php app_call --json='{"class":"<class>","function":"csv_download","post":{"encode":"UTF-8"},"output_file":"/tmp/out.csv"}'`
- CSVアップロード:
  - `php cli.php app_call --json='{"class":"<class>","function":"upload_exe","files":{"file":{"path":"/tmp/in.csv","name":"in.csv","type":"text/csv"}}}'`
- ダウンロードヘッダ確認:
  - `curl -sS -L -D /tmp/headers.txt -o /tmp/out.bin '<download-url>'`
  - `Content-Type`、`Content-Disposition`、`X-Content-Type-Options: nosniff`、サイズを確認する。

## constraints
- エラー時に `show_multi_dialog()` 再実行や `reload_area()` をしない。
- 公開画面で画像/ファイル表示は `fields_view_direct` を優先する。
- 日付/日時/年月のCSV文字列化が PHP 直書きになる場合は `$ctl->create_ValueFormatter()` を使う。`date()` の直書きは機械連携用の固定フォーマットCSVに限定する。
- `ValueFormatter` は CSV/PDF/Mail など PHP 直書き出力用。HTML 表示には使わず、`fields_*` / `html_*` helper を優先する。
- `db()->insert()` / `update()` に配列リテラルを直接渡さず、変数化してから渡す（参照渡し対策）。
- CLI `app_call` の `files.path` 検証では `is_uploaded_file()` が `false` になるため、CLI時のみ `is_file()` を許可して検証する。
- 入力部品や `select` に固定 `width` は原則付けない。ユーザーが明示指定した場合のみ追加する。`style="width:220px;"` のような推測ベースの幅指定は入れない。
