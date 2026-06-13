<form id="{$form_id}" onsubmit="return false;">
	<input type="hidden" name="id" value="{$tool.id}">
	<div id="{$field_area_id}">
		{include file="./_tool_fields_matrix.tpl"}
	</div>
	<div style="display:flex;justify-content:flex-end;margin-top:12px;">
		<button class="ajax-link" data-class="{$class}" data-function="fields_exe" data-form="{$form_id}">{t key="common.save"}</button>
	</div>
</form>
