<div>
	<p style="margin:0 0 8px 0;color:#475569;">{$session_label|escape}</p>
	<p class="original_screen_confirm_message">Delete {$row.name|escape}?</p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="participant_delete_save" data-id="{$row.id|escape}">Delete</button>
	</div>
</div>
