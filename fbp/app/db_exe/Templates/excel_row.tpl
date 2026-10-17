<form class="excel-editor-form" onsubmit="return false;">
<input type="hidden" name="db_id" value="{$db_id}"><input type="hidden" name="excel_token" value="{$excel_token}">
<div class="excel-row-menu">
<button class="ajax-link" data-class="db_exe" data-function="excel_row_action" data-action="add">追加</button>
{if $excel_row_id}<button type="button" class="excel-delete-show">削除</button><button class="ajax-link" data-class="db_exe" data-function="excel_row_action" data-action="copy">コピー</button>{/if}
<button class="ajax-link" data-class="db_exe" data-function="excel_row_action" data-action="paste" {if !$excel_clip_available}disabled{/if}>ペースト</button>
</div>
<div class="excel-delete-confirm" style="display:none"><p>この行を削除しますか？</p><button class="ajax-link" data-class="db_exe" data-function="excel_row_action" data-action="delete" data-confirmed="1">削除する</button></div>
<p class="error_excel_error error_message" role="alert"></p>
</form>
{include file="excel_editor_style.tpl"}
<script>{literal}document.querySelector('.excel-delete-show')?.addEventListener('click',function(){this.closest('form').querySelector('.excel-delete-confirm').style.display='';});{/literal}</script>
