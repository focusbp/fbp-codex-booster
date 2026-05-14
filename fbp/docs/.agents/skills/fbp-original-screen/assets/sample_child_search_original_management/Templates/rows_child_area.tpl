<div class="search_box" style="margin:8px 0 14px 0;padding:10px 14px;border:1px solid #d7deea;border-radius:10px;background:#f8fafc;">
    <form id="sample_child_search_original_management_filter_form">
        <input type="hidden" name="db_id" value="{$db_id|escape}">
        <input type="hidden" name="parent_id" value="{$parent_id|escape}">
        <input type="text" name="keyword" value="{$filter.keyword|escape}" placeholder="キーワード" style="width:100%;box-sizing:border-box;margin-bottom:8px;">
        <select name="status" style="width:100%;box-sizing:border-box;margin-bottom:8px;">
            {html_options options=$status_options selected=$filter.status}
        </select>
        <button type="button" class="ajax-link" data-class="sample_child_search_original_management" data-function="apply_child_filter" data-form="sample_child_search_original_management_filter_form">検索</button>
    </form>
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
