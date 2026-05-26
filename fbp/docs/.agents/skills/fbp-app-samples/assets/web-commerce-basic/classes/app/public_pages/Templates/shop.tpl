<main class="shop-page">
	<form id="shop_search_form" class="shop-search" onsubmit="return false;">
		<input class="shop-input" type="text" name="keyword" value="{$keyword|escape}" placeholder="Search products">
		<select class="shop-select" name="category_id">
			<option value="0">All categories</option>
			{foreach from=$categories item=category}
				<option value="{$category.id|escape}" {if $category_id == $category.id}selected{/if}>{$category.name|escape}</option>
			{/foreach}
		</select>
		<button type="button" class="ajax-link shop-button shop-button-primary" data-class="public_pages" data-function="submit_shop_search" data-form="shop_search_form">Search</button>
	</form>
	<div id="shop_product_list_area">
		{include file="./_product_list.tpl"}
	</div>
</main>
