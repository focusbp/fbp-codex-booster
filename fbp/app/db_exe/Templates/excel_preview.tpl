{if $excel_preview_available}
<div class="excel-preview-toolbar" style="clear:both;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 12px;padding:10px;border:1px solid #d7deea;background:#f8fafc;">
 {if $excel_preview_active}
 <span>検証用：検索＋一覧（本番の画面パターン：Excel風）</span>
 <button class="ajax-link" data-class="db_exe" data-function="excel_preview_reset" data-db_id="{$db_id}" data-excel_token="{$excel_preview_token}">本番の表示に戻す</button>
 {else}
 <span>本番の表示：Excel風 ／ Developer Modeでは列設定を変更できません。</span>
 <button class="ajax-link" data-class="db_exe" data-function="excel_preview_set" data-db_id="{$db_id}" data-excel_token="{$excel_preview_token}">検証用に検索＋一覧で表示</button>
 {/if}
</div>
{/if}
