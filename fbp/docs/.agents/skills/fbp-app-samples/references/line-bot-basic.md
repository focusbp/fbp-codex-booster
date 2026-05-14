# LINE Bot Basic Sample

## Purpose

Reusable FBP sample for the smallest useful LINE Bot setup: LINE member DB,
standard LINE webhook receiver, keyword rules, and a public profile page.

## Included

- `line_member` note/table with `userid`, `line_name`, `name`, and `member_type`.
- `member_type_opt` with `User` and `Manager`.
- `line_webhook` class that delegates to framework `webhook_line`.
- Webhook rules for follow, profile, image, sticker, and unmatch.
- Isolated public profile page class: `line_bot_basic_public`.
- Original Screen management page for `line_member`.
- Installer script for clean Codex Booster apps.

## Excluded

- LINE Channel Secret and Channel Access Token values.
- Project-specific member attributes, groups, orders, payments, or CRM fields.
- LIFF, LINE Login, and external SSO.
- Custom `getting_member` rule. The sample uses standard `webhook_line`
  member resolution.

## Install

From the FBP project root:

```bash
php fbp/docs/.agents/skills/fbp-app-samples/scripts/install_line_bot_basic.php
```

The installer copies the sample classes, creates or updates `line_member`,
creates or updates `member_type_opt`, and upserts the sample `webhook_rule`
records.

## Webhook URL

Set the LINE Messaging API webhook URL to the app URL for:

```text
/line_webhook*receive
```

Do not hard-code secrets in code. Configure the LINE credential fields in the
normal app settings.

## LINE Flow

1. LINE sends a webhook event to `line_webhook::receive`.
2. Framework `webhook_line` verifies and parses the LINE event.
3. Standard member resolution finds or creates `line_member` by `userid`.
4. `Profile` or `プロフィール` matches `line_webhook_rule_profile_basic`.
5. The action class returns a public profile URL with encrypted member ID.
6. `line_bot_basic_public::profile` stores the member context in session.
7. The normal POST profile form updates `line_member.name` without resending
   the member ID.

## Webhook Rules

| Keyword | Match Type | Action Class |
| --- | --- | --- |
| `[follow]` | `data_type` | `line_webhook_rule_follow_basic` |
| `Profile` | `exact` | `line_webhook_rule_profile_basic` |
| `プロフィール` | `exact` | `line_webhook_rule_profile_basic` |
| `[image]` | `data_type` | `line_webhook_rule_image_basic` |
| `[sticker]` | `data_type` | `line_webhook_rule_sticker_basic` |
| `[unmatch]` | `unmatch` | `line_webhook_rule_unmatch_basic` |

## Starter Code

- `assets/line-bot-basic/classes/app/line_webhook/line_webhook.php`
- `assets/line-bot-basic/classes/app/line_webhook_rule_*_basic/`
- `assets/line-bot-basic/classes/app/line_bot_basic_public/`
- `assets/line-bot-basic/classes/app/line_member_original_management/`
- `assets/line-bot-basic/line-bot-basic.json`
- `scripts/install_line_bot_basic.php`

## Verification

Run these after installation:

```bash
php fbp/cli.php app_call --json='{"class":"line_member_original_management","function":"run"}'
php fbp/cli.php webhook_rule_list
php fbp/cli.php db_schema
php -l classes/app/line_webhook/line_webhook.php
php -l classes/app/line_webhook_rule_profile_basic/line_webhook_rule_profile_basic.php
php -l classes/app/line_bot_basic_public/line_bot_basic_public.php
```

Check that:

- `line_member` exists with `userid`, `line_name`, `name`, and `member_type`.
- `member_type_opt` exists with user and manager options.
- `webhook_rule_list` includes the profile and data type rules.
- The LINE Platform webhook path is `/line_webhook*receive`.

## Notes

- This sample uses `line_bot_basic_public` instead of overwriting an existing
  `public_pages` class, so it can be copied into projects with fewer conflicts.
- In a production app, you may move the public functions into `public_pages`
  if that is the project convention.
- The first public handoff uses an encrypted member ID. After that, the member
  context is kept in session.
- Manager forwarding in framework `webhook_line` uses `line_member.member_type=1`.
