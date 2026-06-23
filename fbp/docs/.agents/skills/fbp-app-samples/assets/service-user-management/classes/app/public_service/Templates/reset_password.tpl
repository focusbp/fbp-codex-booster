<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Set New Password</h1>
			{if $error|default:'' != ''}<p class="service-error">{$error|escape}</p>{/if}
			<form method="post" action="{$submit_url|escape}">
				<input type="hidden" name="token" value="{$token|escape}">
				<div class="service-form-row">
					<label for="password">New Password</label>
					<input class="service-input" id="password" type="password" name="password">
				</div>
				<div class="service-actions">
					<a class="service-button" href="{$login_url|escape}">Back</a>
					<button class="service-button service-button-primary" type="submit">Save Password</button>
				</div>
			</form>
		</section>
	</div>
</main>
