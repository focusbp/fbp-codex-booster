# LINE-Entry Mall DB Structure

This is a reusable DB/note design. Do not include project-specific member classification, post-order workflow, external-app linkage, or SSO fields.

## line_member

LINE-linked public member.

- `id`
- `userid`: LINE user ID
- `line_name`: LINE display name
- `name`: member name
- `email`: optional contact email if needed by checkout/inquiry
- `buyer_name`
- `buyer_zip`
- `buyer_address`
- `buyer_tel`
- `shipping_name`
- `shipping_zip`
- `shipping_address`
- `shipping_tel`
- `same_as_buyer`
- `gift_memo`
- `square_customer_id`
- `square_card_id`
- `created_at`
- `updated_at`

## shop

Seller shop profile and shop-scoped payment/shipping settings.

- `id`
- `login_id`: links a normal admin login to one shop
- `status`: closed/open
- `shop_name`
- `company_name`
- `representative_name`
- `postal_code`
- `address`
- `tel`
- `email`
- `invoice_registration_number`
- `shipping_fee`: one flat fee per order
- `delivery_timing`
- `delivery_method`
- `return_policy`
- `shop_description`
- `square_location_id`
- `square_access_token`
- `square_refresh_token`
- `square_token_expires_at`
- `square_merchant_id`
- `created_at`
- `updated_at`

## product_category

Manual-sort mall category.

- `id`
- `name`
- `sort`
- `is_active`

## product

Shop-linked product.

- `id`
- `shop_id`
- `product_category_id`
- `product_type`
- `status`
- `name`
- `catch_copy`
- `description`
- `tax_rate`
- `unit_name`
- `order_memo_template`
- `after_payment_message`
- `purchase_limit_per_order`
- `event_date`
- `image_file_1`
- `image_file_2`
- `image_file_3`
- `reservation_note`
- `sort`
- `created_at`
- `updated_at`

## product_variant

Price/variant under a product.

- `id`
- `parent_id`: product ID
- `name`
- `price`
- `sales_limit`
- `sort`
- `is_active`

## customer_order

One order for one shop.

- `id`
- `shop_id`
- `line_member_id`
- `order_status`
- `payment_id`
- `square_payment_id`
- `buyer_name`
- `buyer_zip`
- `buyer_address`
- `buyer_tel`
- `buyer_email`
- `shipping_name`
- `shipping_zip`
- `shipping_address`
- `shipping_tel`
- `same_as_buyer`
- `gift_memo`
- `subtotal_amount`
- `shipping_fee`
- `tax_amount`
- `total_amount`
- `ordered_at`
- `paid_at`
- `shipped_at`
- `cancelled_at`
- `slip_no`
- `shipping_notice_sent_at`
- `seller_memo`
- `created_at`
- `updated_at`

## customer_order_item

Order line item. Copy product data at order time.

- `id`
- `parent_id`: customer_order ID
- `shop_id`
- `sort`
- `product_id`
- `product_variant_id`
- `product_type`
- `product_name`
- `variant_name`
- `unit_name`
- `unit_price`
- `quantity`
- `tax_rate`
- `line_amount`
- `order_memo`

## mall_inquiry

Public inquiry scoped to member/shop/order.

- `id`
- `line_member_id`
- `shop_id`
- `order_id`
- `name`
- `email`
- `tel`
- `message`
- `status`
- `created_at`
- `updated_at`

## Design Notes

- The public cart is single-shop only.
- Shipping fee is read from `shop.shipping_fee` and added once per order.
- Orders are created only after Square payment succeeds.
- Reservation and inquiry product orders can be created without Square payment when the app treats them as non-payment requests; leave `paid_at` empty and hide receipt actions for those orders.
- `shop.login_id` is enough for a simple seller scope: normal users see only their shop; admins see all shops.
- Product images are fixed fields on `product`, not a separate image table, when the UI expects up to three images.
- For seminar/event products, `event_date` is required and public product queries should hide the product from the day after the event date.
- For reservation and inquiry products, product forms can hide tax rate, unit, sales limit, and event date when those fields are not used by the checkout flow.
- `customer_order.shipped_at` means shipped datetime/fulfillment processing time, not arrival date.
- Product variant prices should be treated as tax-included final display/payment amounts when the UI label says price including tax.
