# Event Registration DB

The sample uses two tables and two constant arrays.

## Constant Arrays

### event_session_status

| Key | Label | Usage |
| --- | --- | --- |
| `open` | Open | Visible and available for registration on the public page |
| `closed` | Closed | Hidden from the public page |

### event_registration_status

| Key | Label | Usage |
| --- | --- | --- |
| `new` | New | Created from the public form |
| `confirmed` | Confirmed | Admin has confirmed the registration |
| `completed` | Completed | Registration is complete |
| `cancelled` | Cancelled | Excluded from active capacity counts |

## event_sessions

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `title` | Title | text | yes | Session title shown publicly |
| `starts_at` | Starts At | datetime | yes | Public page hides past sessions |
| `duration_minutes` | Duration Minutes | number | yes | Used to display the end time |
| `capacity` | Capacity | number | yes | Must be at least 1 |
| `status` | Status | dropdown | yes | `event_session_status` |
| `memo` | Memo | textarea | no | Shown publicly when present |

Recommended table settings:

- `screen_build_type = 1` for Original Screen.
- `dropdown_item_display_type = template`.
- `dropdown_item_template = {title}`.

## event_registrations

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `session_id` | Event Session | dropdown | yes | `table/event_sessions` with display template |
| `name` | Name | text | yes | Visitor name |
| `email` | Email | text | yes | `format_check = email` |
| `phone` | Phone | text | no | Visitor phone |
| `message` | Message | textarea | no | Visitor message |
| `status` | Status | dropdown | yes | `event_registration_status` |
| `created_at` | Created At | datetime | no | Set on insert |
| `updated_at` | Updated At | datetime | no | Set on insert/update |

Recommended table settings:

- `show_menu = 0`; registrations are managed from the session screen.
- `screen_build_type = 1` for Original Screen compatibility.

## Capacity Rule

Active registrations are all `event_registrations` records whose status is not `cancelled`.

```text
remaining = event_sessions.capacity - active registration count
```

The public page shows only sessions with `remaining > 0`.
