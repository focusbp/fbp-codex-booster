<main class="shop-page">
	<section class="shop-panel">
		<h1>Account</h1>
		<table class="shop-table">
			<tr><th>Name</th><td>{$member.name|escape}</td></tr>
			<tr><th>Email</th><td>{$member.email|escape}</td></tr>
			<tr><th>Phone</th><td>{$member.tel|escape}</td></tr>
			<tr><th>Address</th><td>{$member.zip|escape} {$member.address|escape}</td></tr>
		</table>
		<div class="shop-actions">
			<div class="shop-actions-back">
				<a class="shop-button" href="{$shop_url|escape}">Back</a>
			</div>
			<div class="shop-actions-main">
				<a class="shop-button shop-button-primary" href="{$history_url|escape}">Order History</a>
			</div>
		</div>
	</section>
</main>
