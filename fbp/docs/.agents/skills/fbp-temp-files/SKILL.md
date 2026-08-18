---
name: fbp-temp-files
description: Implement temporary-file creation and cleanup in FBP apps with Controller::get_temp_dir(). Use for generated PPTX, PDF, ZIP, CSV, media conversions, export work files, or any app code that would otherwise call sys_get_temp_dir(), tempnam('/tmp', ...), or write directly below /tmp.
---

# FBP temporary files

## Core rule

Use `$ctl->get_temp_dir()` for temporary files created during an FBP request. Do not use
`sys_get_temp_dir()`, a hard-coded `/tmp`, or an app-relative path assembled in the app class.

`get_temp_dir()` returns PHP's default temporary directory when `open_basedir` is not set. When
`open_basedir` is set, it returns the writable app-root `tmp` directory (`fbp/../tmp`). It creates
the app directory when missing and throws when the resolved directory is not writable.

## Implementation pattern

Pass the resolved directory into helpers that generate files:

```php
$builder = new ExportBuilder($ctl->get_temp_dir());
$output_path = $builder->create($rows);
```

Create unpredictable filenames with `tempnam()` and check every filesystem operation:

```php
$base_path = tempnam($temp_dir, 'export_');
if ($base_path === false) {
    throw new RuntimeException('一時ファイルを作成できません。');
}

$output_path = $base_path . '.pptx';
if (!rename($base_path, $output_path)) {
    unlink($base_path);
    throw new RuntimeException('一時ファイル名を設定できません。');
}
```

Delete temporary files after download and on exceptions. Use `register_shutdown_function()` as
a final safeguard for response methods that call `exit`.

## Verification

- Verify syntax and a real file-generation path in the test app.
- Confirm the returned path is the PHP default when `open_basedir` is empty.
- Confirm it is the app-root `tmp` directory when `open_basedir` is set.
- Validate the generated file itself, such as `unzip -t` for PPTX or ZIP output.
- Do not retain generated files after verification.
