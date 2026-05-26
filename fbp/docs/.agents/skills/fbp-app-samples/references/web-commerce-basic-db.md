# Web Commerce Basic DB

The sample uses six `shop_...` tables and five constant arrays.

Use `screen_build_type = 0` for these tables. The Web Commerce Basic sample intentionally uses the framework Standard Screen for admin management instead of shipping custom `shop_..._original_management` classes.

The installer also registers `screen_fields` for `list`, `search`, `add`, `edit`, and `delete`; child tables include `list_on_side`.

The installer registers one `db_additionals` top button on `shop_customer_order`:

| Button | Class | Function | Purpose |
| --- | --- | --- | --- |
| `Public EC Link` | `shop_public_link_dialog` | `run` | Shows the public storefront URL in a dialog |

## Constant Arrays

### shop_member_status

| Key | Label | Usage |
| --- | --- | --- |
| `active` | Active | Member can login |
| `disabled` | Disabled | Member cannot login |

### shop_product_status

| Key | Label | Usage |
| --- | --- | --- |
| `active` | Active | Publicly visible |
| `draft` | Draft | Hidden from public pages |
| `closed` | Closed | Hidden from public pages |

### shop_order_status

| Key | Label | Usage |
| --- | --- | --- |
| `paid` | Paid | Created after successful Square payment |
| `shipped` | Shipped | Admin shipment progress |
| `cancelled` | Cancelled | Admin cancellation |

### shop_payment_status

| Key | Label | Usage |
| --- | --- | --- |
| `paid` | Paid | Square payment succeeded |
| `failed` | Failed | Reserved for payment failure records |

### shop_active_status

| Key | Label | Usage |
| --- | --- | --- |
| `1` | Active | Category or variant is usable |
| `0` | Inactive | Hidden or unavailable |

## shop_member

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `name` | Name | text | yes | Public member name |
| `email` | Email | text | yes | Login ID, `format_check = email` |
| `password_hash` | Password Hash | text | no | Stored with `password_hash()` |
| `tel` | Phone | text | no | Used as checkout default |
| `zip` | ZIP | text | no | Used as checkout default |
| `address` | Address | text | no | Used as checkout default |
| `status` | Status | dropdown | yes | `shop_member_status` |
| `square_customer_id` | Square Customer ID | text | no | Reused for later payments |
| `square_card_id` | Square Card ID | text | no | Last registered card ID |
| `created_at` | Created At | datetime | no | Set by public registration |
| `updated_at` | Updated At | datetime | no | Updated by public registration/payment |

## shop_product_category

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `name` | Name | text | yes | Category label |
| `sort` | Sort | number | yes | Public/admin ordering |
| `is_active` | Active | dropdown | yes | `shop_active_status` |

## shop_product

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `category_id` | Category | dropdown | yes | `table/shop_product_category` |
| `name` | Name | text | yes | Product name |
| `catch_copy` | Catch Copy | text | no | Short product card text |
| `description` | Description | textarea | no | Product detail text |
| `tax_rate` | Tax Rate | number | yes | Percent used for cart tax calculation |
| `image_file` | Image | image | no | Public display uses framework image URL |
| `status` | Status | dropdown | yes | `shop_product_status` |
| `sort` | Sort | number | yes | Public/admin ordering |
| `created_at` | Created At | datetime | no | Seed/public code value |
| `updated_at` | Updated At | datetime | no | Seed/public code value |

## shop_product_variant

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `parent_id` | Product | dropdown | yes | Parent `shop_product` |
| `name` | Name | text | yes | Option label |
| `price` | Price | number | yes | Integer yen |
| `stock_quantity` | Stock Quantity | number | yes | MVP inventory |
| `sort` | Sort | number | yes | Display ordering |
| `is_active` | Active | dropdown | yes | `shop_active_status` |

## shop_customer_order

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `shop_member_id` | Member | dropdown | yes | `table/shop_member` |
| `order_status` | Order Status | dropdown | yes | `shop_order_status` |
| `payment_status` | Payment Status | dropdown | yes | `shop_payment_status` |
| `square_customer_id` | Square Customer ID | text | no | Copied from payment |
| `square_card_id` | Square Card ID | text | no | Copied from payment |
| `buyer_name` | Buyer Name | text | yes | Copied from checkout |
| `buyer_email` | Buyer Email | text | yes | Copied from checkout |
| `buyer_tel` | Buyer Phone | text | yes | Copied from checkout |
| `shipping_zip` | Shipping ZIP | text | no | Copied from checkout |
| `shipping_address` | Shipping Address | text | yes | Copied from checkout |
| `subtotal_amount` | Subtotal Amount | number | yes | Cart subtotal |
| `shipping_fee` | Shipping Fee | number | yes | Fixed sample fee |
| `tax_amount` | Tax Amount | number | yes | Calculated tax |
| `total_amount` | Total Amount | number | yes | Square payment amount |
| `ordered_at` | Ordered At | datetime | yes | Set after payment |
| `paid_at` | Paid At | datetime | no | Set after payment |
| `cancelled_at` | Cancelled At | datetime | no | Reserved for admin handling |
| `memo` | Memo | textarea | no | Checkout memo |
| `created_at` | Created At | datetime | no | Set after payment |
| `updated_at` | Updated At | datetime | no | Set after payment |

## shop_customer_order_item

| Field | Label | Type | Required | Notes |
| --- | --- | --- | --- | --- |
| `parent_id` | Order | dropdown | yes | Parent `shop_customer_order` |
| `sort` | Sort | number | yes | Line order |
| `product_id` | Product | dropdown | yes | Source product |
| `product_variant_id` | Product Variant | dropdown | yes | Source variant |
| `product_name` | Product Name | text | yes | Copied name |
| `variant_name` | Variant Name | text | yes | Copied option name |
| `unit_price` | Unit Price | number | yes | Copied unit price |
| `quantity` | Quantity | number | yes | Ordered quantity |
| `tax_rate` | Tax Rate | number | yes | Copied tax rate |
| `line_amount` | Line Amount | number | yes | `unit_price * quantity` |
