<div style="padding:16px;">
    <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:12px;">
        <button type="button" class="ajax-link button_link" data-class="sample_note_original_management" data-function="add_dialog">追加</button>
    </div>

    <div class="search_box" style="margin:8px 0 14px 0;padding:25px 14px 5px 14px;border:1px solid #d7deea;border-radius:0px;background:#f8fafc;position: relative;">
        <p style="line-height:1.2;font-weight:bold;color:#334155;font-size:12px;position:absolute;top:7px;left:18px;">検索条件</p>
        <div style="display:flex;flex-direction:column;justify-content:center;width:100%;">
            <div class="search_left">
                <form id="sample_note_original_management_filter_form" class="search_form_flex">
                    <div class="search_form_item field_type_dropdown">
                        {fields_form_original name="status" type="dropdown" value=$filter.status options_arr=$status_options title="ステータス" item_margin_top="0px"}
                    </div>
                    <div class="search_form_item field_type_text">
                        {fields_form_original name="keyword" type="text" value=$filter.keyword title="キーワード" item_margin_top="0px"}
                    </div>
                    <button type="button" class="ajax-link" data-class="sample_note_original_management" data-function="apply_filter" data-form="sample_note_original_management_filter_form" style="display:none;" id="sample_note_original_management_filter_trigger"></button>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function ($) {
        var timer = null;
        $(document).off("change.sampleNoteFilter", "#sample_note_original_management_filter_form select");
        $(document).on("change.sampleNoteFilter", "#sample_note_original_management_filter_form select", function () {
            $("#sample_note_original_management_filter_trigger").trigger("click");
        });
        $(document).off("input.sampleNoteFilter", "#sample_note_original_management_filter_form input[name='keyword']");
        $(document).on("input.sampleNoteFilter", "#sample_note_original_management_filter_form input[name='keyword']", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                $("#sample_note_original_management_filter_trigger").trigger("click");
            }, 300);
        });
    })(jQuery);
    </script>

    {include file="list_area.tpl"}
</div>
