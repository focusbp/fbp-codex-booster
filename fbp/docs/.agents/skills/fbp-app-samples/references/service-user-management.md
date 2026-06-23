# Service User Management Sample

## Purpose

Reusable FBP sample for turning a prototype or useful feature into a small paid public service. It provides public service accounts that are separate from FBP administrator users, plus Square card payment that activates service access.

## Included

- Public plan list.
- Public account creation with email and password.
- Public login and logout.
- Password reset token creation and reset form.
- Public account page with current subscription and payment history.
- Square card payment using framework Square payment helpers.
- Subscription-style service activation after successful payment.
- Standard FBP management screens for members, plans, subscriptions, payments, and reset tokens.
- Admin top button that shows the public service URL.

## Excluded

- Ecommerce cart, product catalog, shipping, inventory, order items, coupons, and receipt PDFs.
- True recurring billing automation.
- Square OAuth for per-shop Square accounts.
- Email delivery for reset links. The sample displays the reset URL for local verification.
- Email verification, team accounts, invitations, SSO, tenant isolation, and complex roles.
- Credentials, domains, production data, or live project paths.

## Public Flow

1. Visitor opens `/public_service*plans`.
2. Visitor creates an account from `/public_service*register`.
3. Login stores the public service member ID in a service-specific session.
4. The account page shows whether the member has an active subscription.
5. The plans page posts the encrypted plan ID to `/public_service*subscribe`.
6. Paid plans call `show_square_dialog("public_service", "square_payment_callback", ...)`.
7. The Square callback creates or reuses the Square customer, registers a card, charges the plan amount, stores `service_subscription`, stores `service_payment`, and redirects to thanks.
8. Free plans create an active subscription without a payment record.
9. Password reset creates a one-hour token in `service_password_reset`; production apps should email the generated reset URL.

## Admin Flow

Use generated Standard Screen DB pages for the minimum package:

- `service_member`: public service accounts.
- `service_plan`: service plan master.
- `service_subscription`: current and historical service access.
- `service_payment`: Square payment history.
- `service_password_reset`: reset token audit table, hidden from menu by default.

The `service_member` Standard Screen includes a `Public Service Link` top button. It opens a dialog that shows the public service entry URL generated with `get_APP_URL("public_service", "plans")`.

All sample-owned table names use the `service_` prefix.

## Starter Code

The reusable starter code is in:

- `assets/service-user-management/classes/app/public_service/public_service.php`
- `assets/service-user-management/classes/app/public_service/Templates/_service_head.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/_service_header.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/_service_footer.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/plans.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/register.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/login.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/request_password_reset.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/reset_password.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/account.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/thanks.tpl`
- `assets/service-user-management/classes/app/public_service/Templates/error.tpl`
- `assets/service-user-management/classes/app/service_public_link_dialog/service_public_link_dialog.php`
- `assets/service-user-management/classes/app/service_public_link_dialog/Templates/link.tpl`

Install the DB definitions, seed data, and starter code with:

```bash
php fbp/docs/.agents/skills/fbp-app-samples/scripts/install_service_user_management.php
```

## Implementation Notes

- Keep public service users separate from FBP admin users even when the same person is both an admin and a customer.
- Use `service_subscription.status = active` and `current_period_end >= time()` as the sample access check.
- Store Square customer/card IDs on `service_member` for reuse and copy them into subscription/payment records for audit.
- For production password reset, connect `request_password_reset_save()` to `fbp-send_email` templates instead of displaying the reset URL.
- Configure Square credentials in the target app settings before testing live payment.
- The sample member seed is for local testing only: `member@example.com` / `password123`.
