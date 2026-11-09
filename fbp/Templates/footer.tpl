

	
<div id="dialog"></div>
<div id="multi_dialog"></div>
<div id="new_windowID">{$new_windowID}</div>

{if $testserver}
	<div id="testserver" style="display: none;">true</div>
{else}
	<div id="testserver" style="display: none;">false</div>
{/if}
<div id="display_errors" style="display: none;">{$setting.display_errors}</div>
<div id="lang_priority" style="display:none;">1</div>
<div id="lang_default" style="display:none;">{$legacy_lang_default}</div>
<div id="server_language_code" style="display:none;">{$setting.framework_language_code}</div>
<div id="server_locale_code" style="display:none;">{$setting.locale_code}</div>
<div id="server_timezone" style="display:none;">{$setting.timezone}</div>
<div id="server_date_format" style="display:none;">{$setting.date_format}</div>
<div id="server_datetime_format" style="display:none;">{$setting.datetime_format}</div>
<div id="server_year_month_format" style="display:none;">{$setting.year_month_format}</div>

<div id="session"></div>

{include file="{$base_template_dir}/__management_csrf.tpl"}
<script src="js/function.js?{$timestamp}"></script>

<script src="appjs.php?class=base&{$timestamp}"></script>

{foreach $js_class_list as $jsclass}
	<script src="appjs.php?class={$jsclass}&{$timestamp}"></script>
{/foreach}

