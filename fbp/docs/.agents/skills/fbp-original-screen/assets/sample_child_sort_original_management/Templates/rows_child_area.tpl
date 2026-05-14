<table style="margin-top:10px;">
    <tbody id="sample_child_sort_original_management_sortable_{$parent_id|escape}">
    {foreach $rows as $row}
        <tr id="{$row.id|escape}">
            <td><span class="material-symbols-outlined handle">swap_vert</span></td>
            <td class="row_style">
                <span class="row_title">タイトル</span>
                <span class="row_value">{fields_view_direct db="sample_child_sort" fields="title" data=$row}</span>
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>

<script>
$("#sample_child_sort_original_management_sortable_{$parent_id|escape}").sortable({
    handle: ".handle",
    cancel: "button",
    axis: "y",
    update: function () {
        var fd = new FormData();
        fd.append("class", "sample_child_sort_original_management");
        fd.append("function", "sort_child_save");
        fd.append("db_id", "{$db_id|escape}");
        fd.append("parent_id", "{$parent_id|escape}");
        fd.append("log", $(this).sortable("toArray"));
        appcon("app.php", fd);
    }
});
</script>
