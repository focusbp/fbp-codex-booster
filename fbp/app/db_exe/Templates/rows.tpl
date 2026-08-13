<table class="db_exe_main_table" style="margin-top:10px;">
<tbody>
{foreach $rows as $row}
	<tr class="active_indicator db_exe_selectable_row" data-db-id="{$db_id}" data-row-id="{$row.id}">
		{if $show_id}
		<td class="row_style">
			<span class="row_title">ID</span>
			<span class="row_value row_value_id" style="text-align:right;"><p>{$row.id}</p></span>
		</td>	
		{/if}
		{foreach $group1 as $field}
		<td class="row_style">
			<span class="row_title">{$field["parameter_title"]}</span>
			<span class="row_value">{include file="{$base_template_dir}/__item_viewer.tpl"}</span>
		</td>
		{/foreach}
		{foreach $child_tables as $c}
			{if $c.show_icon_on_parent_list == 0}
		<td class="row_style">
			<span class="row_title">{$c.menu_name}</span>
			<span class="row_value active_indicator_trigger"><span class="ajax-link material-symbols-outlined" style="cursor:pointer;width:24px;font-size: 27px;display: block;" data-class="{$class}" data-function="rows_child" data-db_id="{$c.id}" data-parent_id="{$row.id}" data-parent_field="{$c._parent_field_name|default:'parent_id'}" data-parent_db_id="{$c._parent_db_id|default:''}">table_rows</span></span>
		</td>
			{/if}
		{/foreach}
		<td class="row_style db_exe_action_cell" style="padding:10px;">
			<div class="db_exe_action_buttons">
		{if $flg_delete_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$row["_id_enc"]}" data-db_id="{$db_id}" style="float:right;color:#2d2d2d;margin-right:5px;"><span class="material-symbols-outlined">delete</span></button>
		{/if}
		{if $flg_edit_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$row["_id_enc"]}"  data-db_id="{$db_id}" style="float:right;color:#2d2d2d;"><span class="material-symbols-outlined">edit_square</span></button>
		{/if}
		{if $flg_duplicate_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="duplicate" data-id="{$row["_id_enc"]}"  data-db_id="{$db_id}" style="float:right;color:#2d2d2d;"><span class="material-symbols-outlined">content_copy</span></button>
		{/if}
			
			
		{foreach $additionals as $a}			
			{if $a.button_type == 0}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" data-id="{$row["_id_enc"]}">{$a.button_title}</button>
			{else}
				<a class="ajax-link listbutton {$a.show_button_class}" style="color:black;" invoke-class="{$a.class_name}" invoke-function="{$a.function_name}" data-id="{$row["_id_enc"]}"><span class="material-symbols-outlined">{$a.button_title}</span></a>
			{/if}
			
		{/foreach}


			</div>
		</td>
	</tr>
{/foreach}
</tbody>
</table>


{if $is_last == false}
	<div class="ajax-auto" data-class="{$class}" data-function="rows" data-max="{$max}" data-db_id="{$db_id}">{$max}</div>
{/if}

<script>
	$(".active_indicator_trigger").on("click",function(){
		$(".active_indicator").removeClass("indicator_active");
		$(this).parents(".active_indicator").addClass("indicator_active");
	});
</script>
