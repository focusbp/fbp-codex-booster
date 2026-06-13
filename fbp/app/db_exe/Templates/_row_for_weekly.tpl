<div class="task active_indicator" data-class="{$class}" data-id="{$row["_id_enc"]}" data-db_id="{$db_id}">
	<span class="controlbox"><span class="material-symbols-outlined handle" style="width:25px;">drag_pan</span>
		<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$row["_id_enc"]}" data-db_id="{$db_id}" style="float:right;color:#2d2d2d;margin-right:5px;"><span class="material-symbols-outlined">delete</span></button>
		<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$row["_id_enc"]}"  data-db_id="{$db_id}" style="float:right;color:#2d2d2d;"><span class="material-symbols-outlined">edit_square</span></button>
	</span>
	<span class="time">{$row.start_time} - {$row.end_time}</span>
	<div class="task_message">
		{foreach $group1 as $field}
			<div class="row_style">
				<span class="row_value">{include file="{$base_template_dir}/__item_viewer_notag.tpl"}</span>
			</div>
		{/foreach}
		{foreach $child_tables as $c}
			{if $c.show_icon_on_parent_list == 0}
			<div class="row_style task_child_table_link">
				<span class="row_title">{$c.menu_name}</span>
				<span class="row_value active_indicator_trigger"><span class="ajax-link material-symbols-outlined" style="cursor:pointer;width:24px;" data-class="{$class}" data-function="rows_child" data-db_id="{$c.id}" data-parent_id="{$row.id}" data-parent_field="{$c._parent_field_name|default:'parent_id'}" data-parent_db_id="{$c._parent_db_id|default:''}">table_rows</span></span>
			</div>
			{/if}
		{/foreach}
	</div>
</div>
