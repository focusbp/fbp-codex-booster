<div>
	<p class="original_screen_confirm_message">Delete #{$row.id|escape} / {$row.name|escape}?</p>
	<div class="original_screen_dialog_actions">
		<button type="button" class="ajax-link button_link" invoke-function="delete_save" data-id="{$row.id}">Delete</button>
	</div>
</div>
