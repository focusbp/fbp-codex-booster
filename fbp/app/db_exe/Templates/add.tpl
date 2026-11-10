
<form id="form_{$timestamp}">
	
	{foreach $group1 as $field}
		<div{if !empty($field.standard_screen_hidden)} hidden{/if} style="margin-top:10px;">
			{include file="{$base_template_dir}/__item_edit.tpl"}
			<p class="error_message error_{$field["parameter_name"]}" style="margin-top:0px;"></p>
		</div>
	{/foreach}

	<div>
		<button class="ajax-link" data-form="form_{$timestamp}" data-class="{$class}" data-function="add_exe" data-db_id={$db_id}>{t key="common.add"}</button>
	</div>
	
</form>
