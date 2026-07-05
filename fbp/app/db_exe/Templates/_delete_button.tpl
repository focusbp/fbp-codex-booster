{if $testserver || $setting.show_developer_panel == 1}
	<div class="db_edit_button_area">
	</div>
{/if}


<button class="ajax-link" data-form="form_{$timestamp}" data-class="{$class}" data-function="delete_exe" data-db_id="{$db_id}" style="background:#b11d1d;">{t key="common.delete"}</button>
