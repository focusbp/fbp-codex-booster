# db_additionals 編集・削除カスタマイズ例

標準画面の編集・削除をプロジェクト固有に変える場合は、対象 screen の `screen_fields` を空にして標準アイコンを消し、`db_additionals` の行ボタンから app 側クラスを呼ぶ。
framework の `db_exe` に app 固有分岐を入れない。

## 登録例

```bash
php /home/nakama/web/app-xxx/fbp/cli.php db_additionals_add --json='{
  "tb_name":"customer",
  "button_title":"edit_square",
  "class_name":"customer_custom_edit",
  "function_name":"run",
  "place":1,
  "code_type":[0],
  "reload":1,
  "user_request":"顧客一覧の行ボタンから専用編集ダイアログを表示",
  "databases":[12],
  "close_button":2,
  "sort":100,
  "ui_mode":0,
  "show_button":0,
  "dialog_width":760,
  "button_type":1
}'
```

削除用は `button_title:"delete"`、`class_name:"customer_custom_delete"` のように別クラスへ分ける。
標準の編集・削除アイコンを消すには、該当する `screen_fields` を削除して空にする。

## 編集クラス例

```php
<?php

class customer_custom_edit implements CodegenActionInterface {
    private function decodeId(Controller $ctl, $value): int {
        if ($value === null || $value === "") {
            return 0;
        }
        $value = (string) $value;
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $decoded = $ctl->decrypt($value);
        return ctype_digit((string) $decoded) ? (int) $decoded : 0;
    }

    function run(Controller $ctl) {
        $id = $this->decodeId($ctl, $ctl->POST()["id"] ?? "");
        $row = $id > 0 ? $ctl->db("customer")->get($id) : [];
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません", 2, "#950000", "#FFF");
            return;
        }

        $row["id"] = $ctl->encrypt($id);
        $ctl->assign("row", $row);
        $ctl->assign_field_settings("fields", "customer", "edit", false, false);
        $ctl->show_multi_dialog("customer_custom_edit", "edit.tpl", "顧客編集", 760, "_edit_fixed_bar.tpl");
    }

    function save(Controller $ctl) {
        $post = $ctl->POST();
        $id = $this->decodeId($ctl, $post["id"] ?? "");
        $row = $id > 0 ? $ctl->db("customer")->get($id) : [];
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません", 2, "#950000", "#FFF");
            return;
        }

        $row["name"] = trim((string) ($post["name"] ?? ""));
        if ($row["name"] === "") {
            $ctl->res_error_message("name", "名称を入力してください");
            return;
        }

        $ctl->db("customer")->update($row);
        $ctl->close_multi_dialog("customer_custom_edit");
        $ctl->invoke("rows", ["db_id" => 12], "db_exe");
    }
}
```

`Templates/edit.tpl`:

```smarty
<form id="customer_custom_edit_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$row.id|escape}">
	{foreach $fields as $field}
		<div class="row_style" style="margin-top:10px;">
			<span class="row_title">{$field.parameter_title|escape}</span>
			{include file="{$base_template_dir}/__item_form.tpl"}
			<p class="error_message error_{$field.parameter_name|escape}" style="margin-top:0;"></p>
		</div>
	{/foreach}
</form>
```

`Templates/_edit_fixed_bar.tpl`:

```smarty
<button type="button" class="ajax-link" data-class="customer_custom_edit" data-function="save" data-form="customer_custom_edit_form">保存</button>
```

## 削除クラス例

```php
<?php

class customer_custom_delete implements CodegenActionInterface {
    private function decodeId(Controller $ctl, $value): int {
        if ($value === null || $value === "") {
            return 0;
        }
        $value = (string) $value;
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $decoded = $ctl->decrypt($value);
        return ctype_digit((string) $decoded) ? (int) $decoded : 0;
    }

    function run(Controller $ctl) {
        $id = $this->decodeId($ctl, $ctl->POST()["id"] ?? "");
        $row = $id > 0 ? $ctl->db("customer")->get($id) : [];
        if (empty($row)) {
            $ctl->show_notification_text("対象データが見つかりません", 2, "#950000", "#FFF");
            return;
        }

        $row["id"] = $ctl->encrypt($id);
        $ctl->assign("row", $row);
        $ctl->assign_field_settings("fields", "customer", "delete", false, false);
        $ctl->show_multi_dialog("customer_custom_delete", "delete.tpl", "削除確認", 600, "_delete_fixed_bar.tpl");
    }

    function delete_exe(Controller $ctl) {
        $id = $this->decodeId($ctl, $ctl->POST()["id"] ?? "");
        if ($id <= 0 || empty($ctl->db("customer")->get($id))) {
            $ctl->show_notification_text("対象データが見つかりません", 2, "#950000", "#FFF");
            return;
        }

        $ctl->db("customer")->delete($id);
        $ctl->delete_files("customer", $id);
        $ctl->close_multi_dialog("customer_custom_delete");
        $ctl->invoke("rows", ["db_id" => 12], "db_exe");
    }
}
```

`Templates/delete.tpl`:

```smarty
<form id="customer_custom_delete_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$row.id|escape}">
	{foreach $fields as $field}
		<div class="row_style" style="margin-top:10px;">
			<span class="row_title">{$field.parameter_title|escape}</span>
			{include file="{$base_template_dir}/__item_viewer.tpl"}
		</div>
	{/foreach}
</form>
```

`Templates/_delete_fixed_bar.tpl`:

```smarty
<button type="button" class="ajax-link" data-class="customer_custom_delete" data-function="delete_exe" data-form="customer_custom_delete_form" style="background:#b11d1d;">削除する</button>
```

## ダイアログ内レイアウトCSS例

ダイアログ本文で `<section>` や `<h4>` を使う場合、framework 共通CSSの `section { width:1100px; }` と `section h4:first-child { padding-top:0; }` が効く。
横幅や見出しの余白を専用ダイアログ内だけで調整したい場合は、app 側テンプレートの `{literal}<style>...` に局所セレクタを置く。

```smarty
<div class="customer-custom-dialog">
	<section class="customer-custom-section">
		<h4>確認情報</h4>
		...
	</section>
</div>

{literal}
<style>
section.customer-custom-section {
	box-sizing: border-box;
	width: 100%;
	max-width: 1100px;
}
.customer-custom-dialog .customer-custom-section h4 {
	margin: 0 0 8px;
	padding: 8px 10px;
	background: #e5e7eb;
	font-size: 15px;
}
</style>
{/literal}
```

- `section.customer-custom-section` で共通の固定幅を打ち消し、ダイアログ幅に収める。
- `.customer-custom-dialog .customer-custom-section h4` のように詳細度を上げ、共通の `section h4:first-child` より優先させる。
- framework 共通CSSや `db_exe` に app 固有分岐を入れない。

## 検証

- `standard_screen_check --json='{"tb_name":"customer"}'` で ERROR がないことを確認する。
- `app_call` で `db_exe/rows` を呼び、標準の `db_exe edit/delete` アイコンが消えていること、追加した `db_additionals` 行ボタンが出ることを確認する。
- 行ボタンの class/function を `app_call` し、ダイアログHTML、保存/削除後の一覧更新、対象データの更新/削除を確認する。
- ダイアログ内で `<section>` / `<h4>` を使った場合は、横幅がダイアログからはみ出さないこと、先頭見出しの上paddingが意図通り効くことを確認する。
