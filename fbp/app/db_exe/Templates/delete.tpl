
<form id="form_{$timestamp}">
	<input type="hidden" name="id" value="{$row.id}">
</form>
	
	{foreach $group1 as $field}
		<div class="row_style" style="margin-top:10px;">
			<span class="row_title">{$field["parameter_title"]}</span>
			{include file="{$base_template_dir}/__item_viewer.tpl"}
			<p class="error_message error_{$field["parameter_name"]}" style="margin-top:0px;"></p>
		</div>
	{/foreach}
