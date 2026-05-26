<main class="shop-page">
	<section class="shop-panel">
		<h1>Create Account</h1>
		<form method="post" action="{$submit_url|escape}">
			<div class="shop-form-row">
				<label for="name">Name</label>
				<input class="shop-input" id="name" type="text" name="name" value="{$row.name|escape}">
				{if $errors.name|default:'' != ''}<p class="shop-error">{$errors.name|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="email">Email</label>
				<input class="shop-input" id="email" type="email" name="email" value="{$row.email|escape}">
				{if $errors.email|default:'' != ''}<p class="shop-error">{$errors.email|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="password">Password</label>
				<input class="shop-input" id="password" type="password" name="password" value="">
				{if $errors.password|default:'' != ''}<p class="shop-error">{$errors.password|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="tel">Phone</label>
				<input class="shop-input" id="tel" type="text" name="tel" value="{$row.tel|escape}">
			</div>
			<div class="shop-form-row">
				<label for="zip">ZIP</label>
				<input class="shop-input" id="zip" type="text" name="zip" value="{$row.zip|escape}">
			</div>
			<div class="shop-form-row">
				<label for="address">Address</label>
				<input class="shop-input" id="address" type="text" name="address" value="{$row.address|escape}">
			</div>
			<div class="shop-actions">
				<div class="shop-actions-back">
					<a class="shop-button" href="{$shop_url|escape}">Back</a>
				</div>
				<div class="shop-actions-main">
					<button class="shop-button shop-button-primary" type="submit">Create Account</button>
				</div>
			</div>
		</form>
	</section>
</main>
