# orders_contact sample

`orders_contact` は、公開フォームの基本形を確認するためのサンプル。

## purpose
- `show_public_pages()` の `contents / contents_header / contents_footer` 差し込みを確認する
- 公開フォームの `ajax-link + data-form` 送信を確認する
- 保存成功後に `$ctl->show_public_pages()` で全体遷移する形を確認する
- `public_pages/style.css` を公開側だけに適用する形を確認する

## controller sample

```php
class public_pages {

	function __construct(Controller $ctl) {
		$ctl->set_check_login(false);
	}

	function orders_contact(Controller $ctl) {
		$row = [
			"order_code" => "",
			"order_name" => "",
			"amount" => "",
		];
		$this->show_orders_contact_form($ctl, $row);
	}

	function orders_contact_exe(Controller $ctl) {
		$post = $ctl->POST();
		$row = [
			"order_code" => trim((string) ($post["order_code"] ?? "")),
			"order_name" => trim((string) ($post["order_name"] ?? "")),
			"amount" => trim((string) ($post["amount"] ?? "")),
		];

        $errors = [];
        if ($row["order_name"] === "") $errors["order_name"] = "案件名を入力してください。";
        if ($row["amount"] === "") $errors["amount"] = "金額を入力してください。";
        elseif (!is_numeric(str_replace(",", "", $row["amount"]))) $errors["amount"] = "金額は数値で入力してください。";
        if ($errors) {
            foreach ($errors as $key => $message) $ctl->res_error_message($key, $message);
            $ctl->res_error_message("form", "エラーがあります。修正してください。");
            return;
        }

		if ($row["order_code"] === "") {
			$row["order_code"] = "WEB-" . date("YmdHis");
		}
		$row["amount"] = str_replace(",", "", $row["amount"]);

		$id = (int) $ctl->db("orders")->insert($row);
		$saved = $ctl->db("orders")->get($id);
		$ctl->assign("saved", $saved);
		$ctl->assign("page_title", "お問い合わせありがとうございました");
		$ctl->show_public_pages(
			"orders_contact_done.tpl",
			null,
			"orders_contact_frame_header.tpl",
			"orders_contact_frame_footer.tpl"
		);
	}

	private function show_orders_contact_form(Controller $ctl, array $row): void {
		$ctl->assign("row", $row);
		$ctl->assign("page_title", "お問い合わせ");
		$ctl->show_public_pages(
			"orders_contact.tpl",
			null,
			"orders_contact_frame_header.tpl",
			"orders_contact_frame_footer.tpl"
		);
	}
}
```

公開フォームの見た目・コメント位置・エラー表示は [public_form_design.md](public_form_design.md) を使う。フォームを `.public-page-root` の白いカードで包み、フォーム本体に `.public-form` を付ける。

## contents template sample

```smarty
<main class="public-page-root">
<h1>お問い合わせ</h1>
<form id="orders_contact_form" class="public-form" onsubmit="return false;">
	{fields_form_original name="order_code" type="text" title="受付コード" value=$row.order_code|default:''}
	<p class="error_message error_order_code"></p>

	{fields_form_original name="order_name" type="text" title="案件名" value=$row.order_name|default:''}
	<p class="error_message error_order_name"></p>

	{fields_form_original name="amount" type="text" title="金額" value=$row.amount|default:''}
	<p class="error_message error_amount"></p>

	<p class="error_message error_form" role="alert"></p>
	<div class="public-actions">
		<div class="public-actions-main">
			<button type="button" class="ajax-link button_link" data-class="{$class}" data-function="orders_contact_exe" data-form="orders_contact_form">送信する</button>
		</div>
	</div>
</form>
</main>
```

## contents_header template sample

```smarty
<div class="orders-contact-frame-header">
	<h2>{$page_title|default:"公開ページテスト"}</h2>
</div>
```

## contents_footer template sample

```smarty
<div class="orders-contact-frame-footer">
	<p>この領域は `show_public_pages()` の第4引数で差し込む。</p>
</div>
```

## style.css sample

```css
body.publicsite-body { margin: 0; background: #f5fafc; color: #202e3c; font-family: system-ui, sans-serif; }
.public-page-root { box-sizing: border-box; width: min(100%, 720px); margin: 30px auto; padding: 20px; background: #fff; border: 1px solid #d7e8f3; border-radius: 12px; }
.public-page-root h1 { font-size: 26px; }
.public-page-root h2 { margin-top: 28px; padding-bottom: 8px; border-bottom: 1px solid #ddd; font-size: 20px; }
.public-form .field_edit h6 { margin: 0 0 6px; font-size: 14px; line-height: 1.5; }
.public-form .style_parameter_description { margin: 0 0 6px; font-size: 14px; line-height: 1.5; }
.public-form .public-field { margin-top: 10px; }
.public-form input:not([type=hidden]):not([type=checkbox]):not([type=radio]), .public-form select, .public-form textarea { box-sizing: border-box; max-width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
.public-form textarea, .public-form .public-field-wide input { width: 100%; }
.public-form .error_message { color: #b91c1c; }
.public-form .public-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 16px; margin: 24px 0 10px; }
.public-form .public-actions-back { display: flex; margin-right: auto; }
.public-form .public-actions-main { display: flex; margin-left: auto; }
.public-form .public-actions .button_link { display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; min-width: 180px; min-height: 50px; margin: 0; padding: 12px 28px; border: 0; border-radius: 6px; background: #0879f9; color: #fff; font-size: 18px; font-weight: 600; line-height: 1.4; cursor: pointer; }
.public-form .public-actions .secondary { background: #e5e7eb; color: #374151; }
.public-form .public-actions .button_link:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
```

## notes
- `public_pages/style.css` は通常の管理画面では自動読込されない。
- head 追加が必要な場合だけ `show_public_pages()` 第2引数に head 用テンプレートを渡す。
- 前後ブロックは空なら `null` のままでよい。
- 入力エラーは全項目分を収集し、項目別の `res_error_message()` とボタン上の共通メッセージをすべて返してから `return` する。
