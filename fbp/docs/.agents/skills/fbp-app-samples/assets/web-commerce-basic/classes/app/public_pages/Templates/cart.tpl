<main class="shop-page">
	<section class="shop-panel">
		<h1>Cart</h1>
		{if $cart_rows|@count == 0}
			<p class="shop-empty">Your cart is empty.</p>
			<div class="shop-actions">
				<div class="shop-actions-main">
					<a class="shop-button shop-button-primary" href="{$shop_url|escape}">Continue Shopping</a>
				</div>
			</div>
		{else}
			<form method="post" action="{$update_url|escape}">
				<p class="shop-error error_cart"></p>
				<table class="shop-table">
					<thead>
						<tr>
							<th>Product</th>
							<th>Price</th>
							<th>Qty</th>
							<th>Amount</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$cart_rows item=row}
							<tr>
								<td>{$row.product.name|escape}<br><small>{$row.variant.name|escape}</small></td>
								<td>{$row.unit_price|number_format} yen</td>
								<td><input class="shop-input" style="max-width:90px;" type="number" name="quantity[{$row.variant_id|escape}]" value="{$row.quantity|escape}" min="0"></td>
								<td>{$row.line_amount|number_format} yen</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				<div class="shop-total">
					<p>Subtotal: {$subtotal_amount|number_format} yen</p>
					<p>Shipping: {$shipping_fee|number_format} yen</p>
					<p>Tax: {$tax_amount|number_format} yen</p>
					<p><strong>Total: {$total_amount|number_format} yen</strong></p>
				</div>
				<div class="shop-actions">
					<div class="shop-actions-back">
						<a class="shop-button" href="{$shop_url|escape}">Back</a>
					</div>
					<div class="shop-actions-main">
						<button class="shop-button" type="submit">Update</button>
						<a class="shop-button shop-button-primary" href="{$checkout_url|escape}">Checkout</a>
					</div>
				</div>
			</form>
		{/if}
	</section>
</main>
