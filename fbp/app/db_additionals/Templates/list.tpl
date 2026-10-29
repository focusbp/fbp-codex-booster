{if $tb_name != ""}
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:12px;">
	{foreach $place_opt as $place=>$place_label}
	<div style="border:1px solid #d7dce5;border-radius:8px;background:#fafbfc;padding:12px;">
		<div style="font-weight:bold;margin-bottom:10px;">
			{$place_label|escape}
		</div>
		<div class="db-additional-sort-area" data-place="{$place}" id="db_additional_sort_place_{$place}" style="min-height:120px;border:1px dashed #c7ced9;border-radius:6px;background:#fff;padding:8px;">
			{foreach $grouped_items[$place] as $a}
				<div class="db-additional-sort-item" id="{$a.id}" style="cursor:move;padding:10px 12px;margin-bottom:8px;border:1px solid #d7dce5;border-radius:6px;background:#f5f8fc;">
					{$a.button_title|escape}
				</div>
			{/foreach}
		</div>
	</div>
	{/foreach}
</div>

<script>
	(function () {
		var syncDbAdditionalSort = function () {
			var groups = {};
			$(".db-additional-sort-area").each(function () {
				var place = String($(this).data("place"));
				groups[place] = $(this).sortable("toArray");
			});
			var fd = new FormData();
			fd.append("class", "db_additionals");
			fd.append("function", "button_sort_exe");
			fd.append("tb_name", "{$tb_name|escape}");
			fd.append("target_area", "{$target_area|escape}");
			fd.append("reload_db_id", "{$reload_db_id}");
			fd.append("groups_json", JSON.stringify(groups));
			appcon("app.php", fd);
		};

		$(".db-additional-sort-area").sortable({
			connectWith: ".db-additional-sort-area",
			items: ".db-additional-sort-item",
			tolerance: "pointer",
			helper: "clone",
			placeholder: "db-additional-sort-placeholder",
			forcePlaceholderSize: true,
			update: function (event, ui) {
				if (this !== ui.item.parent()[0]) {
					return;
				}
				syncDbAdditionalSort();
			}
		});
	})();
</script>

<style>
	.db-additional-sort-placeholder {
		height: 42px;
		margin-bottom: 8px;
		border: 1px dashed #8aa4c1;
		border-radius: 6px;
		background: #eef5ff;
	}
</style>
{else}
<div class="db-additionals-overview">
    <div style="margin:12px 0;display:flex;justify-content:flex-end;">
        <button type="button" class="ajax-link" data-class="db_additionals" data-function="add" data-target_area="{$target_area|escape}" data-list_scope="all">{t key="db_additionals.add_button"}</button>
    </div>
    <div style="overflow-x:auto;">
    <table class="db-additionals-overview-table">
        <thead><tr><th>{t key="db.table_name"}</th><th>{t key="db_additionals.button_title"}</th><th>{t key="db_additionals.place"}</th><th>{t key="db_additionals.action_name"} / {t key="db_additionals.function_name"}</th><th>{t key="db_additionals.show_button"}</th><th></th></tr></thead>
        <tbody>
        {foreach $items as $a}
        <tr data-additional-id="{$a.id}" data-tb-name="{$a.tb_name|escape}">
            <td>{$table_labels[$a.tb_name]|default:$a.tb_name|escape}<br><small>{$a.tb_name|escape}</small></td>
            <td>{$a.button_title|escape}</td>
            <td>{$place_opt[$a.place]|default:$a.place|escape}</td>
            <td>{$a.class_name|escape} / {$a.function_name|default:'run'|escape}</td>
            <td>{$show_button_opt[$a.show_button]|default:$a.show_button|escape}</td>
            <td><div style="display:flex;gap:6px;justify-content:flex-end;">
                <button type="button" class="ajax-link" data-class="db_additionals" data-function="edit" data-id="{$a.id}" data-list_scope="all" data-target_area="{$target_area|escape}">{t key="common.edit"}</button>
                <button type="button" class="ajax-link" data-class="db_additionals" data-function="delete" data-id="{$a.id}" data-list_scope="all" data-target_area="{$target_area|escape}">{t key="common.delete"}</button>
            </div></td>
        </tr>
        {foreachelse}
        <tr><td colspan="6">{t key="db_additionals.empty"}</td></tr>
        {/foreach}
        </tbody>
    </table>
    </div>
</div>
{/if}
