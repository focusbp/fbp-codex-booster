
<form id="form_{$timestamp}">
	<input type="hidden" name="parent_id" value="{$parent_id}">
	<input type="hidden" name="parent_field" value="{$parent_field|default:'parent_id'}">
	<input type="hidden" name="parent_db_id" value="{$parent_db_id|default:''}">
	
	{foreach $group1 as $field}
		<div style="margin-top:10px;">
			{include file="{$base_template_dir}/__item_edit.tpl"}
			<p class="error_message error_{$field["parameter_name"]}" style="margin-top:0px;"></p>
		</div>
	{/foreach}

	<div>
		<button class="ajax-link" data-form="form_{$timestamp}" data-class="{$class}" data-function="add_child_exe" data-db_id="{$db_id}" data-parent_id="{$parent_id}" data-parent_field="{$parent_field|default:'parent_id'}" data-parent_db_id="{$parent_db_id|default:''}">{t key="common.add"}</button>
	</div>
	
</form>
