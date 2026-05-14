# Original Screen child side panel

Use this when a child note opened from a parent note needs behavior beyond Standard Screen `list_on_side`.

## Dispatch rule

- Main management screen: `<tb_name>_original_management::run(Controller $ctl)`
- Child side panel: `<tb_name>_original_management::rows_child(Controller $ctl)`
- `db_exe::rows_child()` delegates only when the child note has `screen_build_type=Original Screen` and the original management class has public `rows_child()`.
- If `rows_child()` is missing, the framework falls back to Standard Screen side panel.

## POST contract

`rows_child()` receives the same minimum POST values as Standard Screen side panel:

```php
[
    "db_id" => "<child db id>",
    "parent_id" => "<parent row id>",
]
```

For reload support, keep these values in forms/buttons and use a stable side-area wrapper in the template.

## Samples

- Search/table side panel: `assets/sample_child_search_original_management/`
- Manual-sort side panel: `assets/sample_child_sort_original_management/`

Copy the sample class and templates into:

```text
classes/app/<tb_name>_original_management/
classes/app/<tb_name>_original_management/Templates/
```

Then rename class names, table names, template IDs, field names, and dialog function names to match the target note.
