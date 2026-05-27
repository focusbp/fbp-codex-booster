
<h6 style="margin-top:10px;">{$table_title}</h6>

{if $testserver || $setting.show_developer_panel == 1}
	<div class="db_edit_button_area">
		<button class="ajax-link" invoke-class="db" invoke-function="edit" data-id="{$db_id}" data-mode="database">
		<span class="material-symbols-outlined">description</span>
		</button>
	</div>
{/if}


{if $show_search_box || $testserver}
	<div class="search_box" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}" style="margin:8px 0 14px 0;padding:10px 14px 12px 14px;border:1px solid #d7deea;border-radius:10px;background:#f8fafc;display:flex;flex-direction:column;justify-content:center;">
		<p style="margin:0 0 8px 0;min-height:18px;display:flex;align-items:center;font-size:13px;line-height:1.2;font-weight:bold;color:#334155;white-space:nowrap;">{t key="db_exe.search_panel_title"}</p>
		{if $show_search_box}
		<div class="search_left">
			<form id="form_side_{$timestamp}" class="search_form_flex" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}">
				<input type="hidden" name="db_id" value="{$db_id}">
				<input type="hidden" name="parent_id" value="{$parent_id}">
				{foreach $search_group as $field}
					<div class="search_form_item field_type_{$field.type|escape}" data-parameter-name="{$field.parameter_name|escape}" data-parameter-title="{$field.parameter_title|escape}" data-field-type="{$field.type|escape}">
						{include file="{$base_template_dir}/__item_search.tpl"}
						<p class="error_message error_{$field["parameter_name"]}" style="margin-top:0px;"></p>
						{assign var="search_field_list" value=$search_field_list|cat:$field.parameter_name}
						{assign var="search_field_list" value=$search_field_list|cat:","}
					</div>
				{/foreach}
				<input type="hidden" name="_search_field_list" value="{$search_field_list}">
			</form>
		</div>
		<div class="search_right" style="display:none;">
			<button class="ajax-link lang" data-class="{$class}" data-function="search_child" data-form="form_side_{$timestamp}" data-db-id="{$db_id}" data-parent_id="{$parent_id}">Search</button>
		</div>
		{else}
			<p class="lang" style="color:#4ba3ff;margin-left:10px;">{t key="db_exe.search_fields_not_configured"}</p>
		{/if}
	</div>
{/if}



<table style="margin-top:10px;">
<tbody id="manual_sort{$db_id}">
{foreach $rows as $row}
	<tr id="{$row["id"]}">
		
		<td>
			{if $manual_sort_search_active != true}
			<span><span class="material-symbols-outlined handle">swap_vert</span></span>
			{/if}
		</td>
		
		{foreach $group1 as $field}
		<td class="row_style">
			<span class="row_title">{$field["parameter_title"]}</span>
			<span class="row_value">{include file="{$base_template_dir}/__item_viewer.tpl"}</span>
		</td>
		{/foreach}
		<td>
			
		{if $flg_delete_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="delete_child" data-id="{$row["_id_enc"]}" data-db_id="{$db_id}" data-parent_id="{$parent_id}" style="float:right;color:#2d2d2d;margin-right:5px;"><span class="material-symbols-outlined">delete</span></button>
		{/if}
		
		{if $flg_edit_button}
		<button class="ajax-link listbutton" data-class="{$class}" data-function="edit_child" data-id="{$row["_id_enc"]}"  data-db_id="{$db_id}" data-parent_id="{$parent_id}" style="float:right;color:#2d2d2d;"><span class="material-symbols-outlined">edit_square</span></button>
		{/if}
		
		
		{foreach $additionals_row as $a}
			{if $a.button_type == 0}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" data-id="{$row["_id_enc"]}" data-parent_id="{$parent_id}">{$a.button_title}</button>
			{else}
				<button class="ajax-link listbutton {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" data-id="{$row["_id_enc"]}" data-parent_id="{$parent_id}"><span class="material-symbols-outlined" style="color:black;">{$a.button_title}</span></button>
			{/if}
		{/foreach}
		
		
		</td>
	</tr>
{/foreach}
</tbody>
</table>

<div>
	<div style="float:right;margin-bottom: 8px;">
		{if $flg_add_button}
			<button class="ajax-link lang" data-class="{$class}" data-function="add_child" data-db_id="{$db_id}" data-parent_id={$parent_id}><span class="material-symbols-outlined" style="font-size:18px;vertical-align:text-bottom;margin-right:2px;">add_circle</span>{t key="common.add"}</button>
		{else}
		{/if}
		
		
		
	</div>
	
		{foreach $additionals as $a}
			{if $a.button_type == 0}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" data-parent_id="{$parent_id}">{$a.button_title}</button>
			{else}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" data-parent_id="{$parent_id}" style="padding:6px;"><span class="material-symbols-outlined">{$a.button_title}</span></button>
			{/if}
			
		{/foreach}
		
</div>

<div style="margin-bottom:20px;clear:both;"></div>

<script>
	
	
	{if $manual_sort_search_active != true}
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
	{/if}
</script>
