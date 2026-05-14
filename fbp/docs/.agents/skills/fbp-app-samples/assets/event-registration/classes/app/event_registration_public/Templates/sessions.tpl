<main class="registration-public-shell">
	{foreach from=$sessions item=session}
		<div class="registration-public-card">
			<h2 class="registration-public-session-title">{$session.title|escape}</h2>
			<p class="registration-public-meta">{$session._date_label|escape} {$session._time_label|escape}</p>
			<p class="registration-public-meta">Remaining seats: {$session._remaining|escape}</p>
			{if $session.memo}
				<p class="registration-public-meta">{$session.memo|escape}</p>
			{/if}
			<div class="registration-public-actions registration-public-card-actions">
				<a class="registration-public-button" href="{$session._form_url|escape}">Select</a>
			</div>
		</div>
	{/foreach}
	{if count($sessions) === 0}
		<div class="registration-public-empty">
			<p>No event sessions are currently available.</p>
			<div class="registration-public-actions">
				<a class="registration-public-link" href="{$refresh_url|escape}">Refresh</a>
			</div>
		</div>
	{/if}
</main>
