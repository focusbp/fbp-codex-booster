---
name: fbp-webhook
description: Implement and operate webhook-driven integrations in FBP, including rule registration and verification.
---

# fbp-webhook

## trigger conditions
- 外部イベント受信（Webhook）を実装する
- `webhook_rule` の登録/更新が必要
- LINE連携などWebhook起点処理を扱う

## workflow
1. 受信要件と署名要件を整理。
2. 受信クラス/処理を実装。
3. `webhook_rule` を登録。
4. CLIまたは検証リクエストで受信から処理完了まで確認。

## line patterns
- LINE Bot の `keyword -> action_class` 型を作る場合は `references/line_bot_member_link.md` を読む。
- LINE user_id と会員DBを連結する場合、まず `webhook_line` 標準の `getting_member` 解決を前提にする。
- `getting_member` は LINE の生イベントではなく、`webhook_line` が前処理として呼ぶ内部解決処理。
- 既存アプリ互換のため、`[getting_member]` / `match_type=data_type` の `webhook_rule` がある場合はそれを優先できる。
- 会員連携済みのキーワード処理は、`line_webhook_context.line_member` を前提に `public_pages` URL を返す形を基本とする。
- `match_type=unmatch` は LINE `message/text` 専用のフォールバックとして扱う。
- `unmatch` は通常の `exact / contains / regex` ルールを全件評価したあと、1件も一致しなかった場合だけ実行される。
- 同一 channel では `unmatch` は 1件だけ登録する前提で扱う。
- Wizard 運用では、会員連携の標準構成を `line_member / userid / line_name / name` とする。
- `LINE用会員データベース作製` は固定テンプレートとして扱い、上記のテーブル名・フィールド名で実装する。新規案件では `getting_member` 用 `webhook_rule` / `action_class` は通常不要。
- `Line Bot処理追加` / `Line Bot処理変更` で会員連携を前提にする場合も、特別な指示がない限り `line_member / userid / line_name / name` を標準前提として扱う。
- `webhook_rule` のクラスと公開画面のクラスは分離を基本とし、同一クラスにまとめない。
- 代わりに、`webhook_rule class` と `public_pages function` を対応ペアとして設計・命名する。
- 例: `line_webhook_rule_event -> public_pages::event_list`, `line_webhook_rule_member_search -> public_pages::member_search`
- 問い合わせや申込フォームのように入力・保存がある導線は、`webhook_rule action_class` 側でフォームを持たず、`public_pages` 側で実装する。
- `webhook_rule action_class` 側は、原則として `public_pages` のURLを返す役割にとどめる。
- LINE webhook から `public_pages` へ渡す公開入口の識別子は初回だけ受け、復号後は session に保存して以後の内部導線では再送しない。
- 公開側フォームは `function.js` 読み込み前提で、管理ダイアログと同じ `<form onsubmit="return false;"> + <button class="ajax-link" data-form="...">` で実装してよい。
- 公開側フォームのバリデーションは `res_error_message()` を使い、エラー時は即 `return` する。
- `error_項目名` の表示先タグをテンプレートに必ず置く。
- 公開側で保存成功後にページ全体を切り替えたい場合は、まず `show_public_pages()` を使う。`$ctl->display()` はその内部実装として扱われる前提でよく、`reload_area()` で完了画面を差し替える前提にしない。
- 公開側でも `show_multi_dialog()` は使用できる。確認ダイアログ、補助入力、途中確認などは公開ページ上の dialog で実装してよい。
- Webhook 起点で誘導する公開側画面では、ブラウザ標準の `confirm()` / `window.confirm()` を使わない。削除・送信前確認・重要操作の確認は、FBP標準の `show_multi_dialog()` + `ajax-link` + `data-form` による確認ダイアログで実装する。
- ただし、入力導線の主画面そのものを dialog に閉じ込める必要はなく、主導線は `show_public_pages()`、補助操作だけ dialog に分ける構成を優先する。公開側の一覧・ポータル内CRUDでは、追加・編集・削除を補助操作として扱い、ダイアログを基本にする。
- 公開検索や絞り込みで URL に出したくない値は、`GET` ではなく `POST -> session` で保持し、表示時に復元する。
- 公開側の通常 `form` / 通常リンクは `appcon()` を通らない。会員文脈が必要な内部導線は、原則 `ajax-link` / `invoke-function` / `appcon()` 経由を優先する。
- 公開側の通常 `<a href>` に状態維持用パラメータを付けて引き回す運用は原則禁止。検索エンジンのクロールや重複URL増殖の原因になる。
- 一覧→詳細→一覧、検索、絞り込み、ページングなどの内部導線は、URLパラメータの引き回しより session 保持を優先する。

## public form pattern
- LINE webhook から問い合わせや申込ページへ誘導する場合は、`webhook_rule class -> public_pages URL返信 -> public_pages でフォーム表示/保存/完了表示` の3段構成を基本にする。
- `public_pages` 側では `set_check_login(false)` を設定する。
- 初回表示関数は `show_public_pages("<form.tpl>", "<head.tpl>", "<contents_header.tpl>", "<contents_footer.tpl>")` を使って公開ページ全体を出す。不要な差し込みは `null` で省略してよい。
- 送信先関数では `POST()` を受け、`res_error_message()` で項目単位エラーを返し、成功時は原則 `show_public_pages("<done.tpl>")` で完了画面へ切り替える。
- 管理ダイアログと異なり、公開側では「保存成功後に全体ページを遷移したい」ケースが多いため、その場合は `ajax-link` 成功後に `show_public_pages()` / `$ctl->display()` を使う実装を優先する。
- DB保存は `public_pages` 側で行い、`webhook_rule action_class` 側で保存処理まで抱え込まない。
- 公開側でも dialog ベースの補助導線は許容する。`show_multi_dialog()` を開き、その中で `<form onsubmit="return false;"> + ajax-link + data-form` を使う。
- LINE webhook 起点の公開側でも、一覧・ポータル内CRUDはダイアログを基本にする。追加は入力ダイアログ、編集は編集ダイアログ、削除は確認ダイアログにし、保存・削除後は一覧へ戻す。
- LINE webhook 起点の公開側でも、削除確認などを `confirm()` / `window.confirm()` に逃がさない。確認UIは FBP標準ダイアログに統一する。
- 公開側 dialog でもバリデーションは `res_error_message()` を返して即 `return` し、エラー時に `show_multi_dialog()` を再実行しない。
- 公開側 dialog の保存成功後に主画面へ戻すだけなら `close_multi_dialog()` を使う。完了ページへ全体遷移したい場合は `show_public_pages()` / `$ctl->display()` を使う。
- 公開側の共通CSSは `classes/app/public_pages/style.css` に置いてよい。通常の管理画面では自動読込されない。
- webhook 起点の公開導線サンプルは `references/line_webhook_public_form_sample.md` を参照する。
- Public Assets をテンプレートで使う場合は、`$ctl->get_APP_URL()` を文字列として埋めるより `{public_asset_url}` / `{public_asset_img}` の Smarty helper を優先する。

## line_member handoff pattern
- LINE webhook から公開URLを返す場合は、入口用の暗号化識別子だけを公開パラメータとして渡す。
- action_class 側は公開URLを返す役割にとどめ、公開入口で復号後は session に保存して内部導線では再送しない。
- `line_member` を使う公開導線では、平文IDを hidden やURLに持たせない。
- `続けて入力する` のような継続導線では、初回に受けた暗号化済み識別子を `public_pages` session に保持し、戻りリンクは `public_pages*function` のみへする構成を優先する。

### public dialog pattern
```php
function inquiry_confirm(Controller $ctl) {
	$ctl->assign("row", [
		"name" => trim((string) $ctl->POST("name")),
		"message" => trim((string) $ctl->POST("message")),
	]);
	$ctl->show_multi_dialog("public_inquiry_confirm", "inquiry_confirm.tpl", "確認", 640);
}

function inquiry_confirm_exe(Controller $ctl) {
	$post = $ctl->POST();
	if (trim((string) ($post["name"] ?? "")) === "") {
		$ctl->res_error_message("name", "名前を入力してください。");
		return;
	}
	$ctl->db("contact_inquiry")->insert([
		"name" => trim((string) ($post["name"] ?? "")),
		"message" => trim((string) ($post["message"] ?? "")),
	]);
	$ctl->close_multi_dialog("public_inquiry_confirm");
	$ctl->show_public_pages("inquiry_done.tpl");
}
```

```tpl
<form id="public_inquiry_confirm_form" onsubmit="return false;">
	<input type="hidden" name="name" value="{$row.name|escape}">
	<input type="hidden" name="message" value="{$row.message|escape}">
	<p>{$row.name|escape}</p>
	<p>{$row.message|escape|nl2br nofilter}</p>
	<p class="error_message error_name"></p>
	<p class="error_message error_message"></p>
	<button class="ajax-link" data-class="{$class}" data-function="inquiry_confirm_exe" data-form="public_inquiry_confirm_form">送信</button>
</form>
```

### public form minimal sample
```php
<?php

class public_pages {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function inquiry(Controller $ctl) {
		$ctl->assign("row", [
			"name" => "",
			"message" => "",
		]);
		$ctl->show_public_pages("inquiry.tpl");
	}

	function inquiry_exe(Controller $ctl) {
		$post = $ctl->POST();
		$row = [
			"name" => trim((string) ($post["name"] ?? "")),
			"message" => trim((string) ($post["message"] ?? "")),
		];

		if ($row["name"] === "") {
			$ctl->res_error_message("name", "名前を入力してください。");
			return;
		}
		if ($row["message"] === "") {
			$ctl->res_error_message("message", "内容を入力してください。");
			return;
		}

		$ctl->db("contact_inquiry")->insert($row);
		$ctl->assign("saved", $row);
		$ctl->show_public_pages("inquiry_done.tpl");
	}
}
```

```tpl
<form id="inquiry_form" onsubmit="return false;">
	<input type="text" name="name" value="{$row.name|escape}">
	<p class="error_message error_name"></p>

	<textarea name="message">{$row.message|escape}</textarea>
	<p class="error_message error_message"></p>

	<button class="ajax-link" data-class="{$class}" data-function="inquiry_exe" data-form="inquiry_form">送信</button>
</form>
```

## unmatch action_class rules
- `unmatch` の action_class では `$ctl->get_session("line_webhook_context")` から入力本文を取得する。
- 取得キーは `text` を使う。`message/text` の未一致本文がそのまま入る。
- `line_webhook_context` には通常 `event`, `text`, `userid`, `displayname`, `line_member`, `rule`, `data_type` が入る前提で扱う。
- `unmatch` action_class は `run(Controller $ctl)` を実装する。
- 文字列を返すと、その文字列をLINEへ返信して処理終了になる。
- `["reply_text" => "..."]` を返しても返信できる。
- `["handled" => true]` を返すと処理済み扱いになり、後続の管理者転送へ進まない。
- `["handled" => false]` を返すと、`unmatch` action_class 実行後も処理継続となり、設定次第で管理者転送へ進む。
- `null` を返すと現状の `webhook_line` ではその場で処理終了寄りになるため、管理者転送と共存したい場合は使わず `["handled" => false]` を返す。
- `unmatch` と管理者転送を共存させたい場合は、「DB保存や通知文整形だけ action_class で行い、戻り値は `["handled" => false]`」を基本にする。
- `unmatch` で返信だけして終了したい場合は、文字列または `["reply_text" => "...", "handled" => true]` を返す。

### sample code
```php
<?php

class line_webhook_rule_unmatch_sample {

	function run(Controller $ctl) {
		$context = $ctl->get_session("line_webhook_context");
		$text = trim((string) ($context["text"] ?? ""));
		$line_member = is_array($context["line_member"] ?? null) ? $context["line_member"] : [];
		$userid = trim((string) ($context["userid"] ?? ""));

		if ($text === "") {
			return [
				"handled" => false,
			];
		}

		// 例: ログ保存やDB記録をここで行う
		$ctl->log("[line unmatch] userid=" . $userid . " text=" . $text);

		// 1. 返信して終了したい場合
		// return "お問い合わせありがとうございます。担当者が確認します。";

		// 2. 返信しつつ終了したい場合
		// return [
		// 	"reply_text" => "内容を受け付けました。",
		// 	"handled" => true,
		// ];

		// 3. 管理者転送と共存したい場合
		return [
			"handled" => false,
		];
	}
}
```

## constraints
- 機密情報（シークレット、トークン）をコード直書きしない。
- LINE 連携では `webhook_rule.channel=0` を使う。
- `webhook_rule` は `channel + keyword` 重複を避ける。
- `match_type=unmatch` は同一 channel に複数登録しない。
- 問い合わせ・申込フォームの保存処理は `public_pages` 側に置き、`webhook_rule action_class` にフォーム処理を混在させない。
- `getting_member` を実装する場合は、会員検索キー・表示名保存先・未登録時の新規作成方針を明示する。
