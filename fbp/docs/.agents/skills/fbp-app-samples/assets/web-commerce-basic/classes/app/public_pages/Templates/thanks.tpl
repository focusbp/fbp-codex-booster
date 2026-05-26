<main class="shop-page">
	<section class="shop-panel">
		<h1>Thank You</h1>
		<p>Your order #{$order.id|escape} has been paid.</p>
		<table class="shop-table">
			<thead><tr><th>Product</th><th>Qty</th><th>Amount</th></tr></thead>
			<tbody>
				{foreach from=$items item=item}
					<tr>
						<td>{$item.product_name|escape}<br><small>{$item.variant_name|escape}</small></td>
						<td>{$item.quantity|escape}</td>
						<td>{$item.line_amount|number_format} yen</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
		<div class="shop-total">
			<p><strong>Total: {$order.total_amount|number_format} yen</strong></p>
		</div>
		<div class="shop-actions">
			<div class="shop-actions-back">
				<a class="shop-button" href="{$shop_url|escape}">Back to Shop</a>
			</div>
			<div class="shop-actions-main">
				<a class="shop-button shop-button-primary" href="{$history_url|escape}">Order History</a>
			</div>
		</div>
	</section>
</main>
