<header class="shop-header">
	<div class="shop-header-inner">
		<a class="shop-brand" href="{$shop_url|escape}">{$app_name|escape}</a>
		<nav class="shop-nav">
			<a href="{$shop_url|escape}">Shop</a>
			<a href="{$cart_url|escape}">Cart ({$cart_count|escape})</a>
			{if $member.id|default:0 > 0}
				<a href="{$account_url|escape}">Account</a>
				<a href="{$history_url|escape}">Orders</a>
				<a href="{$logout_url|escape}">Logout</a>
			{else}
				<a href="{$login_url|escape}">Login</a>
				<a href="{$register_url|escape}">Create Account</a>
			{/if}
		</nav>
	</div>
</header>
