# Web Commerce Basic Sample

## Purpose

Reusable FBP sample for a normal web storefront. It is based on the ecommerce shape of `app-daitomiraku`, but the public entry is regular web login instead of LINE.

## Included

- Public product list and product detail pages.
- Email + password member registration and login.
- Single-store cart.
- Product categories.
- Product variants with simple `stock_quantity`.
- Square card payment using the framework Square payment helpers.
- Order and order item creation only after Square payment succeeds.
- Public order thanks page and member order history.
- Standard FBP management screens generated from `shop_...` DB tables.

## Excluded

- LINE entry, LINE user ID linkage, and LINE webhook flows.
- Email verification and password reset.
- Multi-store or mall behavior.
- Shop-scoped Square OAuth.
- Lot-level or warehouse-level inventory.
- Shipment automation, email sending, receipt PDF, coupons, and tax-inclusive/tax-exclusive policy variants.
- Credentials, domains, production data, or live project paths.

## Public Flow

1. Visitor opens `/public_pages*shop`.
2. Product cards are listed from active `shop_product` rows that have active variants with stock.
3. Visitor opens a product detail page and adds a variant quantity to the session cart.
4. Checkout requires login. New members register with email and password.
5. Checkout validates buyer and shipping fields.
6. `show_square_dialog()` starts the Square card flow.
7. The Square callback registers the Square customer/card when needed, charges the cart total, creates `shop_customer_order`, creates `shop_customer_order_item`, decrements variant `stock_quantity`, clears the cart, and redirects to thanks.
8. Members can view their own order history.

## Admin Flow

Use the generated Standard Screen DB pages for the MVP package:

- `shop_member`: public web members.
- `shop_product_category`: category master.
- `shop_product`: product master with image and status.
- `shop_product_variant`: product options, price, and `stock_quantity`.
- `shop_customer_order`: Square-paid orders.
- `shop_customer_order_item`: copied order line items.

The `shop_customer_order` Standard Screen includes a `Public EC Link` top button. It opens a dialog that shows the public storefront URL generated with `get_APP_URL("public_pages", "shop")`.

All sample-owned table names are prefixed with `shop_` to avoid collisions with other samples.

## Starter Code

The reusable public-page starter code is in:

- `assets/web-commerce-basic/classes/app/public_pages/public_pages.php`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/_site_head.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/_site_header.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/_site_footer.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/shop.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/_product_list.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/product_detail.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/cart.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/register.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/login.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/account.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/checkout.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/thanks.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/history.tpl`
- `assets/web-commerce-basic/classes/app/public_pages/Templates/error.tpl`
- `assets/web-commerce-basic/classes/app/shop_public_link_dialog/shop_public_link_dialog.php`
- `assets/web-commerce-basic/classes/app/shop_public_link_dialog/Templates/link.tpl`

Install the DB definitions, seed data, and starter code with:

```bash
php fbp/docs/.agents/skills/fbp-app-samples/scripts/install_web_commerce_basic.php
```

## Implementation Notes

- Keep the sample single-store. Do not add shop tables unless the user explicitly asks for mall behavior.
- Keep inventory to `shop_product_variant.stock_quantity`.
- The starter uses app-wide Square settings from the framework. Configure Square credentials in the target app settings before testing a live payment.
- Do not store Square credentials in sample files.
- The sample member seed is for local testing only: `buyer@example.com` / `password123`.
- Prices are stored as integer yen values. The sample calculates tax from `shop_product.tax_rate` and applies a fixed shipping fee in the public page class.
- For public image display, product image URLs are generated through `get_APP_URL("db_exe", "view_image", ...)`.
