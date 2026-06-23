<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Login</h1>
			{if $error|default:'' != ''}<p class="service-error">{$error|escape}</p>{/if}
			<form method="post" action="{$submit_url|escape}">
				<div class="service-form-row">
					<label for="email">Email</label>
					<input class="service-input" id="email" type="email" name="email" value="{$email|escape}">
				</div>
				<div class="service-form-row">
					<label for="password">Password</label>
					<input class="service-input" id="password" type="password" name="password">
				</div>
				<div class="service-actions">
					<a class="service-button" href="{$password_reset_url|escape}">Forgot Password</a>
					<button class="service-button service-button-primary" type="submit">Login</button>
				</div>
			</form>
		</section>
	</div>
</main>
