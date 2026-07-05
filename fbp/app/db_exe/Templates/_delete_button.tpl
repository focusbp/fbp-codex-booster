{if !$delete_prompt_only && ($testserver || $setting.show_developer_panel == 1)}
	<div class="db_edit_button_area">
	</div>
{/if}


{if !$delete_prompt_only}
	<button class="ajax-link" data-form="form_{$timestamp}" data-class="{$class}" data-function="delete_exe" data-db_id="{$db_id}" style="background:#b11d1d;">{t key="common.delete"}</button>
{/if}
