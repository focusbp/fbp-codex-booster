# Public Embedded Form Layout Sample

Use this sample when embedding a small form inside an existing public page section, especially when the form must keep `fields_form_original` for field behavior/data stability but its generated HTML causes excessive vertical spacing, fixed textarea height, or hard-to-debug grid rows.

## Rule Of Thumb

- Prefer `fields_form_original` for field-backed inputs when field definitions, value handling, option rendering, or future DB consistency matter.
- Use hand-written `.public-field` markup only for static/simple public forms where helper behavior is not needed.
- Put spacing responsibility on the form container with `display: grid` and `gap`.
- Set `.error_message { margin: 0; min-height: 0; }` and hide empty errors.
- Avoid mixing `form gap` with helper wrappers that also have top/bottom margins.
- Scope CSS to the page root and form ID so helper-output fixes do not affect other public forms.
- Target helper output such as `.field_edit`, `.field_edit h6`, `.field_edit input`, `.field_edit textarea`, and `.fbp-original-select-button` only inside the target form.
- If app CSS caching or load order is uncertain, add a short page-local `<style>{literal}...{/literal}</style>` block in the template, then keep the same rules in `public_pages/style.css` when the pattern should be reusable.

## Template

```smarty
<main class="mall-page app-inline-form-page">
	<style>
		{literal}
		.app-inline-form-page .app-form-panel {
			padding: 10px 18px;
		}
		.app-inline-form-page #app_inline_form {
			display: grid;
			grid-template-rows: repeat(5, auto);
			gap: 8px;
		}
		.app-inline-form-page #app_inline_form .field_edit {
			margin: 0 !important;
			padding: 0 !important;
		}
		.app-inline-form-page #app_inline_form .field_edit h6 {
			margin: 0 0 6px !important;
			font-size: 13px;
			font-weight: 700;
			line-height: 1.4;
		}
		.app-inline-form-page #app_inline_form .field_edit input[type="text"],
		.app-inline-form-page #app_inline_form .field_edit textarea {
			box-sizing: border-box;
			width: 100%;
			max-width: none;
			margin: 0 !important;
		}
		.app-inline-form-page #app_inline_form .field_edit textarea {
			height: 96px !important;
			min-height: 96px !important;
			resize: vertical;
		}
		.app-inline-form-page #app_inline_form .error_message {
			min-height: 0 !important;
			margin: 0 !important;
			padding: 0 !important;
		}
		.app-inline-form-page #app_inline_form .error_message:empty {
			display: none;
		}
		.app-inline-form-page #app_inline_form .public-actions {
			margin: 0 !important;
		}
		{/literal}
	</style>

	<section class="app-form-panel">
		<h2>コメントを投稿</h2>
		<form id="app_inline_form" class="public-form" onsubmit="return false;">
			<input type="hidden" name="key" value="{$key|escape}">

			{fields_form_original name="poster_name" type="text" value=$default_poster_name title="投稿者名" item_margin_top="0px"}
			<p class="error_message error_poster_name"></p>

			{fields_form_original name="message" type="textarea" value="" title="コメント" item_margin_top="0px"}
			<p class="error_message error_message"></p>

			<div class="public-actions">
				<div class="public-actions-main">
					<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="comment_save" data-form="app_inline_form">投稿する</button>
				</div>
			</div>
		</form>
	</section>
</main>
```

## Save Handler

```php
function comment_save(Controller $ctl) {
	$message = trim((string) ($ctl->POST("message") ?? ""));
	if ($message === "") {
		$ctl->res_error_message("message", "コメントを入力してください。");
		return;
	}

	$row = [
		"poster_name" => trim((string) ($ctl->POST("poster_name") ?? "")),
		"message" => $message,
		"created_at" => time(),
		"updated_at" => time(),
	];
	$ctl->db("YOUR_TABLE")->insert($row);
	$ctl->res_redirect($ctl->get_APP_URL("public_pages", "YOUR_PAGE"));
}
```

## Verification Checklist

- The template keeps `fields_form_original` for field-backed inputs unless there is a clear reason to hand-write markup.
- Browser metrics show textarea computed height matches the intended value.
- Empty `.error_message` nodes have `display: none` or zero height.
- The form has a single spacing source: `gap` on the form grid.
- Helper wrappers such as `.field_edit` have no unexpected top/bottom margin inside the scoped form.
- The section panel padding is set explicitly, usually `10px 18px`.
- Playwright screenshot confirms there is no large blank area between fields.
