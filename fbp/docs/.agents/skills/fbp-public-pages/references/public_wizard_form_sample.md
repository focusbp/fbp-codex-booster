# Public Wizard Form Sample

Use this pattern for public `public_pages` forms that switch screens step by step, such as first-time LINE member registration.

The important point is that `次へ` and `戻る` are not local JavaScript-only buttons. They are `ajax-link` buttons that post the current form to a server-side step function. The function decides the next step and returns `reload_area()` for the form area.

## Controller

```php
function register(Controller $ctl) {
	$context = $this->resolve_public_context($ctl);
	if ($context["userid"] === "") {
		$this->show_error_page($ctl, [], "LINEからアクセスしてください。");
		return;
	}

	$row = [
		"userid" => $context["userid"],
		"member_type" => "",
		"chapter_name" => "",
		"connect_name" => "",
		"name" => "",
		"email" => "",
	];

	$this->assign_common($ctl, "会員登録", []);
	$this->assign_register_form($ctl, $row, "member_type");
	$ctl->show_public_pages("register.tpl", "_site_head.tpl", "_site_register_header.tpl", "_site_footer.tpl");
}

function register_step(Controller $ctl) {
	$context = $this->resolve_public_context($ctl);
	if ($context["userid"] === "") {
		$ctl->res_error_message("member_type", "LINEからアクセスしてください。");
		return;
	}

	$row = [
		"userid" => $context["userid"],
		"member_type" => trim((string) ($ctl->POST("member_type") ?? "")),
		"chapter_name" => trim((string) ($ctl->POST("chapter_name") ?? "")),
		"connect_name" => trim((string) ($ctl->POST("connect_name") ?? "")),
		"name" => trim((string) ($ctl->POST("name") ?? "")),
		"email" => trim((string) ($ctl->POST("email") ?? "")),
	];

	$current_step = trim((string) ($ctl->POST("current_step") ?? "member_type"));
	$step_action = trim((string) ($ctl->POST("step_action") ?? "next"));
	$errors = [];
	$next_step = $current_step;

	if ($step_action === "back") {
		if ($current_step === "name") {
			$next_step = $row["member_type"] === "1" ? "bni" : "member_type";
		} elseif ($current_step === "bni") {
			$next_step = "member_type";
		}
	} elseif ($current_step === "member_type") {
		if (!in_array($row["member_type"], ["0", "1"], true)) {
			$errors["member_type"] = "会員種別を選択してください。";
		} else {
			$next_step = $row["member_type"] === "1" ? "bni" : "name";
		}
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

function register_save(Controller $ctl) {
	$row = [
		"userid" => trim((string) ($ctl->POST("userid") ?? "")),
		"member_type" => trim((string) ($ctl->POST("member_type") ?? "")),
		"chapter_name" => trim((string) ($ctl->POST("chapter_name") ?? "")),
		"connect_name" => trim((string) ($ctl->POST("connect_name") ?? "")),
		"name" => trim((string) ($ctl->POST("name") ?? "")),
		"email" => trim((string) ($ctl->POST("email") ?? "")),
	];

	if (!in_array($row["member_type"], ["0", "1"], true)) {
		$ctl->res_error_message("member_type", "会員種別を選択してください。");
	}
	if ($row["member_type"] === "1" && $row["chapter_name"] === "") {
		$ctl->res_error_message("chapter_name", "BNIチャプター名を入力してください。");
	}
	if ($row["member_type"] === "1" && $row["connect_name"] === "") {
		$ctl->res_error_message("connect_name", "コネクト氏名を入力してください。");
	}
	if ($row["name"] === "") {
		$ctl->res_error_message("name", "氏名を入力してください。");
	}
	if ($ctl->count_res_error_message() > 0) {
		return;
	}

	// insert/update here
	$ctl->res_redirect($ctl->get_APP_URL("public_pages", "shop"));
}

private function assign_register_form(Controller $ctl, array $row, string $current_step, array $errors = []): void {
	if (!in_array($current_step, ["member_type", "bni", "name"], true)) {
		$current_step = "member_type";
	}
	$ctl->assign("row", $row);
	$ctl->assign("current_step", $current_step);
	$ctl->assign("errors", $errors);
	$ctl->assign("member_type_options", $ctl->get_constant_array("mall_member_type_opt", true));
}
```

## Outer Template

```smarty
{* register.tpl *}
<main class="mall-page mall-form-page">
	<h1>会員登録</h1>
	<div id="register_wizard_area">
		{include file="./_register_wizard_form.tpl"}
	</div>
</main>
```

## Partial Template

```smarty
{* _register_wizard_form.tpl *}
<form id="register_wizard_form" onsubmit="return false;">
	<input type="hidden" name="userid" value="{$row.userid|escape}">
	<input type="hidden" name="current_step" value="{$current_step|escape}">

	{if $current_step == "member_type"}
		{fields_form_original name="member_type" type="dropdown" value=$row.member_type options_arr=$member_type_options title="会員種別"}
		<p class="error_message error_member_type">{$errors.member_type|default:''|escape}</p>
		<input type="hidden" name="chapter_name" value="{$row.chapter_name|escape}">
		<input type="hidden" name="connect_name" value="{$row.connect_name|escape}">
		<input type="hidden" name="name" value="{$row.name|escape}">
		<input type="hidden" name="email" value="{$row.email|escape}">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="next">次へ</button>
	{elseif $current_step == "bni"}
		<input type="hidden" name="member_type" value="{$row.member_type|escape}">
		{fields_form_original name="chapter_name" type="text" value=$row.chapter_name title="BNIチャプター名"}
		<p class="error_message error_chapter_name">{$errors.chapter_name|default:''|escape}</p>
		{fields_form_original name="connect_name" type="text" value=$row.connect_name title="コネクト氏名"}
		<p class="error_message error_connect_name">{$errors.connect_name|default:''|escape}</p>
		<input type="hidden" name="name" value="{$row.name|escape}">
		<input type="hidden" name="email" value="{$row.email|escape}">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="next">次へ</button>
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="back">戻る</button>
	{else}
		<input type="hidden" name="member_type" value="{$row.member_type|escape}">
		<input type="hidden" name="chapter_name" value="{$row.chapter_name|escape}">
		<input type="hidden" name="connect_name" value="{$row.connect_name|escape}">
		{fields_form_original name="name" type="text" value=$row.name title="氏名"}
		<p class="error_message error_name">{$errors.name|default:''|escape}</p>
		<input type="hidden" name="email" value="{$row.email|escape}">
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_save" data-form="register_wizard_form">登録</button>
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="register_step" data-form="register_wizard_form" data-step_action="back">戻る</button>
	{/if}
</form>
```

## Verification

```bash
php -l classes/app/public_pages/public_pages.php
~/scripts/copy_to_web.sh app-xxx
cd ~/web/app-xxx/fbp
php cli.php app_call --json='{"class":"public_pages","function":"register_step","post":{"userid":"U_TEST","current_step":"member_type","member_type":"1","step_action":"next"}}'
php cli.php app_call --json='{"class":"public_pages","function":"register_step","post":{"userid":"U_TEST","current_step":"bni","member_type":"1","chapter_name":"","connect_name":"","step_action":"next"}}'
```

Confirm that:

- the first response contains `response_json.reloadarea`
- the BNI response stays on the `bni` step when required fields are blank
- required field messages are included in the partial HTML
- the final step button order matches the requested primary action order
