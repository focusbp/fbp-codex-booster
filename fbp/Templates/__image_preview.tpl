{assign preview_url $ctl->get_APP_URL('db_exe', 'view_image', ['path' => $file['path']])}
{assign thumbnail_url $ctl->get_APP_URL('db_exe', 'view_image', ['path' => $path])}
<a class="fbp-image-preview" href="{$preview_url|escape}" data-preview-title="{$field.parameter_title|default:$file.filename|escape}" data-fit-label="{t key='image.preview.fit'}" data-in-label="{t key='image.preview.zoom_in'}" data-out-label="{t key='image.preview.zoom_out'}" aria-label="{t key='image.preview.open'}">
    <img src="{$thumbnail_url|escape}" alt="{$file.filename|escape}" style="max-width:min(100%, {$image_width}px, 500px);height:auto;">
</a>
