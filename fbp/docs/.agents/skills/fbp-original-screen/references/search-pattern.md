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

For compact operational screens, use up to 5 fields per row. In responsive mode,
reduce to 4 / 3 / 2 columns as space narrows, then **one column at 700px and below**.
Keep desktop mode in its desktop layout.

Use the maintained [responsive_style.tpl](../assets/sample_note_original_management/Templates/responsive_style.tpl)
with the `original_screen_responsive_page` marker instead of copying a second set of
breakpoints here. It scopes the search grid and list cards to the same page and gates
mobile rules on `body.fbp-standard-responsive`. CSS inside the tpl is wrapped in
`{literal}`. See [responsive-layout.md](responsive-layout.md) for integration and checks.

- Wrap each search field in `.search_form_item`; use `.search_date_range` or
  `.search_datetime_range` for range pairs so both ends stack on mobile.
- Keep the hidden `.search_right` outside the grid form so it does not consume a slot.
- Preserve `screen_fields(search)` order when the fields should match `db_exe`.
- If a required default is needed, set it in the server-side filter before rendering.
