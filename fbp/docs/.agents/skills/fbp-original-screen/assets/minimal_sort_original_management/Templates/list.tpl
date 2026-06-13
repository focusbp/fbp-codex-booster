<div class="original_screen_page">
    <style>
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
        @media (max-width: 520px) {
            .original_screen_page .original_screen_action_cell .listbutton {
                float: none !important;
                margin: 0;
            }
        }
    </style>
    <div class="original_screen_toolbar original_screen_toolbar_end">
        <button type="button" class="ajax-link button_link" data-class="minimal_sort_original_management" data-function="add_dialog">追加</button>
    </div>

    {include file="list_area.tpl"}
</div>
