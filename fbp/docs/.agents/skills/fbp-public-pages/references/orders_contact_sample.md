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

		if ($row["order_name"] === "") {
			$ctl->res_error_message("order_name", "案件名を入力してください。");
			return;
		}
		if ($row["amount"] === "") {
			$ctl->res_error_message("amount", "金額を入力してください。");
			return;
		}
		if (!is_numeric(str_replace(",", "", $row["amount"]))) {
			$ctl->res_error_message("amount", "金額は数値で入力してください。");
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

## contents template sample

```smarty
<form id="orders_contact_form" onsubmit="return false;">
	<input type="text" name="order_code" value="{$row.order_code|default:''}">
	<p class="error_message error_order_code"></p>

	<input type="text" name="order_name" value="{$row.order_name|default:''}">
	<p class="error_message error_order_name"></p>

	<input type="text" name="amount" value="{$row.amount|default:''}">
	<p class="error_message error_amount"></p>

	<div class="public-actions">
		<div class="public-actions-main">
			<button type="button" class="ajax-link button_link" data-class="{$class}" data-function="orders_contact_exe" data-form="orders_contact_form">送信する</button>
		</div>
	</div>
</form>
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
.publicsite-body {
	background: #f4f7fb;
	color: #0f172a;
}

.publicsite-shell {
	min-height: 100vh;
}

.publicsite-main-inner {
	padding-top: 24px;
}

.publicsite-content input[type="text"] {
	border: 1px solid #cbd5e1;
	border-radius: 8px;
	padding: 10px 12px;
	box-sizing: border-box;
}

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
	background: #0f172a;
	color: #ffffff;
	border: 0;
	border-radius: 8px;
	padding: 0 16px;
	line-height: 1.2;
	cursor: pointer;
}
```

## notes
- `public_pages/style.css` は通常の管理画面では自動読込されない。
- head 追加が必要な場合だけ `show_public_pages()` 第2引数に head 用テンプレートを渡す。
- 前後ブロックは空なら `null` のままでよい。
- エラー時は `res_error_message()` を返して即 `return` する。
