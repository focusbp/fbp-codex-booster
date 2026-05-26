<style>
	:root {
		--shop-ink: #1f2937;
		--shop-muted: #667085;
		--shop-line: #d7deea;
		--shop-soft: #f5f7fb;
		--shop-primary: #1f669b;
		--shop-primary-dark: #174d76;
		--shop-white: #fff;
	}
	.shop-page {
		color: var(--shop-ink);
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		margin: 0 auto;
		max-width: 1080px;
		padding: 24px 16px 44px;
	}
	.shop-header {
		border-bottom: 1px solid var(--shop-line);
		margin-bottom: 18px;
		padding: 16px;
	}
	.shop-header-inner {
		align-items: center;
		display: flex;
		gap: 18px;
		justify-content: space-between;
		max-width: 1080px;
		margin: 0 auto;
	}
	.shop-brand {
		color: var(--shop-ink);
		font-size: 24px;
		font-weight: 800;
		text-decoration: none;
	}
	.shop-nav {
		align-items: center;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		justify-content: flex-end;
	}
	.shop-nav a,
	.shop-button {
		align-items: center;
		background: var(--shop-white);
		border: 1px solid var(--shop-line);
		border-radius: 8px;
		box-sizing: border-box;
		color: var(--shop-ink);
		display: inline-flex;
		font: inherit;
		font-weight: 700;
		justify-content: center;
		line-height: 1.2;
		margin: 0;
		min-height: 40px;
		padding: 0 16px;
		text-decoration: none;
	}
	.shop-button-primary {
		background: var(--shop-primary);
		border-color: var(--shop-primary);
		color: var(--shop-white) !important;
	}
	.shop-button-primary:hover {
		background: var(--shop-primary-dark);
		border-color: var(--shop-primary-dark);
	}
	.shop-actions {
		align-items: center;
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		justify-content: space-between;
		margin-top: 16px;
		margin-bottom: 10px;
	}
	.shop-actions-back,
	.shop-actions-main {
		align-items: center;
		display: flex;
		gap: 8px;
	}
	.shop-actions-main {
		justify-content: flex-end;
		margin-left: auto;
	}
	.shop-search {
		display: grid;
		gap: 10px;
		grid-template-columns: minmax(0, 1fr) 180px auto;
		margin-bottom: 18px;
	}
	.shop-input,
	.shop-select,
	.shop-textarea {
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		box-sizing: border-box;
		font: inherit;
		padding: 10px 12px;
		width: 100%;
	}
	.shop-textarea {
		min-height: 92px;
	}
	.shop-grid {
		display: grid;
		gap: 16px;
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	}
	.shop-card {
		border: 1px solid var(--shop-line);
		border-radius: 8px;
		color: var(--shop-ink);
		display: flex;
		flex-direction: column;
		min-height: 100%;
		overflow: hidden;
		text-decoration: none;
	}
	.shop-card-media,
	.shop-product-media {
		align-items: center;
		background: linear-gradient(135deg, #e0f2fe, #f8fafc);
		color: #2563eb;
		display: flex;
		font-size: 14px;
		font-weight: 800;
		justify-content: center;
		min-height: 150px;
	}
	.shop-card-media img,
	.shop-product-media img {
		display: block;
		height: 100%;
		object-fit: cover;
		width: 100%;
	}
	.shop-card-body {
		display: flex;
		flex: 1;
		flex-direction: column;
		gap: 8px;
		padding: 12px;
	}
	.shop-card h2,
	.shop-panel h1,
	.shop-panel h2 {
		margin: 0;
	}
	.shop-price {
		color: var(--shop-primary);
		font-weight: 800;
	}
	.shop-panel {
		border: 1px solid var(--shop-line);
		border-radius: 8px;
		margin: 0 auto;
		max-width: 760px;
		padding: 20px;
	}
	.shop-form-row {
		margin-top: 14px;
	}
	.shop-form-row label {
		display: block;
		font-weight: 800;
		margin-bottom: 6px;
	}
	.shop-error {
		color: #dc2626;
		font-size: 13px;
		font-weight: 700;
		margin: 5px 0 0;
	}
	.shop-table {
		border-collapse: collapse;
		margin-top: 14px;
		width: 100%;
	}
	.shop-table th,
	.shop-table td {
		border-bottom: 1px solid var(--shop-line);
		padding: 10px 8px;
		text-align: left;
		vertical-align: top;
	}
	.shop-table th {
		background: var(--shop-soft);
		font-weight: 800;
	}
	.shop-total {
		text-align: right;
	}
	.shop-empty {
		color: var(--shop-muted);
		margin: 20px 0;
	}
	.shop-footer {
		border-top: 1px solid var(--shop-line);
		color: var(--shop-muted);
		font-size: 13px;
		padding: 24px 16px 28px;
		text-align: center;
	}
	@media (max-width: 760px) {
		.shop-header-inner,
		.shop-nav {
			align-items: flex-start;
			flex-direction: column;
		}
		.shop-search {
			grid-template-columns: 1fr;
		}
		.shop-table {
			font-size: 13px;
		}
	}
</style>
