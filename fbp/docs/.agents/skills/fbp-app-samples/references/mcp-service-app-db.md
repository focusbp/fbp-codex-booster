# MCP Service App DB

The sample uses two `service_...` tables and two constant arrays.

Use `screen_build_type = 0` for both tables. `service_item` is hidden from the menu because MCP owns the primary item workflow.

## Constant Arrays

### service_member_status

| Key | Label | Usage |
| --- | --- | --- |
| `1` | Active | Member can login |
| `2` | Disabled | Member cannot login |

### service_item_status

| Key | Label | Usage |
| --- | --- | --- |
| `1` | Active | Visible item |
| `2` | Archived | Archived item |

## service_member

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `display_name` | Display Name | text | yes | Label used in MCP authorization |
| `email` | Email | text | no | Login ID for public/MCP login |
| `password_hash` | Password Hash | text | no | Stored with `password_hash()` |
| `status` | Status | dropdown | yes | `service_member_status` |
| `subject_type` | Subject Type | text | no | Usually `service_member` |
| `subject_id` | Subject ID | number | no | Set to member ID after registration |
| `fbp_user_id` | FBP User ID | number | no | Optional admin user link |
| `mcp_last_login_at` | MCP Last Login At | datetime | no | Updated by MCP login |
| `created_at` | Created At | datetime | no | Set by registration/seed |
| `updated_at` | Updated At | datetime | no | Set by registration/login |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `display_name`, `email`, `status`, `mcp_last_login_at`, `created_at` |
| search | `display_name`, `email`, `status` |
| add | `display_name`, `email`, `status` |
| edit | `display_name`, `email`, `status`, `subject_type`, `subject_id`, `fbp_user_id` |
| delete | `display_name`, `email`, `status` |

## service_item

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `parent_id` | Member ID | number | yes | Owner `service_member.id` |
| `title` | Title | text | yes | Item title |
| `body` | Body | textarea | no | Item detail |
| `status` | Status | dropdown | yes | `service_item_status` |
| `created_at` | Created At | datetime | no | Set by MCP action/seed |
| `updated_at` | Updated At | datetime | no | Set by MCP action/seed |

Screen fields:

| Screen | Fields |
| --- | --- |
| list | `title`, `status`, `updated_at` |
| search | `title`, `status` |
| add | `parent_id`, `title`, `body`, `status` |
| edit | `parent_id`, `title`, `body`, `status` |
| delete | `title`, `status` |

## MCP Server

| Setting | Value |
| --- | --- |
| server_key | `mcp_service` |
| title | `MCP Service Sample Server` |
| auth_mode | `oauth2` |
| subject_type | `custom` |
| subject_provider_class | `mcp_service_subject_provider` |

## MCP Tool

| Setting | Value |
| --- | --- |
| tool_name | `service_items` |
| tool_type | `app_action` |
| action_class | `mcp_service_action` |
| operations | `list`, `create_item`, `update_item`, `delete_item` |
