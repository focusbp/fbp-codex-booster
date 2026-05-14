---
name: fbp-customer-demo
description: Use when creating the default FBP customer management demo/sample by copying bundled CRUD/CSV/PDF assets, loading the fixed JSON manifest, and verifying with FBP CLI.
---

# fbp-customer-demo

Use this skill when the user asks for a customer management demo, customer CRUD
sample, CRM sample, or an easy first FBP Codex Booster demo.

## Primary Path

Do not design this demo from scratch. Use the bundled installer and assets.

From the FBP project root, run:

```bash
php fbp/docs/.agents/skills/fbp-customer-demo/scripts/install_customer_demo.php
```

The installer:

- Copies `assets/customer-management/classes/app/customers_*` into `classes/app/`.
- Loads `assets/customer-management/customer-demo.json`.
- Creates/updates `customer_status`, `customers`, DB fields, and seed rows.
- Removes stale fields such as old `customer_code` from the demo table.
- Resets `classes/data/common/customers*` by default, then inserts the fixed seed rows.

Use `--keep-data` only when the user explicitly wants to preserve existing
`customers` data. Use `--root=/path/to/project` when running outside the repo
root.

## Companion Skills

Read these only if the installer cannot be used or a stage needs manual repair:

- `fbp-db` for DB/note definitions and data commands
- `fbp-original-screen` for the CRUD management screen
- `fbp-csv-media` for CSV export/import
- `fbp-pdf` for PDF output
- `fbp-cli` for verification commands

## Fixed Names

- Table/note name: `customers`
- CRUD class: `customers_original_management`
- CRUD entry function: `run`
- CSV class: `customers_csv`
- CSV functions: `download`, `upload_form`, `upload_exe`
- PDF class: `customers_pdf`
- PDF functions: `list_pdf`, `detail_pdf`
- Constant array: `customer_status`

## Fixed Fields

Use exactly these default fields. `id` is the built-in FBP record ID and is
shown in list, CSV, and PDF; do not create `customer_code`.

| Field | Label | Type | Required | Usage |
| --- | --- | --- | --- | --- |
| `id` | ID | built-in | yes | list, search, CSV, PDF |
| `company_name` | Company Name | text | yes | list, search, form, CSV, PDF |
| `contact_name` | Contact Name | text | no | list, search, form, CSV, PDF |
| `email` | Email | text | no | list, search, form, CSV, PDF |
| `phone` | Phone | text | no | list, search, form, CSV, PDF |
| `postal_code` | Postal Code | text | no | form, CSV, detail PDF |
| `address` | Address | textarea | no | form, CSV, detail PDF |
| `status` | Status | dropdown | yes | list, search, form, CSV, PDF |
| `memo` | Memo | textarea | no | form, CSV, detail PDF |
| `created_at` | Created At | datetime | no | detail/PDF, set on insert |
| `updated_at` | Updated At | datetime | no | detail/PDF, set on insert/update |

For `email`, set email format validation when supported. For `status`, default
to `prospect`.

## Fixed Status Options

Use `customer_status` with string keys:

| Value | Label |
| --- | --- |
| `prospect` | Prospect |
| `active` | Active |
| `inactive` | Inactive |

Do not invent extra statuses for the default demo.

## Fixed CSV Columns

CSV import/export must use this column order:

```text
id,company_name,contact_name,email,phone,postal_code,address,status,memo
```

Rules:

- Export UTF-8 by default.
- Import updates an existing row when `id` exists.
- Import inserts a new row when `id` is empty or does not match an existing row.
- Validate required `company_name` and `status`.
- Validate `status` against `prospect`, `active`, `inactive`.
- Return `res_error_message()` and immediately `return` on validation errors.

## Fixed PDF Output

Create two PDF flows:

- Customer list PDF: `id`, `company_name`, `contact_name`, `email`, `phone`, `status`
- Customer detail PDF: all customer fields including `id`

PDF download buttons must use `download-link`, not `ajax-link`.

## Seed Data

The installer inserts these three demo records with generated FBP IDs:

| company_name | contact_name | email | phone | postal_code | address | status | memo |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Acme Trading | Alice Johnson | alice@example.com | `03-0000-0001` | `100-0001` | Tokyo | active | Key account |
| Blue River Co. | Bob Smith | bob@example.com | `06-0000-0002` | `530-0001` | Osaka | prospect | Needs follow-up |
| Green Field LLC | Carol Lee | carol@example.com | `052-0000-0003` | `460-0001` | Nagoya | inactive | Past customer |

## Stability Guardrails

- `customer_status` must preserve string keys. If needed, fix framework support
  first: `constant_values.key` must be `T`, `customer_status` must not require
  an `_opt` suffix, and normal dropdown fmt must be `T`.
- Only table dropdowns such as `table/...` should store numeric IDs. Verify
  `classes/data/_common/fmt/customers.fmt` contains `status,24,T`.
- CLI `app_call output_file` must respect `$ctl->stop_res`; CSV/PDF files must
  not have framework JSON appended.
- Form buttons inside the same `<form>` must not set `data-form`; use
  `<button class="ajax-link" invoke-function="...">`.
- `edit.tpl` must include hidden `id`.
- Search filters must only apply when `_customers_search=1`; add/edit/delete
  reloads should show the unfiltered list unless search state is explicitly
  stored.
- The list UI should follow Original Screen classes:
  `original_screen_page`, `original_screen_toolbar original_screen_toolbar_end`,
  `search_box original_search_panel`, `original_screen_table`, `row_style`,
  `row_title`, `row_value`, `original_screen_action_cell`, and `listbutton`.
- CSV upload uses `fields_form_original name="file" type="file"` and allows
  `is_file()` only during `CLI_APP_CALL`.
- PDF buttons use `download-link`, `data-class="customers_pdf"`, and
  `data-open_new_tab="true"`.

## Verification

Run these after the installer:

```bash
php fbp/cli.php app_call --json='{"class":"customers_original_management","function":"run"}'
php fbp/cli.php app_check --json='{"class":"customers_original_management","function":"run","checks":[{"path":"response_json.work_area.html","contains":"original_screen_toolbar original_screen_toolbar_end","label":"toolbar"},{"path":"response_json.work_area.html","contains":"search_box original_search_panel","label":"search panel"},{"path":"response_json.work_area.html","contains":"original_screen_table","label":"list table"},{"path":"response_json.work_area.html","contains":"Customers: 3","label":"seed count"}]}'
php fbp/cli.php data_list --json='{"table":"customers","max":20}'
php fbp/cli.php app_call --json='{"class":"customers_csv","function":"download","output_file":"/tmp/customers.csv"}'
php fbp/cli.php app_call --json='{"class":"customers_pdf","function":"list_pdf","output_file":"/tmp/customers.pdf"}'
```

Check:

- `classes/data/_common/fmt/customers.fmt` has `status,24,T`.
- `data_list` stores statuses as `active`, `prospect`, `inactive`.
- The list HTML contains `original_screen_toolbar original_screen_toolbar_end`,
  `search_box original_search_panel`, and `original_screen_table`.
- Edit Save does not return `Customer not found.` and the list still shows the
  three seed rows.
- `/tmp/customers.csv` starts with the fixed CSV header and has no JSON mixed in.
- `/tmp/customers.pdf` starts with `%PDF-`; use `pdftotext` if available.

If a check fails, fix the failing stage before adding new behavior.
