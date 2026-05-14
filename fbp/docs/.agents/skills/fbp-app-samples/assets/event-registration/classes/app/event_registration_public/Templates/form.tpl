<main class="registration-public-shell">
	<div class="registration-public-card">
		<h2 class="registration-public-session-title">{$session.title|escape}</h2>
		<p class="registration-public-meta">{$session_label|escape}</p>
	</div>

	<form class="registration-public-form" method="post" action="{$save_url|escape}">
		<input type="hidden" name="session" value="{$session_key|escape}">
		<div class="registration-public-field">
			<label class="registration-public-label">Name</label>
			<input class="registration-public-input" type="text" name="name" value="{$row.name|default:''|escape}">
			{if !empty($errors.name)}<p class="registration-public-error">{$errors.name|escape}</p>{/if}
		</div>
		<div class="registration-public-field">
			<label class="registration-public-label">Email</label>
			<input class="registration-public-input" type="email" name="email" value="{$row.email|default:''|escape}">
			{if !empty($errors.email)}<p class="registration-public-error">{$errors.email|escape}</p>{/if}
		</div>
		<div class="registration-public-field">
			<label class="registration-public-label">Phone</label>
			<input class="registration-public-input" type="text" name="phone" value="{$row.phone|default:''|escape}">
		</div>
		<div class="registration-public-field">
			<label class="registration-public-label">Message</label>
			<textarea class="registration-public-textarea" name="message">{$row.message|default:''|escape}</textarea>
		</div>
		{if !empty($errors.session)}<p class="registration-public-error">{$errors.session|escape}</p>{/if}
		<div class="registration-public-actions registration-public-form-actions">
			<button type="submit" class="registration-public-button">Register</button>
			<a class="registration-public-link" href="{$page_url|escape}">Back</a>
		</div>
	</form>
</main>
