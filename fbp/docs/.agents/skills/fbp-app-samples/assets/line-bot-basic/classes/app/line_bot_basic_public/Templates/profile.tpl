<div class="line-basic-shell">
	<div class="line-basic-card">
		<p class="line-basic-meta">LINE display name: {$line_member.line_name|default:""|escape}</p>
		{if $profile_error}
			<p class="line-basic-error">{$profile_error|escape}</p>
		{/if}
		<form id="line_bot_basic_profile_form" method="post" action="{$profile_save_url|escape}">
			<div>
				<p class="line-basic-label">Name</p>
				<input type="text" name="name" value="{$line_member.name|default:""|escape}" class="line-basic-input">
			</div>
			<div class="line-basic-actions">
				<button type="submit" class="line-basic-button">Save Profile</button>
			</div>
		</form>
	</div>
</div>
