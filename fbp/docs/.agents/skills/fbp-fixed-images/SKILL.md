---
name: fbp-fixed-images
description: Store fixed non-sensitive app-owned assets in classes/app/images and reference them through images/...; use DB type=image/type=file and normal upload helpers for dynamic, note, user, per-record, or access-controlled files.
---

# fbp-fixed-images

Use this skill when an FBP app needs fixed, non-sensitive system images, QR images, logos, static PDFs, sounds, or other app-owned assets that should be source-managed and served by the framework image route.

## decision rule
- Use `classes/app/images/` only for fixed assets that are safe to serve without per-user/per-record authorization.
- Use DB `type=image` / `type=file` fields, framework upload storage, and normal file/image helpers for dynamic images/files, user uploads, note attachments, product images, generated per-record files, and anything that may need access control.
- If the asset contains a secret, token, customer data, private URL, temporary link, or environment-specific value, do not store it in `classes/app/images/`.

## storage
- Put fixed app assets under `classes/app/images/`.
- Keep feature-class directories for code and templates; do not store shared fixed assets inside `classes/app/<class_name>/` unless the framework helper specifically requires class-local assets.
- Use stable, descriptive lowercase filenames such as `qr_mall_entry.png` or `invoice_seal.png`.
- Do not put user uploads, generated per-record files, or runtime output in `classes/app/images/`; keep those in the normal upload/data flow.
- Do not commit QR/images that contain secrets, one-time tokens, user-specific IDs, or environment-specific URLs.

## references
- In app templates, reference fixed assets as `images/<filename>` so Apache `.htaccess` and the PHP router resolve them to `classes/app/images/<filename>`.
- Do not use absolute `/images/<filename>` when the app may live under a subpath such as `/app-small/`.
- Avoid `data:image/...;base64,...` for fixed source-managed images. Store the binary file and reference it from the template.
- For full URLs generated from PHP, prefer existing framework URL helpers when available; otherwise keep the template reference relative to the app root.

## workflow
1. Check whether the asset is truly fixed, app-owned, and non-sensitive. If it is user data, note data, generated from per-request/per-record state, or access-controlled, do not move it into `classes/app/images/`.
2. Create `classes/app/images/` if needed, and place the asset file there.
3. Replace embedded base64/data URI or class-local file reads with `images/<filename>` in the template.
4. Remove now-unused PHP file reads, `base64_encode()`, and template assignments.
5. Sync source to the target runtime using the environment's normal procedure.
6. Verify the asset URL returns the expected MIME type and the affected screen still renders.

## verification
- Use `curl -I <app-base>/images/<filename>` or an equivalent request to confirm the route returns `200` and the expected content type.
- Use `app_call` for the affected class/function when the asset is inside a dashboard, public page, or dialog.
- If the asset is binary, also check that the source and runtime copies match after sync.
