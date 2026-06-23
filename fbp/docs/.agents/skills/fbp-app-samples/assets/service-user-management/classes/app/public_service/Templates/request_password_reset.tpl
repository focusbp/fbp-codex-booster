<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Reset Password</h1>
			<form method="post" action="{$submit_url|escape}">
				<div class="service-form-row">
					<label for="email">Email</label>
					<input class="service-input" id="email" type="email" name="email" value="{$email|escape}">
				</div>
				<div class="service-actions">
					<a class="service-button" href="{$login_url|escape}">Back</a>
					<button class="service-button service-button-primary" type="submit">Create Reset Link</button>
				</div>
			</form>
			{if $reset_url|default:'' != ''}
				<p class="service-success">Sample reset URL: <a href="{$reset_url|escape}">{$reset_url|escape}</a></p>
			{/if}
		</section>
	</div>
</main>
