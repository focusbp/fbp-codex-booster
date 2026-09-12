---
name: fbp-dialog
description: Implement FBP dialog-based UI flows with ajax-link/invoke-function, validation, error rendering rules, and reusable URL sharing/copy templates.
---

# fbp-dialog

## trigger conditions
- `show_multi_dialog()` を使う画面を作る
- Ajax ダイアログ遷移や保存処理を実装する
- エラー表示が絡むフォームを作る

## workflow
1. ダイアログ表示は `show_multi_dialog()` で開始。
2. ボタンは `ajax-link` + `invoke-function` で接続。
3. バリデーション時は `res_error_message()` を設定し `return`。
4. 必要なら `app_call` でHTMLに `error_*` 要素があるか確認。

## validation samples
- `world_datetime` は見た目は日時入力でも、POST値は基本的に timestamp として扱う。`date("Y/m/d H:i:s")` のような表示文字列を初期値・検証前提にしない。
- `world_datetime` の初期値は原則 `time()` または既存 timestamp をそのまま入れる。
- 日付バリデーションは「数値 timestamp を最優先、文字列は補助的に変換」の順で組む。
- 例:
```php
private function normalize_datetime_input($value): string
{
    if ($value === null || $value === "") {
        return (string) time();
    }
    $value = trim((string) $value);
    if (ctype_digit($value)) {
        return strlen($value) >= 13
                ? (string) floor(((int) $value) / 1000)
                : $value;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : (string) $timestamp;
}

private function normalize_datetime_to_timestamp($value): int
{
    $value = trim((string) $value);
    if ($value === "") {
        return 0;
    }
    if (ctype_digit($value)) {
        $number = (int) $value;
        return strlen($value) >= 13 ? (int) floor($number / 1000) : $number;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? 0 : (int) $timestamp;
}
```
- `res_error_message("moved_at", "日時を正しく入力してください")` のような検証は、表示文字列ではなく hidden で返る timestamp 値を正本として判定する。

## dialog layout policy
- `show_multi_dialog($dialog_name, $template, $title, $width, $fixed_bar_template, $options)` の引数役割を明確に使い分ける。
- ダイアログ上部タイトルは第3引数 `title` で指定する（テンプレート内に重複タイトルを増やさない）。
- ダイアログ幅は第4引数 `width` で指定する。
- 上部固定操作は第5引数 `fixed_bar_template` に分離して指定する（例: `"_fixed_bar.tpl"`）。
- ダイアログメニュー（タブ）は `add_tab()` を使って `.multi_dialog_tab_area` に追加する。
- 配置指針: 全体操作=固定バー、画面名=title、内容切替=タブ、フォーム本文=contents。
- ダイアログ本文のフォームでは、入力部品の横に実行ボタンを置かない。フォーム項目の下に `.button_row` などの操作行を置き、ボタンは右寄せにする。検索ツールバーの検索ボタンだけは例外として横配置を許可する。
- 公開側 `css_mode=minimal` の手書きダイアログフォームでは、フォームに `.public-form`、入力項目 wrapper に `.public-field`、操作行に `.public-form-actions` または `.public-actions` を使う。アプリ固有クラスは併用し、独自の `*-form-row` だけで input を包まない。

## constraints
- エラー時の再描画（`show_multi_dialog` 再実行、`reload_area`）を禁止。
- ダイアログ本文内の `閉じる` ボタンは、ユーザーから明示指定がある場合を除いて置かない。標準のダイアログクローズUIを使う。
- 保存成功後や callback 後に別画面へ進めるとき、`$this->other_function($ctl)` のように別関数を直接呼んで画面遷移しない。ダイアログ状態や class 解決、共通レイアウトが崩れやすい。
- ダイアログ起点の成功後遷移は `close_multi_dialog()` の後に `invoke()` / `reload_area()` / `reload_work_area()` / `res_redirect()` を使って行う。
- `fields_form_direct` 使用時は項目ごとに `error_項目名` を用意する。
- `fields_form_original` / 手書きinput / checkbox / textarea を含む、POST対象の全入力項目にも `error_項目名` を必ず配置する。
- 実装完了前に「`res_error_message(field, ...)` の `field` 名」と「テンプレート上の `error_field` クラス」が1対1で存在することを確認する（不足がある状態で完了扱いにしない）。
- 公開側 minimal の手書きフォームでは、主要な input / select / textarea が `.public-field` など公開側フォームCSSの対象セレクタに入っていることも確認する。保存動作と `error_*` だけで完了扱いにしない。
- `res_error_message()` を使う場合、表示先タグ（`error_項目名`）が存在することを必ず事前確認する。表示タグを設置できない導線（フォーム未描画前・一覧ボタン直叩き等）では `show_notification_text()` を使う。
- Smarty tpl 内の `<style>` では、CSS の `{}` が Smarty 構文として解釈されないように、必ず `{literal}` ... `{/literal}` で CSS 本文を囲む。
- 確認表示・詳細表示の値描画は `fields_view_direct` を優先し、手書き展開は必要最小限にする。
- DBに登録された file/image を `<img>` で直接表示する場合、テンプレートから保存パスを直参照せず、表示元クラスに `view_image(Controller $ctl)` などの画像表示関数を必ず実装してそこを通す。
- 上記の画像表示関数では、受け取った識別子や暗号化pathを検証し、`is_saved_file()` 確認後に `res_saved_image()` を返す。ダウンロード用は別に `download_file()` を用意して `res_saved_file()` を返す。
- DB画像の `<img>` には原則 `max-width:500px;` を付け、縦サイズは固定しない。`height` / `max-height` で縦横比を崩さない。
- 固定バー上の非ajaxボタン（例: `type="button"`）をJSで扱う場合、`.multi_dialog` スコープで要素取得してイベントを張る。
- `ajax-link` でフォーム値をPOSTする画面は、テンプレート全体を `<form onsubmit="return false;">...</form>` で囲み、対象入力の `error_*` 要素を必ず配置する。


## URL表示・コピーのダイアログ

URLを表示して共有・コピーする管理画面では、[assets/share_url.tpl](assets/share_url.tpl) をコピーして使える。URLそのものをクリック可能にし、右端にコピーアイコンを置くパターン。

- 長いURLは折り返して全文表示する。横スクロール・省略表示にせず、別の「URLを開く」リンクは重複して置かない。
- コピーはアイコンのみで、`title` / `aria-label` を付ける。成功時はチェックアイコンと `role="status"` のメッセージを表示し、失敗時はURLを選択して手動コピーを案内する。
- ボタン背景・成功メッセージは `--fbp-framework-primary-color`、ボタン文字は `--fbp-framework-primary-text-color` を使用し、管理画面の色設定に連動させる。独自の濃紺・緑などで固定しない。
- 表示専用のURLとコピーの組み合わせなので、フォーム入力部品の横に実行ボタンを置かないルールの対象外。POSTする入力項目や `<form>` は不要。
- 専用CSS・JSはテンプレート内の `{literal}` に同梱。ダイアログの再表示時に本文と一緒に反映し、JSのイベントは名前空間付き `off` / `on` で重複を避ける。
- テンプレートは固定バー・タブを持たない専用ダイアログ用。余白・空の固定バーの調整はこのパターンを含むダイアログに限定しているため、フォームや固定バーのあるダイアログにそのまま埋め込まない。

組み込み例（アプリ側で権限と対象レコードの検証を済ませてから表示）:

```php
// assets/share_url.tpl を対象クラスの Templates/share_url.tpl へコピーする。
$ctl->assign('share_url', $ctl->get_APP_URL('public_pages', 'meeting', [
    'id' => $ctl->encrypt($meeting['id']),
]));
$ctl->assign('share_url_help', 'このURLを共有すると、申込み画面を案内できます。');
$ctl->assign('share_url_open_label', '申込み画面を開く');
$ctl->show_multi_dialog('share-url', 'share_url.tpl', '申込みURL', 680);
```

URL生成先・説明・リンクのラベル・タイトルは用途に合わせる。`share_url` はアプリが生成・検証したHTTP(S) URLを渡す（HTMLエスケープだけでは危険なスキームを拒否できない）。テンプレートはアプリ固有のID・接続先を持たない。

検証は、長いURLの折り返し、狭い画面での横はみ出しなし、URLクリックで別タブ表示、クリップボードの全文一致、コピー拒否時の案内、管理画面のテーマ色との一致を確認する。
