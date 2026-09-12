{if $testserver || $setting.show_developer_panel == 1}
<div class="db_edit_button_area">
    <button class="ajax-link" invoke-class="db" invoke-function="edit" data-id="{$db_id}" data-mode="database"><span class="material-symbols-outlined">description</span></button>
</div>
{/if}
<div class="db_exe_page_context single_record_screen" data-db-id="{$db_id}" data-tb-name="{$tb_name|escape}" data-class="{$class|escape}">
    <div class="single_record_top_buttons" style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        {foreach $additionals as $a}
            <button type="button" class="ajax-link lang {$a.show_button_class|escape}" data-class="{$a.class_name|escape}" data-function="{$a.function_name|escape}">
                {if $a.button_type == 0}{$a.button_title|escape}{else}<span class="material-symbols-outlined">{$a.button_title|escape}</span>{/if}
            </button>
        {/foreach}
    </div>
    {if $single_record_error}
        <p class="error_message">{t key="db.single.unavailable"}</p>
    {elseif !$single_record_has_fields}
        <p>{t key="db.single.fields_required"}</p>
    {else}
        <form id="single_record_form_{$timestamp}" class="single_record_form">
            {fields_form_direct field_group=$group1 data=$row}
            <button type="button" class="ajax-link" data-class="{$class|escape}" data-function="save_single_exe" data-db_id="{$db_id}" data-form="single_record_form_{$timestamp}" style="margin-top:16px;">{t key="common.save"}</button>
        </form>
    {/if}
</div>
