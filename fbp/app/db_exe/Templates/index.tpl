{if $testserver || $setting.show_developer_panel == 1}
	<div class="db_edit_button_area">
		<button class="ajax-link" invoke-class="db" invoke-function="edit" data-id="{$db_id}" data-mode="database">
			<span class="material-symbols-outlined">description</span>
		</button>
	</div>
{/if}

<div class="db_exe_page_context" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}" data-class="{$class|escape}">
	<div style="float:right;margin-bottom: 8px;">

		{if $flg_add_button}
			<button class="ajax-link lang" data-class="{$class}" data-function="add" data-db_id="{$db_id}"><span class="material-symbols-outlined" style="font-size:18px;vertical-align:text-bottom;margin-right:2px;">add_circle</span>{t key="common.add"}</button>
		{else}
		{/if}

		{foreach $additionals as $a}
			{if $a.button_type == 0}
			<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}">{$a.button_title}</button>
			{else}
				<button class="ajax-link lang {$a.show_button_class}" data-class="{$a.class_name}" data-function="{$a.function_name}" style="padding:6px;"><span class="material-symbols-outlined">{$a.button_title}</span></button>
			{/if}
			
		{/foreach}


	</div>
	</div>
	<div style="clear:both;"></div>

	{if $show_properties_management_summary}
		<div class="properties_management_summary" style="margin:8px 0 14px 0;padding:12px 16px;border:1px solid #d7deea;border-radius:0;background:#f8fafc;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
			<span style="font-size:13px;font-weight:bold;color:#334155;">現在進行中の総管理物件数</span>
			<span style="font-size:26px;line-height:1;font-weight:bold;color:#0051ba;">{$properties_management_count|number_format}</span>
			<span style="font-size:13px;color:#334155;">件</span>
		</div>
	{/if}
	
	{if $show_search_box || $testserver}


	<div class="search_box" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}" style="margin:8px 0 14px 0;padding:25px 14px 5px 14px;border:1px solid #d7deea;border-radius:0px;background:#f8fafc;position: relative;">
		<p style="line-height: 1.2;
    font-weight: bold;
    color: #334155;
    font-size: 12px;
    position: absolute;
    top: 7px;
    left: 18px;">{t key="db_exe.search_panel_title"}</p>
		<div style="display:flex;flex-direction:column;justify-content:center;width:100%;">
		{if $show_search_box }
		<div class="search_left">
			<form id="form_{$timestamp}" class="search_form_flex" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}">
				<input type="hidden" name="db_id" value="{$db_id}">
				{foreach $group1 as $field}
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
				<button class="ajax-link lang" data-class="{$class}" data-function="search" data-form="form_{$timestamp}" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}">Search</button>
			</div>
		{else}
			<p class="lang" style="color:#4ba3ff;margin-left:10px;">{t key="db_exe.search_fields_not_configured"}</p>
		{/if}
		</div>
	</div>
{/if}


{if $horizontal_scroll_main}
	<div class="main_table_horizontal_scroll_fixed"><div class="main_table_horizontal_scroll_fixed_inner"></div></div>
{/if}
<div id="main_table" class="{if $horizontal_scroll_main}main_table_horizontal_scroll{/if}">
</div>
