
<table style="margin-top:10px;">
<tbody id="manual_sort{$db_id}">
{foreach $rows as $row}
	<tr id="{$row["id"]}" class="active_indicator">
		<td>
			<span><span class="material-symbols-outlined handle">swap_vert</span></span>
		</td>
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
			<span class="row_value active_indicator_trigger"><span class="ajax-link material-symbols-outlined" style="cursor:pointer;width:24px;" data-class="{$class}" data-function="rows_child" data-db_id="{$c.id}" data-parent_id="{$row.id}">table_rows</span></span>
		</td>
			{/if}
		{/foreach}
		<td>
		{if $flg_delete_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$row["_id_enc"]}" data-db_id="{$db_id}" style="float:right;color:#2d2d2d;margin-right:5px;"><span class="material-symbols-outlined">delete</span></button>
		{/if}
		{if $flg_edit_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$row["_id_enc"]}"  data-db_id="{$db_id}" style="float:right;color:#2d2d2d;"><span class="material-symbols-outlined">edit_square</span></button>
		{/if}
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
	
	
    $("#manual_sort" + "{$db_id}").sortable({
        handle:".handle",
        cancel:"button",
		axis:"y",
        start: function(event, ui){
            ui.placeholder.height(ui.helper.outerHeight());
        },
        helper: function(event, ui){
			// adjust placeholder td width to original td width
			ui.children().each(function(){
				$(this).width($(this).width());
			});
			return ui;
		},
        update: function(){
            var log = $(this).sortable("toArray");
            var fd = new FormData();
            fd.append("class","db_exe");
			fd.append("db_id",{$db_id});
            fd.append("function","manual_sort");
            fd.append("log",log);
            appcon("app.php", fd);
        }
    });
</script>
