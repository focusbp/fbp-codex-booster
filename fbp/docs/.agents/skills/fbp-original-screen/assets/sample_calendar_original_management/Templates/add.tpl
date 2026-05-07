<form id="sample_calendar_original_management_add_form">
    {fields_form_direct db="sample_schedule" fields=$form_fields data=$row item_margin_top="10px"}

    <p class="error_message error_title"></p>
    <p class="error_message error_datetime"></p>
    <p class="error_message error_duration"></p>
    <p class="error_message error_travel_before"></p>
    <p class="error_message error_travel_after"></p>

    <div style="margin-top:18px;text-align:right;">
        <button type="button" class="ajax-link button_link" data-class="sample_calendar_original_management" data-function="add_save" data-form="sample_calendar_original_management_add_form">保存</button>
    </div>
</form>
