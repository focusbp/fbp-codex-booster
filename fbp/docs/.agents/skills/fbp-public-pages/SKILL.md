---
name: fbp-public-pages
description: Build and operate public_pages with login-free entry points, secure URL parameter design, and CLI-first verification.
---

# fbp-public-pages

## trigger conditions
- `public_pages` クラスを新規作成・修正する
- ログイン不要の公開導線（一覧/申込み/決済/完了）を実装する
- LINE・Webhook・メール等から公開URLを発行する

## workflow
1. `__construct` で `set_check_login(false)` を設定し、公開入口関数を決める。
2. 入口関数で公開パラメータ（例: `id`）を受け、`decrypt` して session に公開ユーザー文脈をセットする。
3. 公開ページ全体表示は `show_public_pages()` を優先する。新規の公開アプリ型UIでは必ず `show_public_pages("<contents.tpl>", "<head.tpl>", null, null, ["css_mode" => "minimal"])` を使い、管理画面向け `appstyle.css` を読み込まない。既存互換が必要な公開ページだけ、従来どおり `show_public_pages("<contents.tpl>", "<head.tpl>", "<contents_header.tpl>", "<contents_footer.tpl>")` を使う。不要な差し込みは `null` で省略してよい。操作系は `show_multi_dialog()` + `ajax-link` + `invoke-function` で遷移を組む。
4. 表示テンプレートは `fields_view_direct` を第一候補とし、手書き値展開はリンク化・複合レイアウトなど必要最小限に限定する。
   画像を一覧で軽量表示したい場合は `fields_view_direct ... use_thumbnail=true` を使い、タグ単位でサムネイル表示を指定する。
5. 保存・決済前に必須チェックを行い、異常時は `show_notification_text()` または `res_error_message()` で即 `return`。
   checkbox 項目は配列前提で扱い、会員可否のような判定は `count($line_member["xxx"] ?? []) > 0` で判定する。
6. 外部導線（LINE webhook 等）では `$ctl->get_APP_URL("public_pages", "<function>", ["id" => $id_enc])` でURLを生成する。
7. `app_call` / `app_check` で公開導線を検証し、更新系は `data_get` / `data_list` で反映確認する。
8. 公開入口の識別子は初回だけ受け、復号後は session に保存して以後の内部導線では再送しない。
9. 公開検索や絞り込みで URL に出したくない値は、`GET` ではなく `POST -> session` で保持し、表示時に復元する。
10. 公開側で `ajax-auto` によるスクロール追加を行う場合、初回表示関数と追加読込関数を分ける。初回は `show_public_pages()` で全体表示し、追加読込は一覧部分専用の関数から `reload_area()` で部分テンプレートだけを返す。

## public charts
- 公開ページで棒グラフを表示する場合、指定がない限り棒の `backgroundColor` と `borderColor` は同じ色にする。輪郭だけ濃い色にすると意図せず古い印象になりやすい。

## public downloads
- 公開側でも、LINE Bot関係ではない通常導線のダウンロードは `download-link` を基本にしてよい。
- LINE Botで送るURL、LINEメッセージから開く公開ページ、LINE内ブラウザでの利用が主目的のダウンロードは `<a href>` でGETのダウンロードURLを開く。XHR/blob経由ではなく、ブラウザに実URLの `Content-Type` / `Content-Disposition` を直接見せる。
- 実ファイル応答は特別な要件がない限り独自header実装を作らず、`$ctl->res_saved_file($stored, $download_name)` を使う。
- ダウンロードURLは文字列連結せず、`$ctl->get_APP_URL("<class>", "file_download", ["code" => $code, "download" => "1"])` のように生成する。LINE側の古い判定を避けたい場合は `download=1` などの明示パラメータを付ける。
- サンプルコードは `fbp-csv-media` の `download links` を参照する。

## minimal public pages
- `minimal` は、公開側アプリの独自デザインに管理画面CSSを影響させず、同時に FBP Ajax / dialog / Screen Log などフレームワーク連携をスムーズに使うための公開側標準モードとして扱う。
- 新規の公開アプリ型UI、ログイン後ポータル、公開側CRUD画面では `show_public_pages(..., ["css_mode" => "minimal"])` を標準にする。通常の `show_public_pages()` は既存互換ページ向けとして扱う。
- `minimal` は FBP Ajax / dialog / Screen Log アイコンに必要な共通assetsを維持しつつ、管理画面向け `appstyle.css` を読み込まない。ボタン、フォーム、カード、ページレイアウトなどの見た目は各公開アプリ側CSSで明示する。
- `publicsite_minimal.css` は、公開側Ajaxで使う共通部品だけを持つ。対象は Screen Log、`multi_dialog`、エラー表示、通知、公開側フォームの明示クラス、ダウンロード進捗、文字数カウンタ、datepicker周辺、`fbp-original-select`、`year_month_picker_panel` とし、管理画面向けの広い `button` / `form` / `table` / 見出しCSSは入れない。
- フレームワークの `appstyle.css` で Ajax 共通部品、dialog、Screen Log、datepicker、original select、download、notification、wordcounter などを変更する場合は、同じ変更が `publicsite_minimal.css` にも必要か必ず確認する。公開側 minimal で使う部品なら、広い管理画面CSSをコピーせず、対象コンポーネントの最小CSSだけを `publicsite_minimal.css` に反映する。
- 既存互換の通常 `show_public_pages()` では `appstyle.css` が読み込まれる。管理画面向けの広い `button` / icon / `.listbutton` 系CSSが公開側UIに干渉する場合があるため、新規UIは `minimal` へ寄せ、公開アプリ側のCSSで色・余白・角丸・アイコンサイズを明示して設計する。

## public minimal forms
- `css_mode=minimal` の公開側手書きフォームでは、フォームに `.public-form`、各入力項目の wrapper に `.public-field`、checkbox/radio 系の行に `.public-check`、送信ボタン行に `.public-form-actions` または `.public-actions` を付ける。
- アプリ固有のクラスは共通クラスと併用する。例: `<form class="public-form motioncards-form">`、`<div class="public-field motioncards-field">`。アプリ固有CSSは色や余白の上書きに使い、入力欄が共通セレクタ対象から外れないようにする。
- 手書き input / select / textarea を置く場合は、`.public-field` 配下に置く。`publicsite_minimal.css` の共通フォームCSSは `.public-field` 配下だけに効くため、独自の `*-form-row` などを使うと入力欄のデザインが外れる。
- `fields_form_direct` / `fields_form_original` を使う場合は、生成HTMLの都合で `.public-field` を直接付けられないことがある。必要なら公開ページ固有CSSで helper 出力の `.field_edit` / `.fbp-original-select-button` を、その画面スコープ内だけで調整する。
- `error_*` 要素は該当入力項目の直下に置き、`res_error_message(field, ...)` の field 名と一致させる。
- ダイアログに表示する部分テンプレートも同じルールに従う。`show_multi_dialog()` で表示されるフォームは、全体ページの見た目ではなく読み込まれている共通/アプリCSSのセレクタに一致しているか確認する。

```smarty
<form id="public_contact_form" class="public-form app-contact-form" onsubmit="return false;">
	<div class="public-field app-contact-field">
		<label for="public_contact_name">氏名</label>
		<input id="public_contact_name" type="text" name="name" value="{$row.name|default:''|escape}" required>
		<p class="error_message error_name">{$errors.name|default:''|escape}</p>
	</div>
	<div class="public-field app-contact-field">
		<label for="public_contact_body">お問い合わせ内容</label>
		<textarea id="public_contact_body" name="body" rows="5">{$row.body|default:''|escape}</textarea>
		<p class="error_message error_body">{$errors.body|default:''|escape}</p>
	</div>
	<div class="public-form-actions">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="contact_save" data-form="public_contact_form">送信</button>
	</div>
</form>
```

## public action buttons
- 公開フォーム/公開一覧の操作ボタンは、ボタンを直接横並びにせず、共通のアクションバーで包む。戻るボタンだけ左寄せ、送信/次へ/予約/保存などの主操作と補助操作は右寄せを基本にする。
- 基本構造は `<div class="public-actions">` の中に、戻る用の `.public-actions-back` と主操作用の `.public-actions-main` を置く。戻るがない画面では `.public-actions-back` は省略してよい。
- 下にテーブル、一覧、カード、詳細表示などが続く場合は、アクションバー下に `margin-bottom: 10px` 以上を確保する。フォーム末尾でも `margin-top` は同じ基準にして画面ごとのばらつきを避ける。
- すべての操作ボタン/ボタン風リンクに同じ `button_link` 系クラスを付け、`min-height`、`padding`、`line-height`、`display: inline-flex`、`align-items: center` を共通CSSで揃える。個別ボタンの inline style、`float`、個別 `margin` で位置調整しない。
- 主操作/戻る/補助で色や枠線を変えるのはよいが、高さ・左右余白・行内余白・ボタン間 `gap` は統一する。
- 公開側ダイアログ内の保存・追加・削除実行などのアクションボタンは右寄せにする。`publicsite_minimal.css` では `.public-actions` / `.form-actions` / `*-form-actions` / `.multi_dialog_fixed_bar` を右寄せするため、個別UIでも同系統のアクション行クラスを使う。
- `appstyle.css` 側の汎用 `button` 上マージンが公開側UIに干渉するため、公開ページのルートスコープ内で `button { margin-top: 0; }` または `button { margin: 0; }` を明示して打ち消す。
- Smarty tpl 内の `<style>` では、CSS の `{}` が Smarty 構文として解釈されないように、必ず `{literal}` ... `{/literal}` で CSS 本文を囲む。
- `classes/app/public_pages/style.css` またはページ固有CSSに、次の形をベースとして置く。

```smarty
<div class="public-actions">
	<div class="public-actions-back">
		<button type="button" class="ajax-link button_link secondary" data-class="public_pages" data-function="back_function">戻る</button>
	</div>
	<div class="public-actions-main">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="save_function" data-form="public_form">予約する</button>
	</div>
</div>
```

```css
.public-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	margin-top: 16px;
	margin-bottom: 10px;
}

.public-actions-back,
.public-actions-main {
	display: flex;
	align-items: center;
	gap: 8px;
}

.public-actions-main {
	margin-left: auto;
	justify-content: flex-end;
}

.public-actions .button_link {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	min-height: 40px;
	margin: 0;
	padding: 0 16px;
	line-height: 1.2;
}

.public-page-root button {
	margin-top: 0;
}
```

## public CRUD dialogs
- 公開側の一覧・ポータル・アプリ型UIで、追加・編集・削除のCRUD操作を置く場合は、ページ遷移ではなくダイアログを基本にする。
- 追加は一覧上部ボタンから入力ダイアログ、編集は行ボタンから編集ダイアログ、削除は行ボタンから確認ダイアログを開く。保存・削除後は一覧へ戻し、一覧部分を再表示する。
- 公開側の確認操作にブラウザ標準の `confirm()` / `window.confirm()` は使わない。削除・送信前確認・重要操作の確認は、FBP標準の `show_multi_dialog()` + `ajax-link` + `data-form` で確認ダイアログとして実装する。
- 初回公開URL、外部サイト、認証/決済、ファイルダウンロード、完了ページなどブラウザ遷移が必要な導線だけ、通常のページ遷移を使ってよい。
- `show_public_pages()` などで FBP の `function.js` / `appcon()` / `ajax-link` が使える公開ページでは、`show_multi_dialog()` + `ajax-link` + `data-form` を優先する。
- 独自の公開クラスや単体テンプレートで FBP Ajax が載っていない場合は、`show_multi_dialog()` 前提にしない。HTML `<dialog>` と小さなページ内JSで開閉し、保存は通常POST後に `res_redirect()` で一覧へ戻すなど、公開ページ単体で確実に動く形にする。
- 実装後は `app_call` だけでなく、Playwright で実際にCRUDボタンをクリックし、ダイアログが開くこと、保存・削除後に一覧へ反映されることを確認する。

## public wizard forms
- 公開側の初回登録・申込みなどをウィザード形式にする場合、ステップ切替をページ内JSだけに依存しない。公開画面ではJSイベントの初期化順や差し替え後の再bindでボタンが動かないことがある。
- 推奨形は、外枠テンプレートに `#xxx_wizard_area` を置き、フォーム部分を `_xxx_wizard_form.tpl` に切り出す。`次へ` / `戻る` は `ajax-link` + `data-form` で同一 `public_pages` 関数へ送信し、サーバー側で `current_step` と入力値を見て次のステップを決め、`reload_area("#xxx_wizard_area", "_xxx_wizard_form.tpl")` でフォーム部分だけ更新する。
- 各ステップの入力値は次ステップでも失わないよう、表示しない項目を hidden で持ち回る。最終保存では改めて全必須チェックを行う。
- ステップ移動時にも、そのステップで必須の項目は検証する。例: BNI選択時の `chapter_name` / `connect_name` は、BNIステップの `次へ` 時点で未入力なら同じステップを再表示し、項目下にエラーを出す。
- ボタン配置は `public action buttons` のアクションバーに従い、戻るは左、`次へ`/`登録` などの主操作は右に置く。
- 実装時にまとまったサンプルが必要な場合は `references/public_wizard_form_sample.md` を読む。

### wizard sample
```php
function register(Controller $ctl) {
	$this->assign_register_form($ctl, $row, "member_type");
	$ctl->show_public_pages("register.tpl", "_site_head.tpl", "_site_register_header.tpl", "_site_footer.tpl");
}

function register_step(Controller $ctl) {
	$row = [
		"member_type" => trim((string) $ctl->POST("member_type")),
		"chapter_name" => trim((string) $ctl->POST("chapter_name")),
		"connect_name" => trim((string) $ctl->POST("connect_name")),
		"name" => trim((string) $ctl->POST("name")),
	];
	$current_step = trim((string) ($ctl->POST("current_step") ?? "member_type"));
	$step_action = trim((string) ($ctl->POST("step_action") ?? "next"));
	$errors = [];
	$next_step = $current_step;

	if ($step_action === "back") {
		$next_step = $current_step === "name" && $row["member_type"] === "1" ? "bni" : "member_type";
	} elseif ($current_step === "member_type") {
		$next_step = $row["member_type"] === "1" ? "bni" : "name";
	} elseif ($current_step === "bni") {
		if ($row["chapter_name"] === "") {
			$errors["chapter_name"] = "BNIチャプター名を入力してください。";
		}
		if ($row["connect_name"] === "") {
			$errors["connect_name"] = "コネクト氏名を入力してください。";
		}
		if ($errors === []) {
			$next_step = "name";
		}
	}

	$this->assign_register_form($ctl, $row, $next_step, $errors);
	$ctl->reload_area("#register_wizard_area", "_register_wizard_form.tpl");
}
```

```smarty
{* register.tpl *}
<main>
	<h1>会員登録</h1>
	<div id="register_wizard_area">
		{include file="./_register_wizard_form.tpl"}
	</div>
</main>
```

```smarty
{* _register_wizard_form.tpl *}
<form id="register_wizard_form" onsubmit="return false;">
	<input type="hidden" name="current_step" value="{$current_step|escape}">
	{if $current_step == "member_type"}
		{fields_form_original name="member_type" type="dropdown" value=$row.member_type options_arr=$member_type_options title="会員種別"}
		<p class="error_message error_member_type">{$errors.member_type|default:''|escape}</p>
		<input type="hidden" name="chapter_name" value="{$row.chapter_name|escape}">
		<input type="hidden" name="connect_name" value="{$row.connect_name|escape}">
		<input type="hidden" name="name" value="{$row.name|escape}">
		<div class="public-actions">
			<div class="public-actions-main">
				<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="next">次へ</button>
			</div>
		</div>
	{elseif $current_step == "bni"}
		<input type="hidden" name="member_type" value="{$row.member_type|escape}">
		{fields_form_original name="chapter_name" type="text" value=$row.chapter_name title="BNIチャプター名"}
		<p class="error_message error_chapter_name">{$errors.chapter_name|default:''|escape}</p>
		{fields_form_original name="connect_name" type="text" value=$row.connect_name title="コネクト氏名"}
		<p class="error_message error_connect_name">{$errors.connect_name|default:''|escape}</p>
		<div class="public-actions">
			<div class="public-actions-back">
				<button type="button" class="ajax-link button_link secondary" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="back">戻る</button>
			</div>
			<div class="public-actions-main">
				<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="next">次へ</button>
			</div>
		</div>
	{/if}
</form>
```

## infinite scroll / ajax-auto
- `ajax-auto` の呼び先に、初回表示と同じ `show_public_pages()` 関数をそのまま使わない。公開ページ全体の HTML が返り、一覧末尾に誤挿入される。
- 一覧本体は `_list.tpl` のような部分テンプレートへ切り出し、本文テンプレート側では `<div id="list_area">{include file="./_list.tpl"}</div>` のように包む。
- 追加読込用の関数では、初回表示と同じ一覧データを組み立てた上で `reload_area("#list_area", "_list.tpl")` を返す。
- `ajax-auto` は部分テンプレートの末尾に置き、追加読込関数を `data-function` に指定する。
- `max` の増分管理は `increment_post_value("max", <page size>)` を使い、初回・追加読込で同じロジックを共有する。
- 実装後は `app_call` で、初回関数が `response_text` の全体 HTML、追加読込関数が `response_json.reloadarea` を返すことを確認する。

### sample
```php
function news(Controller $ctl) {
	$this->assign_news_list($ctl);
	$ctl->assign("page_title", "お知らせ");
	$ctl->show_public_pages("news.tpl");
}

function news_more(Controller $ctl) {
	$this->assign_news_list($ctl);
	$ctl->reload_area("#news_list_area", "_news_list.tpl");
}

private function assign_news_list(Controller $ctl) {
	$max = $ctl->increment_post_value("max", 20);
	$list = array_values($this->ffm_news->getall("id", SORT_DESC));
	$total = count($list);
	$is_last = $total <= $max;
	if (!$is_last) {
		$list = array_slice($list, 0, $max);
	}
	$ctl->assign("list", $list);
	$ctl->assign("max", $max);
	$ctl->assign("is_last", $is_last);
}
```

```smarty
{* news.tpl *}
<div class="news-page">
	<h1>{$page_title|escape}</h1>
	<div id="news_list_area">
		{include file="./_news_list.tpl"}
	</div>
</div>
```

```smarty
{* _news_list.tpl *}
<div class="news-list moredata">
	{foreach $list as $row}
		<div class="news-row">{$row.title|escape}</div>
	{/foreach}
</div>

{if !$is_last}
	<div class="ajax-auto" data-class="public_pages" data-function="news_more" data-max="{$max}"></div>
{/if}
```

## Public Assets
- `public_pages` で固定画像を使う場合は、`Public Assets` 管理で登録された `asset_key` を使う。
- 実ファイル名（`stored_filename`）をテンプレートやコードに直書きしない。
- ウィザードの `新規ページ追加` / `共通デザイン` で `Public Assets` が選択された場合、プロンプトの `【使用するPublic Assets】` に列挙された `asset_key` を前提に実装する。
- 画像URLは文字列連結せず、`$ctl->get_APP_URL("public_asset_media", "view", ["key" => $asset_key])` を使う。
- Smarty テンプレートでは `src` / `href` に URL を直書きせず、`{public_asset_url key="asset_key"}` または `{public_asset_img key="asset_key" ...}` を優先する。`&amp;amp;` のような二重エスケープ回避に有効。
- `public_asset_media` は `asset_key` または `id` で配信できるが、公開ページ実装では可読性のため `asset_key` を優先する。
- `header.tpl` / `footer.tpl` / 共通LP / ヒーロー画像など、複数ページで使う素材は `Public Assets` に寄せる。
- `public_pages` の新規作成プロンプトに `Public Assets` が含まれる場合は、選択された素材をどこで使うか（ヘッダ、KV、セクション背景、ロゴ等）を制作内容に沿って具体化して実装する。

## common layout rules
- 公開ページ全体のラップは `fbp/Templates/publicsite_index.tpl` を前提にする。
- 共通head周りの調整は `fbp/Templates/publicsite_header.tpl` を優先する。
- 共通footer周りの調整は `fbp/Templates/publicsite_footer.tpl` を優先する。
- `publicsite_index.tpl` は公開ページ共通の骨組みに限定し、ブランド名・会社名・ロゴURLなどを固定で持たせない。
- 可視ヘッダの内容は `publicsite_header.tpl`、可視フッタの内容は `publicsite_footer.tpl` 側で持つ。
- `publicsite_index.tpl` 側には `html_header` / `contents_header` / `contents` / `contents_footer` の配置枠だけを置く。
- 公開側の共通デザインを変更する主対象は `show_public_pages()` の第3引数 / 第4引数で渡すテンプレートと `classes/app/public_pages/style.css` とする。
- `publicsite_header.tpl` / `publicsite_footer.tpl` が未作成でも壊れないように、必要なら空の class 付き要素で成立する構造にする。
- head内のCSS追加は `publicsite_header.tpl` 側に置く。
- 末尾scriptや共通JS追加は `publicsite_footer.tpl` 側に置く。
- `publicsite_footer.tpl` 既存の script / DOM 責務は維持したまま拡張する。
- 公開側の共通CSSは `classes/app/public_pages/style.css` に置いてよい。通常の管理画面では自動読込されない。
- 公開側の ajax 保存後に `show_public_pages()` で完了画面を出す場合、内部では `display()` が使われる。公開導線では `_DISPLAY` 復元が安定する前提で、このワンパターンを優先してよい。
- `public_pages` の各本文テンプレートに、共通ヘッダ・フッタを重複実装しない。
- 共通デザイン作製時は、まず `publicsite_index.tpl / publicsite_header.tpl / publicsite_footer.tpl` の責務を確認してから編集範囲を決める。
- `show_public_pages()` 前提の構造を崩さない。共通レイアウト変更はこの前提の中で行う。
- 共通導線のリンク先が未確定な段階では、後から差し替えやすい実装を優先する。
- 共通導線は仮リンク、TODOコメント、差し替え用プレースホルダ構造で一旦実装してよい。
- 共通メニューは `public_pages_registry` から取得して描画する前提を優先する。
- メニュー対象は `enabled=1` かつ `show_in_menu=1` のレコードを使う。
- メニュー表示名は `menu_label` を優先し、未設定時は `title` を使う。
- メニュー順は `menu_sort` 昇順を前提にする。
- まだ公開ページが揃っていない段階でも、ヘッダ側は `public_pages_registry` のメニュー取得に寄せておく。
- LINEモールや小規模ECの公開側で、今回のような整った赤基調のモール画面を作る場合は `references/public_mall_ui_sample.md` を読む。ヘッダ、検索、商品カード、カート、履歴、空表示、フォーム、モバイル対応のCSSサンプルを含む。
- FC店ポータル、会員マイページ、店舗用業務画面など、公開側を管理画面風の業務ポータルにする場合は `references/public_admin_portal_ui_sample.md` を読む。上バー、左メニュー、検索付き一覧、サマリー、親情報を子一覧上部へ表示する横罫線テーブル、Material Symbols、AjaxでURLを変えない操作、appstyle打ち消し、dropdown/label/datetime調整、CRUD helper、`ajax-auto` の基準を含む。
- 管理画面風ポータルでは、初回URL以後はURLを変えず、CRUD・検索・削除・ページングを `ajax-link` / `ajax-button` / `reload_area()` / dialog で処理する。

## URL design rules
- URLは文字列連結せず、必ず `$ctl->get_APP_URL()` を使う。
- `id` などの公開パラメータは平文IDを使わず `encrypt()` した値を渡す。
- 受け側は `GET("id")` / `decrypt_post("id")` で復号し、対象が取れない場合は公開エラーを返して終了する。
- 公開フォームの継続導線で同じ識別子を何度も使う場合は、初回入口で暗号化済み値を session に保持してよい。`続けて入力する` リンクはパラメータ省略で同一 function に戻すほうが安定する。
- 公開側を管理画面風ポータルやアプリ型UIとして作る場合、初回入口以後はURLを変更しない。内部の画面切替・検索・保存・削除・ページングは `ajax-link` / `ajax-button` と `reload_area()` / dialog で処理する。
- `public_pages` の関数名が URL 導線単位になるため、用途ごとに関数を分ける。
- URL発行側と受け側で、クラス名・関数名・パラメータキー（例: `id`）を必ず一致させる。
- URLの基本形は `/<class>*<function>`。例: `public_pages -> lp` の場合は `/public_pages*lp`。
- クエリ付き例: `$ctl->get_APP_URL("public_pages", "lp", ["id" => $id_enc])` は `/public_pages*lp?id=<encrypted>` 形式になる。
- このフレームワークでは `/<class>*<function>&key=value` や `/<class>*<function>?key=value` を公開URLの正常系として扱う。`*` による class/function 表記や、先頭が `?class=` でないこと自体を異常扱いしない。

## root rewrite and homepage menu
- 管理側の「メニューにホームページリンクを表示」は `website_url` をリンク先として使う。`show_menu_homepage=1` でも `website_url` が空、または `http/https` URLでなければ表示されない。
- 公開側LPをホームページリンクにする場合、ルートアクセス時の rewrite と `website_url` を必ずセットで考える。
- `website_url` にシステムと関係ない外部サイトや通常の会社ホームページを入れる場合、ルートアクセス時の rewrite 設定は気にしなくてよい。
- `rewrite_rule_root=login` / `rewrite_rule_function=page` のようにルートが管理ログイン側の場合、ホームページリンクは LP の明示URLにする。例: `.../public_pages*page`。
- `rewrite_rule_root=public_pages` / `rewrite_rule_function=page` のようにルート自体がLPの場合、ホームページリンクはアプリルート `.../` でよい。
- LP関数が `page` 以外の場合や、ルートを管理側に残す場合は、`website_url` を必ず `.../public_pages*<lp_function>` にする。
- 設定後は管理メニューのリンク先だけでなく、リンク先URLがLP本文を返すことまで確認する。

## constraints
- 公開導線でも `_buttons_prompt_form.tpl` の allowlist に従う。
- エラー時に `show_multi_dialog()` 再実行や `reload_area()` で再描画しない。
- 公開側の表示は file/image に限らず `fields_view_direct` を優先する。
- 画像の一覧表示は `fields_view_direct` の `use_thumbnail=true` を優先し、コントローラ側で `_use_thumbnail` を広域代入しない。
- 公開ページのレイアウトラップ（`publicsite_index.tpl`）は `show_public_pages()` に集約し、各 `public_pages` クラスで `template_dir` を直接切り替えない。
- 公開側エントリクラス名は必ず `public_pages` を使用する（別クラス名で公開導線を作らない）。
- 公開側の通常 `form` / 通常リンクは `appcon()` を通らない。会員文脈が必要な内部導線は、原則 `ajax-link` / `invoke-function` / `appcon()` 経由を優先する。
- 公開側の通常 `<a href>` に状態維持用パラメータを付けて引き回す運用は原則禁止。検索エンジンのクロールや重複URL増殖の原因になる。
- 公開側ポータル内の通常操作に `<a href>` 遷移や通常 `form submit` を使わない。URLを変える必要があるのは、初回公開URL、外部サイト、ファイルダウンロード、決済/認証などブラウザ遷移が必要な導線に限定する。ただし FBP Ajax が載っていない独自公開ページでは、HTML `<dialog>` + 通常POST + `res_redirect()` で一覧へ戻す実装を許容する。
- 公開側で画面切替する際に、`$this->other_function($ctl)` のように別 public_pages 関数を直接呼んで遷移しない。内部状態・共通ヘッダ・Square callback 復帰・class 解決が崩れやすい。
- 公開側の画面遷移は用途に応じて `invoke()` / `show_public_pages()` / `reload_area()` / `res_redirect()` を使う。特に callback 後や保存成功後に別ページへ進める場合は、直接関数呼び出しではなく `res_redirect()` または `invoke()` を優先する。

## recommended base shape
- 公開ページの基本形は `publicsite_index.tpl` を骨格、`publicsite_header.tpl` / `publicsite_footer.tpl` を共通head・共通footer、`classes/app/public_pages/style.css` を公開側共通CSSとして分離する。
- `show_public_pages()` の第2引数は head 追加、第3引数は本文前ブロック、第4引数は本文後ブロックとして使う。
- ページ固有の見出しや補足導線は `contents_header.tpl` / `contents_footer.tpl` に切り出すと差し替えや再利用がしやすい。
- 共通デザイン案件では、第3引数 / 第4引数に渡している共通テンプレートを主に編集し、style.css の変更指示は別に持たせると実装が安定する。
- フォーム本体は本文テンプレートに置き、共通の案内・ナビ・メニュー・補足は前後テンプレートへ寄せる。
- 完了画面も原則 `show_public_pages()` でそろえ、特殊な事情がない限り `res_redirect()` に逃がさない。
- `続けて入力する` のような戻りリンクでは、復号用の識別子を URL に毎回載せ直さず session 保持へ寄せると壊れにくい。
- 一覧→詳細→一覧、検索、絞り込み、ページングなどの内部導線は、URLパラメータの引き回しより session 保持を優先する。
- 公開フォームの基本例は `references/orders_contact_sample.md` を参照する。
