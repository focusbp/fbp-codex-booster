<main class="service-main">
	<div class="service-shell">
		<section class="service-panel">
			<h1>Create Account</h1>
			<form method="post" action="{$submit_url|escape}">
				<div class="service-form-row">
					<label for="name">Name</label>
					<input class="service-input" id="name" name="name" value="{$row.name|escape}">
					{if $errors.name|default:'' != ''}<p class="service-error">{$errors.name|escape}</p>{/if}
				</div>
				<div class="service-form-row">
					<label for="email">Email</label>
					<input class="service-input" id="email" type="email" name="email" value="{$row.email|escape}">
					{if $errors.email|default:'' != ''}<p class="service-error">{$errors.email|escape}</p>{/if}
				</div>
				<div class="service-form-row">
					<label for="password">Password</label>
					<input class="service-input" id="password" type="password" name="password">
					{if $errors.password|default:'' != ''}<p class="service-error">{$errors.password|escape}</p>{/if}
				</div>
				<div class="service-actions">
					<a class="service-button" href="{$login_url|escape}">Login</a>
					<button class="service-button service-button-primary" type="submit">Create Account</button>
				</div>
			</form>
		</section>
	</div>
</main>
