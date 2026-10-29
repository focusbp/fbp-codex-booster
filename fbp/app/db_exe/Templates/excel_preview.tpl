{if $excel_preview_available && $excel_preview_active}
<div class="excel-preview-toolbar" style="clear:both;text-align:right;">
<button class="ajax-link" data-class="db_exe" data-function="excel_preview_reset" data-db_id="{$db_id}" data-excel_token="{$excel_preview_token}">Excel風の表示に戻す</button>
</div>
{/if}
