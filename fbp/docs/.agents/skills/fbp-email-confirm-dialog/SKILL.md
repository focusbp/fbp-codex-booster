---
name: fbp-email-confirm-dialog
description: Implement FBP email address confirmation-code dialog flows for app management screens, including send-code and verify steps, session-backed pending email storage, validation targets, no change/back button on the code-entry screen, and close-on-success behavior.
---

# fbp-email-confirm-dialog

Use this skill when adding or repairing an FBP dialog flow that registers or changes an email address only after a one-time confirmation code is verified.

## Required Flow

1. Open an input dialog with `show_multi_dialog()`.
2. Send a 4-digit code to the posted email address.
3. Store only pending state in session: email, hashed code, expiry, attempts.
4. Re-render the same dialog name with the code-entry template.
5. On verification success, update the target record, clear pending state, close the dialog, reload the affected list/detail area, then show a notification.

## Hard Rules

- The code-entry template must not include a "メールアドレスを変更", back, reset, or re-open-input button. If the email is wrong, the user should close the dialog and start again.
- Do not store the raw confirmation code in DB or long-lived state. Store `password_hash($code, PASSWORD_DEFAULT)` in session.
- Use `res_error_message("email", ...)` and `res_error_message("code", ...)` only when the template has matching `.error_email` / `.error_code` elements.
- Do not call another dialog function directly after success. Use `close_multi_dialog()` followed by `reload_area()` or another response action.
- Do not close the dialog on validation failure.
- Keep pending code expiry short, normally 10 minutes, and cap wrong-code attempts.
- Do not add environment-specific mail settings, URLs, credentials, or local paths to docs or samples.

## Sample Assets

Copy and adapt the files in `assets/email-confirm-dialog/`:

- `class-methods.php`: controller methods and helper methods to paste into an Original Screen class.
- `email_confirm_input.tpl`: email input step.
- `email_confirm_verify.tpl`: code-entry step. This intentionally has only the final registration button.

Before using the sample, replace placeholders such as `YOUR_TABLE`, `YOUR_DIALOG_NAME`, `YOUR_LIST_AREA_ID`, and `email_confirm_*` names with the target app's class/table naming.

## Verification

- Run `php -l` on the edited class.
- Confirm `email_confirm_input.tpl` has `.error_email` and `email_confirm_verify.tpl` has `.error_code`.
- Search the code-entry template for unwanted navigation: `rg "メールアドレスを変更|変更|戻る|back|reset" <verify-template>`.
- In the test environment, send a code, verify success closes the dialog, and verify wrong/expired codes keep the dialog open with an inline error.
