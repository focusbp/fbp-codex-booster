# Service User Management DB

The sample uses five `service_...` tables and five constant arrays.

Use `screen_build_type = 0` for these tables. The sample intentionally uses the framework Standard Screen for minimum admin management. The manifest registers `screen_fields` for `list`, `search`, `add`, `edit`, and `delete`.

The installer registers one `db_additionals` top button on `service_member`:

| Button | Class | Function | Purpose |
| --- | --- | --- | --- |
| `Public Service Link` | `service_public_link_dialog` | `run` | Shows the public service URL in a dialog |

## Constant Arrays

### service_member_status

| Key | Label | Usage |
| --- | --- | --- |
| `active` | Active | Member can login |
| `disabled` | Disabled | Member cannot login |

### service_plan_status

| Key | Label | Usage |
| --- | --- | --- |
| `active` | Active | Publicly selectable |
| `draft` | Draft | Hidden from public pages |
| `closed` | Closed | Hidden from public pages |

### service_subscription_status

| Key | Label | Usage |
| --- | --- | --- |
| `active` | Active | Current service access |
| `cancelled` | Cancelled | Stopped by change/cancellation |
| `expired` | Expired | Reserved for batch expiration |

### service_payment_status

| Key | Label | Usage |
| --- | --- | --- |
| `paid` | Paid | Square payment succeeded |
| `failed` | Failed | Reserved for failure records |
| `refunded` | Refunded | Reserved for refund tracking |

### service_billing_cycle

| Key | Label | Usage |
| --- | --- | --- |
| `month` | Monthly | Monthly service period |
| `once` | One Time | One-time activation or trial |

## service_member

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `name` | Name | text | yes | Public service member name |
| `email` | Email | text | yes | Login ID, `format_check = email` |
| `password_hash` | Password Hash | text | no | Stored with `password_hash()` |
| `status` | Status | dropdown | yes | `service_member_status` |
| `square_customer_id` | Square Customer ID | text | no | Reused for later payments |
| `square_card_id` | Square Card ID | text | no | Last registered card ID |
| `created_at` | Created At | datetime | no | Set by public registration |
| `updated_at` | Updated At | datetime | no | Updated by registration/payment |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `name`, `email`, `status`, `created_at` |
| search | `name`, `email`, `status` |
| add | `name`, `email`, `status` |
| edit | `name`, `email`, `status`, `square_customer_id`, `square_card_id` |
| delete | `name`, `email`, `status` |

## service_plan

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `name` | Name | text | yes | Public plan name |
| `description` | Description | textarea | no | Public plan description |
| `price` | Price | number | yes | Integer yen; `0` creates a free subscription |
| `billing_cycle` | Billing Cycle | dropdown | yes | `service_billing_cycle` |
| `status` | Status | dropdown | yes | `service_plan_status` |
| `sort` | Sort | number | yes | Public/admin ordering |
| `created_at` | Created At | datetime | no | Seed/public code value |
| `updated_at` | Updated At | datetime | no | Seed/public code value |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `name`, `price`, `billing_cycle`, `status`, `sort` |
| search | `name`, `status` |
| add | `name`, `description`, `price`, `billing_cycle`, `status`, `sort` |
| edit | `name`, `description`, `price`, `billing_cycle`, `status`, `sort` |
| delete | `name`, `price`, `billing_cycle`, `status` |

## service_subscription

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `service_member_id` | Member | dropdown | yes | `table/service_member` |
| `service_plan_id` | Plan | dropdown | yes | `table/service_plan` |
| `status` | Status | dropdown | yes | `service_subscription_status` |
| `current_period_start` | Current Period Start | datetime | yes | Access start |
| `current_period_end` | Current Period End | datetime | yes | Access end |
| `square_customer_id` | Square Customer ID | text | no | Copied from payment |
| `square_card_id` | Square Card ID | text | no | Copied from payment |
| `cancelled_at` | Cancelled At | datetime | no | Set when superseded/cancelled |
| `created_at` | Created At | datetime | no | Set after payment |
| `updated_at` | Updated At | datetime | no | Set after payment/cancel |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `service_member_id`, `service_plan_id`, `status`, `current_period_start`, `current_period_end` |
| search | `service_member_id`, `service_plan_id`, `status` |
| add | `service_member_id`, `service_plan_id`, `status`, `current_period_start`, `current_period_end` |
| edit | `service_member_id`, `service_plan_id`, `status`, `current_period_start`, `current_period_end`, `cancelled_at` |
| delete | `service_member_id`, `service_plan_id`, `status` |

## service_payment

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `service_member_id` | Member | dropdown | yes | `table/service_member` |
| `service_subscription_id` | Subscription | dropdown | yes | `table/service_subscription` |
| `service_plan_id` | Plan | dropdown | yes | `table/service_plan` |
| `amount` | Amount | number | yes | Integer yen |
| `payment_status` | Payment Status | dropdown | yes | `service_payment_status` |
| `square_customer_id` | Square Customer ID | text | no | Copied from payment |
| `square_card_id` | Square Card ID | text | no | Copied from payment |
| `paid_at` | Paid At | datetime | no | Set after payment |
| `created_at` | Created At | datetime | no | Set after payment |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `service_member_id`, `service_plan_id`, `amount`, `payment_status`, `paid_at` |
| search | `service_member_id`, `service_plan_id`, `payment_status` |
| add | `service_member_id`, `service_subscription_id`, `service_plan_id`, `amount`, `payment_status`, `paid_at` |
| edit | `service_member_id`, `service_subscription_id`, `service_plan_id`, `amount`, `payment_status`, `paid_at`, `square_customer_id`, `square_card_id` |
| delete | `service_member_id`, `service_plan_id`, `amount`, `payment_status` |

## service_password_reset

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `service_member_id` | Member | dropdown | yes | `table/service_member` |
| `token` | Token | text | yes | Random reset token |
| `expires_at` | Expires At | datetime | yes | Sample uses one hour |
| `used_at` | Used At | datetime | no | Set after successful reset |
| `created_at` | Created At | datetime | no | Set when requested |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `service_member_id`, `expires_at`, `used_at`, `created_at` |
| search | `service_member_id`, `token` |
| add | `service_member_id`, `token`, `expires_at` |
| edit | `service_member_id`, `token`, `expires_at`, `used_at` |
| delete | `service_member_id`, `expires_at`, `used_at` |
