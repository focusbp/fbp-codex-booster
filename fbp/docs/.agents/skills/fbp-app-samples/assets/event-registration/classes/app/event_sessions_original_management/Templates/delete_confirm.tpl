<div>
	<p class="original_screen_confirm_message">Delete {$row.title|escape}?</p>
	{if $registration_count > 0}
		<p class="error_message" style="display:block;">This event session has registrations and cannot be deleted.</p>
	{else}
		<div class="original_screen_dialog_actions">
			<button type="button" class="ajax-link button_link" invoke-function="delete_save" data-id="{$row.id|escape}">Delete</button>
		</div>
	{/if}
</div>
