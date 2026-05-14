# LINE Bot Basic DB Structure

Reusable DB/note design for the LINE Bot Basic sample.

## member_type_opt

LINE member role options.

| Key | Value | Purpose |
| --- | --- | --- |
| `0` | `User` | Normal LINE member |
| `1` | `Manager` | Receives manager-forwarded messages |

## line_member

LINE-linked member.

- `id`
- `userid`: LINE user ID
- `line_name`: LINE display name
- `name`: editable member name
- `member_type`: dropdown using `member_type_opt`

## Design Notes

- `userid`, `line_name`, and `name` are the standard framework field names for
  LINE member resolution.
- Standard `webhook_line` creates missing members with `userid`, `line_name`,
  `name`, and `member_type=0`.
- `member_type=1` is used by framework manager forwarding. It is not a business
  membership status.
- Add project-specific profile fields only after the basic webhook flow is
  verified.
