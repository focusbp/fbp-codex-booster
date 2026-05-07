<div id="minimal_note_original_management_list_area">
    <table style="width:100%;">
        <tbody>
            {foreach from=$rows item=row}
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #ddd;">{$row.id}</td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;">{$row.title|escape}</td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;">{fields_view_direct db="sample_note" fields="status" data=$row}</td>
                    <td style="padding:8px;border-bottom:1px solid #ddd;text-align:right;">
                        <button type="button" class="ajax-link" data-class="minimal_note_original_management" data-function="edit_dialog" data-id="{$row.id}">編集</button>
                        <button type="button" class="ajax-link" data-class="minimal_note_original_management" data-function="delete_dialog" data-id="{$row.id}">削除</button>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
