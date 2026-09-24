{* Copy with list.tpl/list_area.tpl; include once outside the Ajax list area. *}
<style>
{literal}
.original_screen_responsive_page .search_form_flex {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px 12px;
    align-items: end;
}
.original_screen_responsive_page .search_form_item {
    width: auto;
    min-width: 0;
}
.original_screen_responsive_page .field_edit,
.original_screen_responsive_page .fbp-original-select-wrap {
    min-width: 0;
    max-width: 100%;
}
.original_screen_responsive_page .original_search_panel_body {
    display: flex;
    flex-direction: row;
    gap: 12px;
    align-items: flex-end;
}
.original_screen_responsive_page .search_left {
    flex: 1;
    min-width: 0;
}
.original_screen_responsive_page .search_right {
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    flex: 0 0 auto;
    margin-left: auto;
    min-width: 0;
}
.original_screen_responsive_page .search_right button {
    float: none !important;
}
.original_screen_responsive_page .original_screen_list_scroll {
    max-width: 100%;
    overflow-x: auto;
}
.original_screen_responsive_page .original_screen_table {
    width: 100%;
}
.original_screen_responsive_page .original_screen_action_cell {
    display: table-cell;
    text-align: right;
    vertical-align: top;
}
.original_screen_responsive_page .original_screen_action_cell .listbutton {
    float: right;
    margin: 0 0 0 6px;
}
@media (max-width: 1280px) {
    body.fbp-standard-responsive .original_screen_responsive_page .search_form_flex {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
@media (max-width: 1024px) {
    body.fbp-standard-responsive .original_screen_responsive_page .search_form_flex {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 760px) {
    body.fbp-standard-responsive .original_screen_responsive_page .search_form_flex {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 700px) {
    body.fbp-standard-responsive:has(#work_area .original_screen_responsive_page) {
        min-width: 0;
    }
    body.fbp-standard-responsive:has(#work_area .original_screen_responsive_page) .content {
        display: block;
        width: 100%;
        min-width: 0;
        padding: 10px;
    }
    body.fbp-standard-responsive #work_area:has(.original_screen_responsive_page) {
        min-width: 0;
        max-width: 100%;
    }
    body.fbp-standard-responsive .original_screen_responsive_page {
        min-width: 0;
        padding: 0;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .search_form_flex {
        grid-template-columns: minmax(0, 1fr);
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_search_panel_body,
    body.fbp-standard-responsive .original_screen_responsive_page .search_date_range,
    body.fbp-standard-responsive .original_screen_responsive_page .search_datetime_range {
        flex-direction: column;
        align-items: stretch !important;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .search_right {
        width: 100%;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_list_scroll {
        overflow-x: visible;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table,
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody,
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr {
        display: block;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > thead {
        display: none;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr {
        margin-bottom: 12px;
        border: 1px solid #d9e2ec;
        border-radius: 6px;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr > td {
        display: grid;
        grid-template-columns: 86px minmax(0, 1fr);
        align-items: start;
        gap: 10px;
        width: 100% !important;
        min-width: 0;
        max-width: none;
        padding: 10px;
        border: 0;
        border-top: 1px solid #edf2f7;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr > td:first-child {
        border-top: 0;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table .row_title {
        color: #64748b;
        font-weight: 700;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table .row_value {
        min-width: 0;
        line-height: 1.5;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table .row_value p {
        padding: 0;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .item_viewer_dropdown_badge {
        max-width: 100%;
        white-space: normal;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr > td.original_screen_action_cell {
        display: flex;
        flex-direction: row-reverse;
        flex-wrap: wrap;
        gap: 8px;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_action_cell .listbutton,
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_toolbar button {
        float: none !important;
        margin: 0;
        min-width: 38px;
        min-height: 38px;
        max-width: 100%;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    body.fbp-standard-responsive .original_screen_responsive_page .original_screen_table > tbody > tr > td.original_screen_empty_row {
        display: block;
    }
}
{/literal}
</style>
