<div class="original_screen_page">
    <style>
        .original_screen_page #sample_note_original_management_filter_form {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px 12px;
            align-items: end;
        }
        .original_screen_page #sample_note_original_management_filter_form > .search_form_item {
            min-width: 0;
        }
        .original_screen_page #sample_note_original_management_filter_form .field_edit input,
        .original_screen_page #sample_note_original_management_filter_form .field_edit select {
            box-sizing: border-box;
            max-width: 100%;
            width: 100%;
        }
        .original_screen_page .original_search_panel_body {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        .original_screen_page .search_right {
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            flex: 0 0 auto;
            margin-left: auto;
            min-width: 0;
        }
        .original_screen_page .search_right .button_link {
            float: none !important;
            min-width: 82px;
            white-space: nowrap;
        }
        .original_screen_page .original_screen_table {
            width: 100%;
        }
        .original_screen_page .original_screen_action_cell {
            display: table-cell;
            text-align: right;
            white-space: nowrap;
            vertical-align: top;
        }
        .original_screen_page .original_screen_action_cell .listbutton {
            float: right;
            margin: 0 0 0 6px;
        }
        @media (max-width: 1280px) {
            .original_screen_page #sample_note_original_management_filter_form {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (max-width: 1024px) {
            .original_screen_page #sample_note_original_management_filter_form {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 760px) {
            .original_screen_page #sample_note_original_management_filter_form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 520px) {
            .original_screen_page #sample_note_original_management_filter_form {
                grid-template-columns: 1fr;
            }
            .original_screen_page .original_search_panel_body {
                flex-direction: column;
                align-items: stretch;
            }
            .original_screen_page .search_right {
                width: 100%;
            }
            .original_screen_page .search_right .button_link {
                width: 100%;
            }
            .original_screen_page .original_screen_action_cell .listbutton {
                float: none !important;
                margin: 0;
            }
        }
    </style>

    <div class="original_screen_toolbar original_screen_toolbar_end">
        <button type="button" class="ajax-link button_link" data-class="sample_note_original_management" data-function="add_dialog">追加</button>
    </div>

    <div class="search_box original_search_panel">
        <p class="original_search_panel_title">検索条件</p>
        <div class="original_search_panel_body">
            <div class="search_left">
                <form id="sample_note_original_management_filter_form" class="search_form_flex">
                    <div class="search_form_item field_type_dropdown">
                        {fields_form_original name="status" type="dropdown" value=$filter.status options_arr=$status_options title="ステータス" item_margin_top="0px"}
                    </div>
                    <div class="search_form_item field_type_text">
                        {fields_form_original name="keyword" type="text" value=$filter.keyword title="キーワード" item_margin_top="0px"}
                    </div>
                </form>
            </div>
            <div class="search_right" style="display:none;">
                <button type="button" class="ajax-link" data-class="sample_note_original_management" data-function="apply_filter" data-form="sample_note_original_management_filter_form">Search</button>
            </div>
        </div>
    </div>

    {include file="list_area.tpl"}
</div>
