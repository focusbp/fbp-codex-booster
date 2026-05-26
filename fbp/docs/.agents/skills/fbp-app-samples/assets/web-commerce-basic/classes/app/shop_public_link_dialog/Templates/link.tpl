<div class="shop-public-link-dialog">
	<p class="shop-public-link-note">Use this URL as the public storefront entry point.</p>
	<div class="shop-public-link-copy">
		<input id="shop_public_link_url" type="text" value="{$public_shop_url|escape}" readonly>
		<button type="button" class="button_link" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('shop_public_link_url').value);">Copy</button>
	</div>
	<div class="shop-public-link-actions">
		<a class="button_link" href="{$public_shop_url|escape}" target="_blank" rel="noopener">Open Public EC</a>
	</div>
</div>

<style>
	.shop-public-link-dialog {
		color: #1f2937;
	}
	.shop-public-link-note {
		margin: 0 0 12px;
	}
	.shop-public-link-copy {
		align-items: center;
		display: flex;
		gap: 10px;
		margin-bottom: 10px;
	}
	.shop-public-link-copy input {
		border: 1px solid #cbd5e1;
		border-radius: 6px;
		box-sizing: border-box;
		flex: 1;
		font: inherit;
		padding: 10px 12px;
	}
	.shop-public-link-actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 10px;
	}
	@media (max-width: 640px) {
		.shop-public-link-copy {
			align-items: stretch;
			flex-direction: column;
		}
		.shop-public-link-actions {
			justify-content: flex-start;
		}
	}
</style>
