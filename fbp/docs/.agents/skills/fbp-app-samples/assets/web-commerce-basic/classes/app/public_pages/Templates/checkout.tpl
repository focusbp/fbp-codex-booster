<main class="shop-page">
	<section class="shop-panel">
		<h1>Checkout</h1>
		<form id="shop_checkout_form" onsubmit="return false;">
			<div class="shop-form-row">
				<label for="buyer_name">Name</label>
				<input class="shop-input" id="buyer_name" type="text" name="buyer_name" value="{$row.buyer_name|escape}">
				{if $errors.buyer_name|default:'' != ''}<p class="shop-error">{$errors.buyer_name|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="buyer_email">Email</label>
				<input class="shop-input" id="buyer_email" type="email" name="buyer_email" value="{$row.buyer_email|escape}">
				{if $errors.buyer_email|default:'' != ''}<p class="shop-error">{$errors.buyer_email|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="buyer_tel">Phone</label>
				<input class="shop-input" id="buyer_tel" type="text" name="buyer_tel" value="{$row.buyer_tel|escape}">
				{if $errors.buyer_tel|default:'' != ''}<p class="shop-error">{$errors.buyer_tel|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="shipping_zip">Shipping ZIP</label>
				<input class="shop-input" id="shipping_zip" type="text" name="shipping_zip" value="{$row.shipping_zip|escape}">
			</div>
			<div class="shop-form-row">
				<label for="shipping_address">Shipping Address</label>
				<input class="shop-input" id="shipping_address" type="text" name="shipping_address" value="{$row.shipping_address|escape}">
				{if $errors.shipping_address|default:'' != ''}<p class="shop-error">{$errors.shipping_address|escape}</p>{/if}
			</div>
			<div class="shop-form-row">
				<label for="memo">Memo</label>
				<textarea class="shop-textarea" id="memo" name="memo">{$row.memo|escape}</textarea>
			</div>
			<table class="shop-table">
				<thead><tr><th>Product</th><th>Qty</th><th>Amount</th></tr></thead>
				<tbody>
					{foreach from=$cart_rows item=row_item}
						<tr>
							<td>{$row_item.product.name|escape}<br><small>{$row_item.variant.name|escape}</small></td>
							<td>{$row_item.quantity|escape}</td>
							<td>{$row_item.line_amount|number_format} yen</td>
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
					<a class="shop-button" href="{$back_url|escape}">Back</a>
				</div>
				<div class="shop-actions-main">
					<button class="ajax-link shop-button shop-button-primary" type="button" data-class="public_pages" data-function="start_square_payment" data-form="shop_checkout_form">Pay with Square</button>
				</div>
			</div>
		</form>
	</section>
</main>
