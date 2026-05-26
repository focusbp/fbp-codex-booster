<main class="shop-page">
	<section class="shop-panel">
		<h1>Order History</h1>
		{if $orders|@count == 0}
			<p class="shop-empty">No orders yet.</p>
		{else}
			<table class="shop-table">
				<thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Ordered</th></tr></thead>
				<tbody>
					{foreach from=$orders item=order}
						<tr>
							<td>#{$order.id|escape}</td>
							<td>{$order.order_status|escape}</td>
							<td>{$order.total_amount|number_format} yen</td>
							<td>{if $order.ordered_at|default:0 > 0}{$order.ordered_at|date_format:"%Y/%m/%d %H:%M"}{/if}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{/if}
		<div class="shop-actions">
			<div class="shop-actions-back">
				<a class="shop-button" href="{$account_url|escape}">Back</a>
			</div>
			<div class="shop-actions-main">
				<a class="shop-button shop-button-primary" href="{$shop_url|escape}">Shop</a>
			</div>
		</div>
	</section>
</main>
