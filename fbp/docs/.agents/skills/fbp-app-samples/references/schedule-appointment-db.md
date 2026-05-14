# Schedule Appointment DB

## Notes

This sample uses one new note/table.

### `schedule_appointment_slots`

Purpose: stores both admin-created appointment availability and the public booking result.

Recommended table settings:

- `screen_build_type`: `Original Screen`
- `list_type`: `Search and Table`
- `show_menu`: `1`
- `list_width`: `1200`
- `edit_width`: `760`
- `sortkey`: `starts_at`
- `sort_order`: descending date order in DB config, with screen-level sorting handled by the Original Screen.

Fields:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `user_id` | dropdown `table/user` | yes | Login user who owns the public URL and slots. |
| `title` | text | yes | Internal/public slot label. |
| `starts_at` | datetime | yes | Slot start timestamp. |
| `duration_minutes` | number | yes | Slot length. Public calendar assumes 30-minute grid rows, but longer slots are allowed. |
| `status` | dropdown `schedule_appointment_status` | yes | `available`, `booked`, `blocked`, `cancelled`. |
| `customer_name` | text | no | Filled by public booking. |
| `customer_email` | text | no | Filled by public booking. |
| `customer_phone` | text | no | Filled by public booking. |
| `customer_message` | textarea | no | Filled by public booking. |
| `booked_at` | datetime | no | Filled when public booking succeeds or admin marks a slot booked. |
| `internal_note` | textarea | no | Admin-only memo. |

## Constant Arrays

### `schedule_appointment_status`

| Key | Value | Use |
| --- | --- | --- |
| `available` | Available | Publicly selectable. |
| `booked` | Booked | Reserved by public booking or admin. |
| `blocked` | Blocked | Admin-held time, not public. |
| `cancelled` | Cancelled | Historical/cancelled slot, not public. |

## Data Rules

- Admin create/edit always sets `user_id` from the current login session.
- Public URLs carry encrypted `user.id`, never a plain user ID.
- Public booking only updates future rows where `status = available`.
- Public booking does not create a second table; it updates the selected slot row.
- Public pages do not expose slots for other users.
