<div class="customers_demo_detail_grid">
	{foreach from=$detail_fields item=field}
		<div class="customers_demo_detail_label">{$field|replace:"_":" "|capitalize|escape}</div>
		<div class="customers_demo_detail_value">{fields_view_direct db="customers" fields=$field data=$row}</div>
	{/foreach}
</div>
