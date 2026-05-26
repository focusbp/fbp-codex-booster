<div class="shop-grid moredata">
	{foreach from=$products item=product}
		<a class="shop-card" href="{$product.detail_url|escape}">
			<div class="shop-card-media">
				{if $product.image_url|default:'' != ''}
					<img src="{$product.image_url|escape}" alt="">
				{else}
					<span>Product</span>
				{/if}
			</div>
			<div class="shop-card-body">
				<h2>{$product.name|escape}</h2>
				<p>{$product.catch_copy|default:''|escape}</p>
				<p class="shop-price">{$product.price_from|number_format} yen</p>
			</div>
		</a>
	{/foreach}
</div>
{if $products|@count == 0}
	<p class="shop-empty">No products found.</p>
{/if}
{if !$is_last}
	<div class="ajax-auto" data-class="public_pages" data-function="shop_more" data-max="{$max|escape}"></div>
{/if}
