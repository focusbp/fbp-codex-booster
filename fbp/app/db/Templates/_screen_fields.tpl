
<div class="screen_field_list">
	{foreach $screen_fields_arr as $screen_field}
		<div class="screen_field" id="{$screen_field.id}">
			<p><span class="ui-icon ui-icon-arrowthick-2-n-s screen_field_handle"></span><span class="screen_field_title">{$screen_field.parameter_title}</span>
				{if $screen_field.has_search_default}
					<button type="button" class="screen_field_edit ajax-link" data-class="{$class}" data-function="edit_screen_field" data-id="{$screen_field.id}" title="項目設定"><span class="material-symbols-outlined">settings</span></button>
				{/if}
				<span class="screen_field_delete ajax-link" data-class="{$class}" data-function="delete_screen_field" data-id="{$screen_field.id}">x</span></p>
		</div>
	{/foreach}
</div>
