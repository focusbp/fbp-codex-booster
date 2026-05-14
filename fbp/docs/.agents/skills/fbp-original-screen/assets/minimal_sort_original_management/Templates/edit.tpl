<div class="original_screen_dialog_panel">
    <form id="minimal_sort_original_management_edit_form">
        <input type="hidden" name="id" value="{$row.id|escape}">
        {fields_form_direct db="sample_sort_master" fields="title,note" data=$row item_margin_top="10px"}
        <p class="error_message error_title"></p>
    </form>
    <div class="original_screen_dialog_actions">
        <button type="button" class="ajax-link button_link" data-class="minimal_sort_original_management" data-function="edit_save" data-form="minimal_sort_original_management_edit_form">保存</button>
    </div>
</div>
