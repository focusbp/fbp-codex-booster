<style>
    #sample_child_search_original_management_filter_form {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px 12px;
        align-items: end;
    }
    #sample_child_search_original_management_filter_form .search_form_item {
        min-width: 0;
    }
    #sample_child_search_original_management_filter_form input,
    #sample_child_search_original_management_filter_form select {
        box-sizing: border-box;
        max-width: 100%;
        width: 100%;
    }
    @media (max-width: 1280px) {
        #sample_child_search_original_management_filter_form {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    @media (max-width: 1024px) {
        #sample_child_search_original_management_filter_form {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 760px) {
        #sample_child_search_original_management_filter_form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 520px) {
        #sample_child_search_original_management_filter_form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="search_box" style="margin:8px 0 14px 0;padding:10px 14px;border:1px solid #d7deea;border-radius:10px;background:#f8fafc;">
    <div class="search_left">
        <form id="sample_child_search_original_management_filter_form" class="search_form_flex">
            <input type="hidden" name="db_id" value="{$db_id|escape}">
            <input type="hidden" name="parent_id" value="{$parent_id|escape}">
            <div class="search_form_item field_type_text">
                <input type="text" name="keyword" value="{$filter.keyword|escape}" placeholder="キーワード">
            </div>
            <div class="search_form_item field_type_dropdown">
                <select name="status">
                    {html_options options=$status_options selected=$filter.status}
                </select>
            </div>
        </form>
    </div>
    <div class="search_right" style="display:none;">
        <button type="button" class="ajax-link" data-class="sample_child_search_original_management" data-function="apply_child_filter" data-form="sample_child_search_original_management_filter_form">Search</button>
    </div>
</div>

<table style="margin-top:10px;">
    <tbody>
    {foreach $rows as $row}
        <tr>
            <td class="row_style">
                <span class="row_title">タイトル</span>
                <span class="row_value">{fields_view_direct db="sample_child" fields="title" data=$row}</span>
            </td>
            <td class="row_style">
                <span class="row_title">ステータス</span>
                <span class="row_value">{fields_view_direct db="sample_child" fields="status" data=$row}</span>
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>

{if !$is_last}
    <div class="ajax-auto" data-class="sample_child_search_original_management" data-function="rows_child_more" data-db_id="{$db_id|escape}" data-parent_id="{$parent_id|escape}" data-max="{$max|escape}">{$max|escape}</div>
{/if}
