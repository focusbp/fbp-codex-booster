<form id="minimal_note_original_management_edit_form">
    <input type="hidden" name="id" value="{$row.id}">
    {fields_form_direct db="sample_note" fields='["title","detail","status"]' data=$row item_margin_top="10px"}
    <p class="error_message error_title"></p>
    <div style="margin-top:16px;text-align:right;">
        <button type="button" class="ajax-link button_link" data-class="minimal_note_original_management" data-function="edit_save" data-form="minimal_note_original_management_edit_form">更新</button>
    </div>
</form>
