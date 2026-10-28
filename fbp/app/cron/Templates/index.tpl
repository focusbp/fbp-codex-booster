

<div>
	<div style="float:right;margin-bottom: 8px;">
		<button class="ajax-link lang" data-class="{$class}" data-function="add">{t key="common.add"}</button>
		
	</div>
</div>
<div style="clear:both;"></div>

<table style="margin-top:10px;" class="moredata">
	<thead>
		<tr class="table-head">
			<th></th>
			<th class="lang">{t key="common.title"}</th>
			<th class="lang">{t key="common.class_name"}</th>
			<th class="lang">{t key="cron.handler_function"}</th>
			<th class="lang">{t key="cron.last_log"}</th>
			
			<th></th>
		</tr>
	</thead>

	<tbody class="db_additionals_sort">
		{foreach $items as $item}
			<tr id="{$item.id}" class="dragable-item">
				<td><div class="col col_handle"><span class="material-symbols-outlined handle">swap_vert</span></div></td>
				<td>{$item.title}</td>
				<td>{$item.class_name}</td>
				<td>{$item.function_name}</td>
				<td>{$item.last_log|escape:"force"|nl2br nofilter}</td>
				<td>
					<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>

					<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button>
					<button class="ajax-link lang" data-class="{$class}" data-function="exec" data-id="{$item._id_enc}">{t key="cron.exec"}</button>
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>

	<script>
	$(".db_additionals_sort").sortable({
		handle: '.col_handle',
		axis: "y",
		update: function () {
			var log = $(this).sortable("toArray");
			var fd = new FormData();
			fd.append('class', 'db_additionals');
			fd.append('function', 'sort');
			fd.append('log', log);
			appcon('app.php', fd);
		}
	});
	</script>
