
<div id="appcode" style="display: none;">{$appcode}</div>
{if $testserver}
	<div id="testserver" style="display: none;">true</div>
{else}
	<div id="testserver" style="display: none;">false</div>
{/if}

<div id="display_errors" style="display: none;">{$setting.display_errors}</div>

<div id="dialog"></div>
<div id="multi_dialog"></div>
<div id="new_windowID">{$new_windowID}</div>

<div id="download_view">
	<div id="download_bar">
		<div id="download_message" style="margin-left:10px;"></div>
		<div id="download_progress"></div>
	</div>
</div>

<div id="lang_priority" style="display:none;">1</div>
<div id="lang_default" style="display:none;">{$legacy_lang_default}</div>
<div id="server_language_code" style="display:none;">{$setting.framework_language_code}</div>
<div id="server_locale_code" style="display:none;">{$setting.locale_code}</div>
<div id="server_timezone" style="display:none;">{$setting.timezone}</div>
<div id="server_date_format" style="display:none;">{$setting.date_format}</div>
<div id="server_datetime_format" style="display:none;">{$setting.datetime_format}</div>
<div id="server_year_month_format" style="display:none;">{$setting.year_month_format}</div>
<div id="public_windowcode" style="display:none;">{$windowcode}</div>


{include file="{$base_template_dir}/scripts.tpl"}

<script>
	// bodyタグに data-class を付与する
	$("body").attr("data-classname","{$class}");
</script>

<script src="js/function.js?{$timestamp}"></script>

<script src="appjs.php?class={$class}&{$timestamp}"></script>

<div id="page_classname" data-class="{$class}" style="display: none;"></div>

<script>
	(function () {
		function getServerWindowcode() {
			return ($("#public_windowcode").text() || "").trim();
		}

		function getPublicWindowcode() {
			var wid = getServerWindowcode();
			if (wid !== "") {
				return wid;
			}
			try {
				wid = sessionStorage.getItem("windowID") || "";
			} catch (e) {
				wid = "";
			}
			if (wid === "") {
				wid = Cookies.get("windowID") || "";
			}
			return wid;
		}

		function bindWindowcodeToForm(form, wid) {
			if (!form || wid === "") {
				return;
			}
			var $form = $(form);
			var $field = $form.find('input[name="_windowcode"]');
			if ($field.length === 0) {
				$field = $('<input type="hidden" name="_windowcode">');
				$form.append($field);
			}
			$field.val(wid);
		}

		function bindWindowcodeToPublicPage() {
			var wid = getPublicWindowcode();
			if (wid === "") {
				return;
			}
			$("body.publicsite-body form").each(function () {
				bindWindowcodeToForm(this, wid);
			});
		}

		bindWindowcodeToPublicPage();

		$(document).on("submit", "body.publicsite-body form", function () {
			var wid = getPublicWindowcode();
			if (wid === "") {
				return;
			}
			bindWindowcodeToForm(this, wid);
		});
	})();
</script>
