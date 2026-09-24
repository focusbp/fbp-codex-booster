<div id="sample_note_original_management_list_area">
    <p class="original_screen_toolbar_note">Sample Original Management の一覧です。表示件数: {$count}</p>
    <div class="original_screen_list_scroll">
    <table class="original_screen_table">
    <tbody>
        {foreach $rows as $row}
            <tr class="active_indicator">
                <td class="row_style" style="width:80px;">
                    <span class="row_title">ID</span>
                    <span class="row_value row_value_id" style="text-align:right;"><p>{$row.id|escape}</p></span>
                </td>
                <td class="row_style">
                    <span class="row_title">題名</span>
                    <span class="row_value">{fields_view_direct db="sample_note" fields="title" data=$row}</span>
                </td>
                <td class="row_style" style="width:180px;">
                    <span class="row_title">ステータス</span>
                    <span class="row_value">{fields_view_direct db="sample_note" fields="status" data=$row}</span>
                </td>
                <td class="row_style original_screen_action_cell">
                    <button type="button" class="ajax-link listbutton original_screen_action_delete" data-class="sample_note_original_management" data-function="delete_confirm" data-id="{$row.id}" aria-label="削除">
                        <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                    </button>
                    <button type="button" class="ajax-link listbutton original_screen_action_edit" data-class="sample_note_original_management" data-function="edit_dialog" data-id="{$row.id}" aria-label="編集">
                        <span class="material-symbols-outlined" aria-hidden="true">edit_square</span>
                    </button>
                </td>
            </tr>
        {/foreach}
        {if count($rows) === 0}
            <tr>
                <td colspan="4" class="original_screen_empty_row">データはありません。</td>
            </tr>
        {/if}
    </tbody>
    </table>
    </div>
</div>
{if $is_last == false}
    <div class="ajax-auto" data-class="sample_note_original_management" data-function="rows_more" data-max="{$max}">{$max}</div>
{/if}
