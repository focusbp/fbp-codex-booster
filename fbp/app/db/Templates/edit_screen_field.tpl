<form id="screen_field_edit_form_{$screen_field.id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$screen_field.id}">
	{foreach $screen_field_setting as $field}
		<h4>{$field.parameter_title}</h4>
		<h5>検索画面初期値</h5>
		<p style="margin:0 0 14px;color:#475569;">検索画面を初めて開いたときだけ適用する初期値です。利用者は検索条件を変更またはクリアできます。</p>
		{include file="{$base_template_dir}/__item_search.tpl" hide_title=true}
	{/foreach}
	<div class="button_row" style="text-align:right;margin-top:20px;">
		<button class="ajax-link" data-class="{$class}" data-function="edit_screen_field_exe" data-form="screen_field_edit_form_{$screen_field.id}">{t key="common.update"}</button>
	</div>
</form>
