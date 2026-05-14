<h1 class="mall-title">{$product.name|escape}</h1>
<section class="mall-card">
	<p>{$product.shop_name|escape}</p>
	<p>{$product.description|escape|nl2br}</p>
	<form class="mall-form ajax-form" method="post" action="{$cart_add_url|escape}">
		<input type="hidden" name="product_id" value="{$product.id|escape}">
		<label class="mall-label">
			種類
			<select class="mall-select" name="variant_id">
				{foreach from=$variants item=variant}
					<option value="{$variant.id|escape}">{$variant.name|escape} / 価格（税込）{$variant.price|number_format}円</option>
				{/foreach}
			</select>
		</label>
		<label class="mall-label">
			数量
			<input class="mall-input" type="number" name="quantity" value="1" min="1">
		</label>
		<button class="mall-button" type="submit">カートに入れる</button>
	</form>
</section>
