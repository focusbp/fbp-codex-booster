<h1 class="mall-title">カート</h1>
{if $cart_items|@count == 0}
	<section class="mall-card">
		<p>カートに商品はありません。</p>
		<a class="mall-button mall-button-subtle" href="{$shop_url|escape}">商品を見る</a>
	</section>
{else}
	<table class="mall-table">
		<thead>
			<tr>
				<th>商品</th>
				<th>種類</th>
				<th>数量</th>
				<th>金額（税込）</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$cart_items item=item}
				<tr>
					<td>{$item.product.name|escape}</td>
					<td>{$item.variant.name|escape}</td>
					<td>{$item.quantity|escape}</td>
					<td>{$item.line_amount|number_format}円</td>
					<td>
						<form class="ajax-form" method="post" action="{$cart_remove_url|escape}">
							<input type="hidden" name="variant_id" value="{$item.variant.id|escape}">
							<button class="mall-button mall-button-subtle" type="submit">削除</button>
						</form>
					</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
	<div class="mall-total">
		<div>小計: {$cart_subtotal|number_format}円</div>
		<div>送料: {$cart_shipping_fee|number_format}円</div>
		<strong>合計: {$cart_total|number_format}円</strong>
	</div>
{/if}
