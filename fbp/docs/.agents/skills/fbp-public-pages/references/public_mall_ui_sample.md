# Public Mall UI Sample

Use this reference for a clean public mall / small EC UI in `public_pages`.

The pattern is intentionally quiet and utilitarian: red top bar, white content surface, 8px radius cards, restrained shadows, compact forms, and responsive grids. It should feel like a usable shopping and order tool, not a landing page.

## Structure

Keep the shared parts separate:

- `_site_head.tpl`: CSS link, theme color, minimal critical header color if needed
- `_site_header.tpl`: brand and navigation
- `_site_register_header.tpl`: brand-only header for first-time registration before mall access is allowed
- `_site_footer.tpl`: simple footer
- `style.css`: public mall UI styles

Use `show_public_pages("page.tpl", "_site_head.tpl", "_site_header.tpl", "_site_footer.tpl")` for normal mall pages. Use the registration-only header for flows that must not expose mall navigation yet.

## Header

```smarty
{* _site_header.tpl *}
<header class="mall-header">
	<div class="mall-header-inner">
		<a class="mall-brand" href="{$shop_url|escape}">
			<span class="material-symbols-outlined mall-brand-icon" aria-hidden="true">storefront</span>
			<span>{$app_name|escape}</span>
		</a>
		<nav class="mall-nav">
			<a href="{$shop_url|escape}">モール</a>
			<a href="{$cart_url|escape}">カート{if $cart_count > 0}({$cart_count}){/if}</a>
			<a href="{$history_url|escape}">注文履歴</a>
			<a href="{$account_url|escape}">会員情報</a>
			<a href="{$inquiry_url|escape}">問い合わせ</a>
		</nav>
	</div>
</header>
```

```smarty
{* _site_register_header.tpl *}
<header class="mall-header mall-register-header">
	<div class="mall-header-inner">
		<div class="mall-brand">
			<span class="material-symbols-outlined mall-brand-icon" aria-hidden="true">storefront</span>
			<span>{$app_name|escape}</span>
		</div>
	</div>
</header>
```

```smarty
{* _site_head.tpl *}
<link rel="stylesheet" href="{$style_url|escape}?v={$style_version|escape}">
<meta name="theme-color" content="#CF2030">
```

## Product List

```smarty
<main class="mall-page">
	<h1>モール</h1>
	<form id="mall_search_form" class="mall-search" onsubmit="return false;">
		<input type="text" name="keyword" value="{$keyword|escape}" placeholder="キーワード">
		<select name="category_id">
			<option value="0">すべて</option>
			{foreach $categories as $category}
				<option value="{$category.id|escape}" {if $category_id == $category.id}selected{/if}>{$category.name|escape}</option>
			{/foreach}
		</select>
		<button type="button" class="ajax-link button_link" data-class="public_pages" data-function="submit_shop_search" data-form="mall_search_form">検索</button>
	</form>
	<div id="mall_product_list_area">
		{include file="_product_list.tpl"}
	</div>
</main>
```

```smarty
{* _product_list.tpl *}
<div class="mall-product-grid">
	{foreach $products as $product}
		<a class="mall-product-card" href="{$product.detail_url|escape}">
			{if $product.image_url}
				<img src="{$product.image_url|escape}" alt="{$product.name|escape}">
			{/if}
			<span class="mall-product-shop">{$product.shop_name|escape}</span>
			<strong>{$product.name|escape}</strong>
			<span>{$product.catch_copy|escape}</span>
			<em>{$product.price_from|number_format}円〜</em>
		</a>
	{/foreach}
	{if count($products) === 0}
		<p class="mall-empty">商品がありません。</p>
	{/if}
</div>
{if !$is_last}
	<div class="ajax-auto" data-class="public_pages" data-function="shop_more" data-max="{$max}"></div>
{/if}
```

## Cart Empty State

```smarty
<main class="mall-page mall-cart-page">
	<h1>カート</h1>
	{if $cart.items|@count == 0}
		<p class="mall-empty">カートに商品がありません。</p>
		<div class="mall-empty-actions">
			<a class="button_link" href="{$shop_url|escape}">モールへ戻る</a>
		</div>
	{/if}
</main>
```

## CSS

```css
:root {
	--mall-red: #CF2030;
	--mall-red-dark: #A71926;
	--mall-red-soft: #FBECEE;
	--mall-white: #FFF;
	--mall-ink: #1F2933;
	--mall-muted: #667085;
	--mall-line: #E6E8EC;
	--mall-bg: #FFF;
}

body.publicsite-body {
	background: var(--mall-bg);
	color: var(--mall-ink);
}

.mall-header {
	position: sticky;
	top: 0;
	z-index: 20;
	border-bottom: 1px solid var(--mall-red);
	background: var(--mall-red);
	box-shadow: 0 8px 24px rgba(31, 41, 51, 0.06);
	padding-top: env(safe-area-inset-top);
}

.mall-header-inner {
	max-width: 1040px;
	margin: 0 auto;
	padding: 14px 18px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 18px;
}

.mall-brand {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	font-weight: 800;
	color: var(--mall-white);
	text-decoration: none;
}

.mall-brand-icon {
	color: var(--mall-white);
	font-size: 24px;
	font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24;
	line-height: 1;
}

.mall-nav {
	display: flex;
	gap: 4px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.mall-nav a {
	border-radius: 6px;
	padding: 8px 10px;
	color: var(--mall-white);
	text-decoration: none;
}

.mall-nav a:hover {
	background: var(--mall-white);
	color: var(--mall-red);
}

.mall-page {
	max-width: 1040px;
	margin: 0 auto;
	padding: 30px 18px;
}

.mall-page h1 {
	margin: 0 0 18px;
	color: var(--mall-ink);
	font-size: 28px;
	line-height: 1.35;
}

.mall-search {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) 220px auto;
	gap: 10px;
	align-items: center;
	margin-bottom: 22px;
	padding: 14px;
	border: 1px solid var(--mall-line);
	border-radius: 8px;
	background: var(--mall-white);
}

.mall-search input,
.mall-search select,
.mall-cart-form select,
.mall-cart-form input,
.mall-form input,
.mall-form textarea {
	min-height: 42px;
	border: 1px solid #D0D5DD;
	border-radius: 6px;
	padding: 8px 10px;
	background: var(--mall-white);
	color: var(--mall-ink);
}

.mall-search input:focus,
.mall-search select:focus,
.mall-cart-form select:focus,
.mall-cart-form input:focus,
.mall-form input:focus,
.mall-form textarea:focus {
	outline: 2px solid rgba(207, 32, 48, 0.18);
	border-color: var(--mall-red);
}

.mall-page .button_link,
.mall-page button.button_link,
.mall-page a.button_link {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 42px;
	border: 1px solid var(--mall-red);
	border-radius: 6px;
	padding: 8px 16px;
	background: var(--mall-red);
	color: var(--mall-white) !important;
	font-weight: 700;
	line-height: 1.2;
	text-decoration: none;
	cursor: pointer;
}

.mall-page .button_link:hover {
	background: var(--mall-red-dark);
	border-color: var(--mall-red-dark);
}

.mall-page .button_link.secondary {
	background: var(--mall-white);
	color: var(--mall-red) !important;
}

.mall-page .button_link.secondary:hover {
	background: var(--mall-red-soft);
}

.mall-product-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 16px;
}

.mall-product-card {
	display: flex;
	flex-direction: column;
	gap: 9px;
	border: 1px solid var(--mall-line);
	border-radius: 8px;
	padding: 12px;
	color: var(--mall-ink);
	text-decoration: none;
	background: var(--mall-white);
	box-shadow: 0 8px 20px rgba(31, 41, 51, 0.05);
	transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.mall-product-card:hover {
	border-color: rgba(207, 32, 48, 0.38);
	box-shadow: 0 12px 28px rgba(31, 41, 51, 0.09);
	transform: translateY(-2px);
}

.mall-product-card img {
	width: 100%;
	aspect-ratio: 4 / 3;
	object-fit: cover;
	border-radius: 6px;
	background: #F5F6F7;
}

.mall-product-shop {
	color: var(--mall-muted);
	font-size: 13px;
}

.mall-product-card strong {
	font-size: 16px;
	line-height: 1.45;
}

.mall-product-card span {
	line-height: 1.55;
}

.mall-product-card em,
.mall-cart-line-amount,
.mall-receipt-amount {
	font-style: normal;
	color: var(--mall-red);
}

.mall-cart-list,
.mall-history-list,
.mall-order-items {
	display: grid;
	gap: 12px;
}

.mall-cart-item {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto 40px;
	gap: 14px;
	align-items: start;
	border-bottom: 1px solid var(--mall-line);
	padding: 16px 0;
}

.mall-history-row {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 14px;
	border: 1px solid var(--mall-line);
	border-radius: 8px;
	padding: 14px;
	color: var(--mall-ink);
	text-decoration: none;
	background: var(--mall-white);
}

.mall-detail-list {
	display: grid;
	gap: 8px;
	margin: 0;
}

.mall-detail-list div {
	display: grid;
	grid-template-columns: 150px 1fr;
	gap: 12px;
}

.mall-detail-list dt {
	color: var(--mall-muted);
}

.mall-detail-list dd {
	margin: 0;
}

.mall-form-page {
	max-width: 720px;
}

.mall-actions {
	margin-top: 18px;
}

.mall-action-row,
.mall-empty-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	justify-content: flex-end;
}

.mall-empty {
	color: #475467;
}

.mall-cart-page .mall-empty,
.mall-history-page .mall-empty {
	margin-bottom: 40px;
}

.mall-footer {
	width: 100%;
	margin: 32px 0 0;
	padding: 28px 18px 30px;
	border-top: 1px solid #D0D5DD;
	color: var(--mall-muted);
	text-align: center;
}

.mall-footer p {
	margin: 0;
	font-size: 13px;
	line-height: 1.6;
}

@media (max-width: 720px) {
	.mall-header-inner {
		align-items: flex-start;
		flex-direction: column;
	}

	.mall-nav {
		justify-content: flex-start;
	}

	.mall-search {
		grid-template-columns: 1fr;
	}

	.mall-cart-item {
		grid-template-columns: 1fr 40px;
	}

	.mall-history-row {
		flex-direction: column;
	}

	.mall-detail-list div,
	.mall-order-item {
		grid-template-columns: 1fr;
	}

	.mall-action-row {
		flex-direction: column;
		align-items: flex-start;
	}
}
```

## Notes

- Keep the dominant background white. Use red for header, primary buttons, focus, links, and prices.
- Keep cards at `8px` radius or less.
- Use real product images where possible. Product cards should reveal the actual item, not generic decorative imagery.
- Use compact headings inside operational pages. Avoid hero-scale type unless the page is truly a landing page.
- Avoid putting page sections inside decorative cards. Cards are for repeated items, receipt frames, and contained tools.
- For first-time registration before account completion, use the brand-only header so users cannot enter mall navigation prematurely.
