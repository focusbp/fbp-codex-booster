<main class="shop-page">
	<section class="shop-panel">
		<h1>Login</h1>
		{if $error|default:'' != ''}<p class="shop-error">{$error|escape}</p>{/if}
		<form method="post" action="{$submit_url|escape}">
			<div class="shop-form-row">
				<label for="email">Email</label>
				<input class="shop-input" id="email" type="email" name="email" value="{$email|escape}">
			</div>
			<div class="shop-form-row">
				<label for="password">Password</label>
				<input class="shop-input" id="password" type="password" name="password" value="">
			</div>
			<div class="shop-actions">
				<div class="shop-actions-back">
					<a class="shop-button" href="{$shop_url|escape}">Back</a>
				</div>
				<div class="shop-actions-main">
					<a class="shop-button" href="{$register_url|escape}">Create Account</a>
					<button class="shop-button shop-button-primary" type="submit">Login</button>
				</div>
			</div>
		</form>
	</section>
</main>
