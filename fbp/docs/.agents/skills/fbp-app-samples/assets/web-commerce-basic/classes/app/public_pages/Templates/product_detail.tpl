<main class="shop-page">
	<section class="shop-panel">
		<div class="shop-product-media">
			{if $product.image_url|default:'' != ''}
				<img src="{$product.image_url|escape}" alt="">
			{else}
				<span>Product</span>
			{/if}
		</div>
		<h1 style="margin-top:16px;">{$product.name|escape}</h1>
		<p>{$product.description|escape|nl2br}</p>
		<form method="post" action="{$add_to_cart_url|escape}">
			<input type="hidden" name="product_id_enc" value="{$product_id_enc|escape}">
			<div class="shop-form-row">
				<label for="variant_id">Option</label>
				<select class="shop-select" id="variant_id" name="variant_id">
					{foreach from=$variants item=variant}
						<option value="{$variant.id|escape}">{$variant.name|escape} / {$variant.price|number_format} yen / stock {$variant.stock_quantity|escape}</option>
					{/foreach}
				</select>
			</div>
			<div class="shop-form-row">
				<label for="quantity">Quantity</label>
				<input class="shop-input" id="quantity" type="number" name="quantity" value="1" min="1">
				<p class="shop-error error_quantity"></p>
			</div>
			<div class="shop-actions">
				<div class="shop-actions-back">
					<a class="shop-button" href="{$shop_url|escape}">Back</a>
				</div>
				<div class="shop-actions-main">
					<button class="shop-button shop-button-primary" type="submit">Add to Cart</button>
				</div>
			</div>
		</form>
	</section>
</main>
