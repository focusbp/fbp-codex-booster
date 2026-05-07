<div id="minimal_sort_original_management_list_area">
    <table style="margin-top:10px;width:100%;">
        <tbody id="minimal_sort_original_management_sortable">
            {foreach $rows as $row}
                <tr id="{$row.id}" class="dragable-item">
                    <td style="width:42px;text-align:center;">
                        <span class="material-symbols-outlined handle" style="cursor:move;">swap_vert</span>
                    </td>
                    <td class="row_style" style="width:90px;">
                        <span class="row_title">順番</span>
                        <span class="row_value"><p>{$row.sort|escape}</p></span>
                    </td>
                    <td class="row_style">
                        <span class="row_title">タイトル</span>
                        <span class="row_value"><p>{$row.title|escape}</p></span>
                    </td>
                    <td class="row_style">
                        <span class="row_title">メモ</span>
                        <span class="row_value"><p>{$row.note|escape}</p></span>
                    </td>
                    <td style="padding:10px;display:flex;flex-direction:row-reverse;">
                        <button type="button" class="ajax-link listbutton" data-class="minimal_sort_original_management" data-function="delete_dialog" data-id="{$row.id}" style="float:right;color:#dc2626;margin-right:5px;">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                        <button type="button" class="ajax-link listbutton" data-class="minimal_sort_original_management" data-function="edit_dialog" data-id="{$row.id}" style="float:right;color:#2d2d2d;">
                            <span class="material-symbols-outlined">edit_square</span>
                        </button>
                    </td>
                </tr>
            {/foreach}
            {if count($rows) === 0}
                <tr>
                    <td colspan="5" style="text-align:center;color:#64748b;padding:12px;">データはありません。</td>
                </tr>
            {/if}
        </tbody>
    </table>
</div>

<script>
(function ($) {
    var selector = "#minimal_sort_original_management_sortable";
    if ($(selector).hasClass("ui-sortable")) {
        $(selector).sortable("destroy");
    }
    $(selector).sortable({
        handle: ".handle",
        cancel: "button",
        axis: "y",
        update: function () {
            var log = $(this).sortable("toArray");
            var fd = new FormData();
            fd.append("class", "minimal_sort_original_management");
            fd.append("function", "sort_save");
            fd.append("log", log);
            appcon("app.php", fd);
        }
    });
})(jQuery);
</script>
