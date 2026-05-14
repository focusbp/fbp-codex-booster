# LINE-Entry Mall Sample

## Purpose

Reusable FBP sample for a mall-style ecommerce app where the only public entry is LINE. The sample is intentionally generic.

## Included

- LINE-only public entry via `userid` or `user_id`.
- First access registration with `name` only.
- Registered account page with read-only member info.
- Account edit dialog.
- Multiple shops.
- Products linked to shops.
- Product variants.
- Product categories.
- Single-shop cart.
- One shop-level shipping fee per order.
- Checkout and Square payment flow.
- Orders and order items.
- Public order history and receipt.
- Public inquiry form.
- Original Screen management pages for shops, products, categories, LINE members, orders, and inquiries.

## Excluded

- Project-specific member type, chapter, group, or referral fields.
- Project-specific post-order workflow.
- External-app SSO linkage.
- URL-derived client IDs.
- Shared SSO keys.
- Account deletion API linkage to another app.

## Public Flow

1. User opens a LINE-issued mall URL containing `userid` or `user_id`.
2. App stores the LINE user ID in session.
3. If no completed `line_member` exists, show registration.
4. Registration asks for `name` only.
5. Save `line_member`, then redirect to the mall top.
6. Mall top shows searchable product cards.
7. Product detail adds a selected variant to cart.
8. Cart rejects products from another shop.
9. Checkout creates an order only after Square payment succeeds.
10. Order history and receipt are scoped to the LINE member.
11. Seminar/event products require an event date and are hidden publicly from the day after the event.
12. Reservation and inquiry products may skip payment and create orders directly when the app treats them as non-payment requests.

## Management Screens

Use Original Screen for all management screens.

- `shop_original_management`: shop profile, status, shipping fee, Square settings/connection.
- `product_original_management`: product, images, variants, status, category, type-specific form fields.
- `product_category_original_management`: manual sort category list.
- `line_member_original_management`: member list and edit.
- `customer_order_original_management`: orders, shipped datetime, shipping slip number, details, receipt/order PDF if needed.
- `mall_inquiry_original_management`: inquiry handling.

## Starter Code

The reusable public-page starter code is in:

- `assets/line-entry-mall/classes/app/public_pages/public_pages.php`
- `assets/line-entry-mall/classes/app/public_pages/Templates/_site_head.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/_site_header.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/_site_footer.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/account.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/account_register.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/account_edit.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/shop.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/product_detail.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/cart.tpl`
- `assets/line-entry-mall/classes/app/public_pages/Templates/error.tpl`

The starter code is intentionally partial. It provides the generic LINE-entry member/account flow, mall top, product detail, and single-shop cart constraint. Extend it with checkout, Square payment, order creation, order history, receipt, inquiry, and admin screens using the DB reference and normal FBP skills.

## Implementation Notes

- Keep the first registration generic: `name` only.
- Keep the cart single-shop. When adding a product from another shop, return `res_error_message()` and do not rewrite the cart.
- Use JavaScript for product-type-specific product form visibility. For seminar/event, show and require event date. For reservation/inquiry, hide tax rate, unit, sales limit, and event date if those fields are not used by the checkout flow.
- Create `customer_order` and `customer_order_item` only after payment succeeds.
- If reservation/inquiry products skip payment, create the order directly without `paid_at`, and do not show a receipt for that order.
- Use `shop.shipping_fee` once per order.
- Label variant prices as tax-included when the app stores/display prices as final charged amounts.
- Use "shipped datetime" wording for shipment processing time. Do not label it as delivery/arrival datetime unless the app stores a separate arrival date.
- Use Original Screen for management screens. This sample intentionally does not provide copied admin screen code because screen fields and customer-specific controls should be generated from the target app's note definitions.
