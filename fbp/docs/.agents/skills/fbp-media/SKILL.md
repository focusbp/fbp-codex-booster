---
name: fbp-media
description: Implement and verify FBP file/image uploads and image/video/file delivery, covering both open public media and closed authenticated or owner-only media. Use fbp-csv for CSV import/export.
---

# fbp-media

## workflow
1. 画像・動画を「固定素材」か「実行時のアップロード・生成物」かで分類して保存先を決め、次に公開範囲を決める。公開範囲だけで保存先を決めない。
2. 動的なファイルは DB の `type=file` / `type=image` を使い、`screen_fields` に反映する。保存は `save_files()` / `save_posted_files()`（標準画面では自動保存）を使う。固定の公開素材は `fbp-fixed-images` を参照する。
3. 配信区分に合うクラス・関数と画面の取得URLを実装し、HTTP経由で認証・表示・ダウンロードを確認する。
4. 動画の保存・ブラウザー再生には [references/video.md](references/video.md) を使う。動画は `type=file` で扱い、再生用の配信とファイルダウンロードを区別する。

## 保存先と認証の基本

| 種類 | 保存先・配置時期 | 配信・認証 |
| --- | --- | --- |
| 固定の公開素材 | `classes/app/images/` に開発時に配置し、リリースで反映 | `images/...` で公開。個別のログイン・所有者判定を必要とする画像には使わない |
| 実行時の公開画像・動画 | `classes/data/upload/` に保存 | 公開用の配信関数で公開対象を確認して返す |
| 実行時の非公開画像・動画 | `classes/data/upload/` に保存 | アプリ専用の配信関数をログイン認証下に置き、必要な閲覧権限を確認して返す |

- 標準保存APIの保存先は `classes/data/upload/`。DBの画像・ファイルは通常 `upload_file_<ID>`、サムネイルは `upload_file_<ID>_th` として保存される。公開/非公開で `upload/images` などへ自動的に振り分ける仕組みではない。
- 固定の公開画像は `classes/app/images/` 以下に開発時に配置し、リリースで反映する。フレームワークの標準保存APIを通して実行時にここへ配置することはできないため、アップロード先・画像生成結果の保存先には使わない。
- 実行時に生成・アップロードする画像は、公開するものも `classes/data/upload/` に保存して配信関数から返す。「公開画像」という理由で固定素材用の `classes/app/images/` へ保存しない。
- 標準のアップロードファイルは保存パスを直接公開せず、関数経由で取得する。その配信関数を通常のログイン認証下に置き、必要な所有者・閲覧権限を確認することで、アプリ側でクローズな配信を実装できる。保存先が `upload/` であること自体がログイン必須を意味するわけではない。
- クローズ化の基本はアプリ専用配信関数での認証・認可とする。同じファイルを返す別の公開経路が残っていないことも確認し、共通framework変更が必須とは決めつけない。

## 保存から表示まで
- DBの画像・ファイル項目はファイルIDを保持し、`get_file_info($file_id)` で保存情報を取得する。既定ではpathが暗号化されるため、サーバー側で保存名を必要とする場合は `get_file_info($file_id, false)` を使う。
- `save_file($filename, $data)` は `upload/` への実体保存であり、DBのファイル登録や所有者の紐付けとは別。生成物もレコード・所有者・保存名の対応をアプリ側で管理する。
- ブラウザーへ渡すのは配信関数のURLと対象レコードの識別子。配信側で対象レコードを読み、公開状態またはログイン・閲覧権限を確認し、そのレコードから保存名を解決して `is_saved_file()` と応答APIへ渡す。
- 配信関数を通ることは、そこで認証を実装できるという意味。`res_saved_image()` / `res_saved_file()` の呼出しだけをログイン・所有者チェックの代わりにしない。
- 新規の画像・動画共通配信は、権限確認後に `$ctl->res_saved_media($stored, ["cache" => false])` を使える。画像・動画・音声を実体MIMEで判定してinline応答し、動画のRange・HEADにも対応する。認証は呼出し元で行う。既存の画像専用APIやダウンロードAPIも引き続き使える。

## オープンな配信
- 標準の画像配信はログイン不要で取得できる構成がある。`db_exe` の `view_image` / `download_file` は `set_check_login(false)` を設定するため、管理画面上の画像でも取得URL自体が認証付きとは限らない。
- 「オープン」は取得経路の性質を指す。保存ディレクトリが直接Web公開されているかどうかとは分けて確認する。
- 公開画像/ファイル表示は `fields_view_direct` を優先する。暗号化pathは取得対象の指定であり、本人認証にはならない。
- 独自の公開配信クラスで認証を外す場合は、公開してよい対象だけを返す。公開コード、有効期限、公開状態など要件に応じた検証を行う。

### 関数ごとのログイン設定
フレームワークはログイン必須を既定にし、呼出し関数名を設定 → クラスのコンストラクタを実行 → ログイン要否を判定 → 対象関数を実行する。このため、同じクラス内でもコンストラクタで公開関数だけを明示して認証を外せる。

```php
function __construct(Controller $ctl) {
	$function = (string) $ctl->get_called_function();
	$public_functions = ["view_public_image", "view_public_video"];
	$ctl->set_check_login(!in_array($function, $public_functions, true));
}
```

- 上例では `view_public_image` と `view_public_video` だけがログイン不要で、他の関数はログイン必須。実際に公開する関数だけをリストに含める。公開関数内でも対象レコードの公開状態を確認してから返す。
- URLは `$ctl->get_APP_URL("<配信クラス>", "view_public_image", ["id" => $row["id"]])` で生成し、`<img>` の `src` から呼び出す。固定素材の `images/...` はこの関数配信とは別経路。
- `set_check_login()` はコンストラクタで設定する。配信関数の中ではルーターの認証判定が終わっているため、そこでの切替では遅い。コンストラクタでは画像本体を返さない。
- 公開関数名は明示的に列挙する。クラス全体を無条件に `false` にして管理・非公開関数まで公開しない。

## クローズな配信
- まずアプリ専用クラスに `view_image(Controller $ctl)`、`download_file(Controller $ctl)`、必要なら動画配信関数を設ける方法を検討する。通常の管理側クラスはログイン認証が既定で有効なので、非公開関数への呼出しでは `set_check_login(false)` を設定しない。公開関数と同居させる場合は上記の関数別設定を使う。クラス全体で認証を外す公開専用クラスを使う場合は、認証付き配信を別クラスに分ける。
- ログイン認証と本人限定は別条件。受け取ったレコードIDからサーバー側で対象を取得し、現在のログインユーザーの所有権・閲覧権限を確認してから、そのレコードに紐づく保存ファイルを解決する。クライアントが渡すpathやfile IDだけで許可しない。
- 権限と `is_saved_file()` の確認後、画像は `$ctl->res_saved_image($stored, false)` で公開キャッシュを無効にして返す。ダウンロードは `res_saved_file()` を使い、非公開データに適したキャッシュヘッダを確認する。
- URLは `$ctl->get_APP_URL()` で専用クラス・関数へ向ける。標準helperが `db_exe` を出力する場合は、該当メディア表示を専用URLに置き換える。
- 表示URLの変更だけで保護完了とはしない。同じ実体を標準のログイン不要ルートや静的URLから取得できないか確認する。必要なら保存方法・配信経路をアプリ側で見直す。
- 動画のinline表示・Range応答が必要な場合は、その応答にも同じ認証・所有権判定を適用する。`res_saved_file()` のダウンロードだけで再生要件を満たすとは判断しない。
- 非公開メディアという理由だけで共通framework変更を前提にしない。アプリ側で満たせない具体的な取得経路が残る場合に、その根拠と必要な共通変更を示す。

## 配信の検証
- オープン: ログインなしで公開対象を表示でき、非公開・公開終了の対象は返さない。
- クローズ: 許可された本人には返り、未ログイン・権限のない別ユーザーには実データが返らない。正しいURL/識別子を知っていても権限判定されることを確認する。
- クローズでは元画像・サムネイル・ダウンロード・動画で実際に使う取得経路と、同じ実体へ到達する標準経路を確認する。キャッシュヘッダ、動画のRangeは採用した実装に応じて確認する。
- CLIだけではルーターのログインチェックを検証できない。実HTTPの応答本文・ヘッダも確認する。

## download links
- PDFの生成・取得は `fbp-pdf` を正本とし、公開PDFはサンプルのGETリンク、オブジェクトで生成するPDF本体の応答は `$pdf->download_pdf($filename)` を使う。以下の一般ファイル向けルールより優先する。
- PDF以外の管理側と、LINE Bot関係ではない通常の公開側ダウンロードは `download-link` を基本にする。
- LINE Botで送るURL、LINEメッセージから開く公開画面、LINE内ブラウザでの利用が主目的の公開側ダウンロードは、通常の `<a href>` でGETのダウンロードURLを開く。LINE内ブラウザではXHR/blob経由より、実URLのレスポンスヘッダを直接見せる方が安定する。
- 実ファイル応答は特別な理由がない限り独自header実装を作らず、`$ctl->res_saved_file($stored, $download_name)` を使う。`res_saved_file()` はMIME判定、`Content-Disposition`、日本語ファイル名、`X-Content-Type-Options: nosniff` を担う。
- 独自実装が許されるのは、Range対応、inline表示、外部ストレージのストリーミング、特殊な認証/監査ログなど、`res_saved_file()` では満たせない要件が明確な場合だけ。

### download-link sample
以下は管理側の構成例。`load_readable_document()` は標準APIではなく、対象レコードの存在・削除状態・現在のログインユーザーの閲覧権限を確認し、拒否時に `null` を返すアプリ実装のメソッド。これを実装したうえで使用する。配信クラスでは `set_check_login(false)` を設定しない。
```smarty
{assign file $ctl->get_file_info($row.file_id)}
<button type="button"
        class="download-link"
        data-class="document"
        data-function="download_file"
        data-filename="{$file.filename|escape}"
        data-id="{$row.id|escape}">
	ダウンロード
</button>
```

```php
function download_file(Controller $ctl) {
	$row = $this->load_readable_document($ctl, (int) $ctl->POST("id"));
	if (!$row) {
		http_response_code(404);
		return;
	}
	$file = $ctl->get_file_info($row["file_id"], false);
	$path = (string) ($file["path"] ?? "");
	if ($path === "" || !$ctl->is_saved_file($path)) {
		http_response_code(404);
		return;
	}
	$ctl->res_saved_file($path, $file["filename"]);
}
```

### LINE/public href sample
`load_public_saved_path()` / `load_public_download_name()` はアプリ側で実装する。公開コードの検証と公開状態の判定を含める。ログイン不要にする場合は `fbp-public-pages` に従い公開専用クラスとして構成する。
```php
function page(Controller $ctl) {
	$code = (string) ($ctl->GET("code") ?? "");
	$ctl->assign("download_url", $ctl->get_APP_URL("public_document", "file_download", [
	    "code" => $code,
	    "download" => "1",
	]));
	$ctl->assign("download_filename", "document.txt");
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

## constraints
- PDFは `fbp-pdf`、一時作業ファイルは `fbp-temp-files` を使う。
- 動的・本人限定の画像を `classes/app/images` などの固定公開素材用ディレクトリへ置かない。
- CLIアップロード検証では `is_uploaded_file()` が `false` になるため、CLI時のみ `is_file()` を許可する。
- 入力部品に推測ベースの固定幅を付けない。エラー時に `show_multi_dialog()` 再実行や `reload_area()` をしない。
