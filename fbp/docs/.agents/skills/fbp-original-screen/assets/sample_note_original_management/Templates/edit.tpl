<form id="sample_note_original_management_edit_form" class="stack_form">
    <input type="hidden" name="id" value="{$row.id|escape}">
    {fields_form_original name="title" type="text" value=$row.title title="題名" item_margin_top="10px"}
    <p class="error_message error_title"></p>
    {fields_form_original name="status" type="dropdown" value=$row.status options_arr=$status_options title="ステータス" item_margin_top="10px"}
    <p class="error_message error_status"></p>
    {fields_form_original name="detail" type="textarea" value=$row.detail title="詳細" item_margin_top="10px"}
    <p class="error_message error_detail"></p>
    <div class="button_row button_row_end">
        <button type="button" class="ajax-link button_link" data-class="sample_note_original_management" data-function="edit_save" data-form="sample_note_original_management_edit_form">更新</button>
    </div>
</form>
