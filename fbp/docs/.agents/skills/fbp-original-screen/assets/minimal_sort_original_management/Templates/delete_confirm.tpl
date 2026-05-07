<div style="padding:12px 4px 0 4px;">
    <p style="margin:0;color:#334155;">「{$row.title|escape}」を削除します。</p>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
        <button type="button" class="button_link_close" onclick="close_multi_dialog('minimal_sort_original_management_delete');">キャンセル</button>
        <button type="button" class="ajax-link button_link" data-class="minimal_sort_original_management" data-function="delete_save" data-id="{$row.id}" style="background:#dc2626;border-color:#dc2626;">削除</button>
    </div>
</div>
