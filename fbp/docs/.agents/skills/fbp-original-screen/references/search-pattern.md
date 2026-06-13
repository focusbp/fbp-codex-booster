# Original Screen Search Pattern

Use this pattern when an Original Screen needs a `db_exe`-like search area.

## Auto Search DOM

Use the same structure as `db_exe` so framework JS can bind auto-submit:

```smarty
<div class="search_box">
    <div class="original_search_panel_body">
        <div class="search_left">
            <form id="example_filter_form" class="search_form_flex">
                {fields_form_direct field_group="search_field_group" data=$filter item_margin_top="0px"}
            </form>
        </div>
        <div class="search_right" style="display:none;">
            <button type="button"
                    class="ajax-link"
                    data-class="example_original_management"
                    data-function="apply_filter"
                    data-form="example_filter_form">Search</button>
        </div>
    </div>
</div>
```

- Do not show visible `検索` / `解除` buttons when auto search is the intended UX.
- `bind_search_box_auto_submit()` looks for `.search_box`, `form.search_form_flex`, and `.search_right button.ajax-link`.
- Text inputs and textareas auto-submit after a short delay; selects submit immediately.
- `apply_filter()` should reload only the list area, for example `reload_area($list_area, "list_area.tpl")`.
- Required search values should return `res_error_message()` and immediately `return`.
- If a visible `検索` button is used instead of hidden auto search, keep it in `.search_right` and right-align it with flex. Set the button to `float:none !important;` because shared button CSS may float buttons.

## Five-Column Field Layout

For compact operational screens, search items should wrap after 5 items per row unless the design needs a different density.

```smarty
<style>
    .example-page #example_filter_form {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px 12px;
        align-items: end;
    }
    .example-page #example_filter_form > [class^="field_"] {
        min-width: 0;
    }
    .example-page #example_filter_form .field_edit input,
    .example-page #example_filter_form .field_edit select {
        box-sizing: border-box;
        max-width: 100%;
        width: 100%;
    }
    .example-page .original_search_panel_body {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }
    .example-page .search_right {
        display: flex;
        justify-content: flex-end;
        align-items: flex-end;
        flex: 0 0 auto;
        margin-left: auto;
        min-width: 0;
    }
    .example-page .search_right .button_link {
        float: none !important;
        white-space: nowrap;
    }
    @media (max-width: 1280px) {
        .example-page #example_filter_form {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    @media (max-width: 1024px) {
        .example-page #example_filter_form {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 760px) {
        .example-page #example_filter_form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 520px) {
        .example-page #example_filter_form {
            grid-template-columns: 1fr;
        }
        .example-page .original_search_panel_body {
            flex-direction: column;
            align-items: stretch;
        }
        .example-page .search_right {
            width: 100%;
        }
        .example-page .search_right .button_link {
            width: 100%;
        }
    }
</style>
```

- Keep the hidden `.search_right` outside the grid form so it does not consume one of the 5 slots.
- Preserve `screen_fields(search)` order when the search fields are expected to match `db_exe`.
- If a required default is needed, set it in the server-side default filter before rendering.
