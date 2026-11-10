{if $managed_media|default:null !== null}
    {assign preview_url $managed_media.image_url}
    {if $_use_thumbnail|default:false}
        {assign thumbnail_url $managed_media.thumbnail_url}
    {else}
        {assign thumbnail_url $managed_media.image_url}
    {/if}
{else}
    {assign preview_url $ctl->get_APP_URL('db_exe', 'view_image', ['path' => $file['path']])}
    {assign thumbnail_url $ctl->get_APP_URL('db_exe', 'view_image', ['path' => $path])}
{/if}
<a class="fbp-image-preview" href="{$preview_url|escape}" data-preview-title="{$field.parameter_title|default:$file.filename|escape}" data-close-label="{t key='common.close'}" aria-label="{t key='image.preview.open'}">
    <img src="{$thumbnail_url|escape}" alt="{$file.filename|escape}" style="max-width:min(100%, {$image_width}px, 500px);height:auto;">
</a>
