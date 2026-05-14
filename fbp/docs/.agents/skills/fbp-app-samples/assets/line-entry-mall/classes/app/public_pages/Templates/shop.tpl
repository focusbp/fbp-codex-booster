<main class="mall-page">
	<h1>モール</h1>
	<div class="mall-product-grid">
		{foreach $products as $product}
			<a class="mall-product-card" href="{$product.detail_url|escape}">
				<span class="mall-product-shop">{$product.shop_name|escape}</span>
				<strong>{$product.name|escape}</strong>
				<span>{$product.catch_copy|escape}</span>
			</a>
		{/foreach}
		{if count($products) === 0}
			<p>商品がありません。</p>
		{/if}
	</div>
</main>
