{if $testserver || $setting.show_developer_panel == 1}
	<div class="db_edit_button_area">
	</div>
{/if}


<button class="ajax-link" data-form="form_{$timestamp}" data-class="{$class}" data-function="edit_child_exe" data-db_id="{$db_id}" data-parent_id="{$parent_id}" data-parent_field="{$parent_field|default:'parent_id'}" data-parent_db_id="{$parent_db_id|default:''}">{t key="common.update"}</button>
