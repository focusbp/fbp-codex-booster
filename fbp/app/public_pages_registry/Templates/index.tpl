<div>
	<div style="float:right;margin-bottom:8px;">
		<button class="ajax-link" data-class="{$class}" data-function="add">{t key="public_pages_registry.add_button"}</button>
	</div>
	<p style="margin:0;color:#4b5563;font-size:12px;">{t key="public_pages_registry.description"}</p>
</div>
<div style="clear:both;"></div>

<table class="moredata" style="margin-top:10px;">
	<thead>
		<tr class="table-head">
			<th style="width:5%;"></th>
			<th style="width:20%;">{t key="common.title"}</th>
			<th style="width:12%;">{t key="public_pages_registry.menu"}</th>
			<th style="width:18%;">{t key="public_pages_registry.menu_label"}</th>
			<th>{t key="public_pages_registry.admin_link_role"}</th>
			<th>{t key="public_pages_registry.show_in_admin_links"}</th>
			<th style="width:25%;">{t key="public_pages_registry.route_url"}</th>
			<th style="width:8%;">{t key="common.status"}</th>
			<th style="width:8%;"></th>
		</tr>
	</thead>
	<tbody class="public-pages-registry-sort">
		{foreach $items as $item}
			<tr id="{$item.id}" style="background:#FFF;">
				<td><span class="ui-icon ui-icon-arrowthick-2-n-s ppr-handle"></span></td>
				<td>{$item.title|escape}</td>
				<td>{if $item.show_in_menu == 1}{$menu_opt[1]}{else}{$menu_opt[0]}{/if}</td>
				<td>{if $item.menu_label|default:'' != ''}{$item.menu_label|escape}{else}<span style="color:#6b7280;">-</span>{/if}</td>
				<td>{if $item.admin_link_role|default:'' == ''}{$admin_link_role_opt['']|escape}{else}{$item.admin_link_role|escape}{/if}</td>
				<td>{if $item.show_in_admin_links|default:'' === '0' || $item.show_in_admin_links|default:'' === 0}{$enabled_opt[0]}{else}{$enabled_opt[1]}{/if}</td>
				<td style="font-size:11px;word-break:break-all;">{if $item.route_url != ''}<a href="{$item.route_url|escape}" target="_blank" rel="noopener noreferrer">{$item.route_url|escape}</a>{elseif $item.link_requires_params == 1}{t key="public_pages_registry.link_parameters"}{/if}</td>
				<td>{if $item.enabled == 1}{$enabled_opt[1]}{else}{$enabled_opt[0]}{/if}</td>
				<td>
					<button class="ajax-link listbutton" data-class="{$class}" data-function="delete" data-id="{$item.id}" style="float:right;color:black;margin-right:5px;"><span class="ui-icon ui-icon-trash"></span></button>
					<button class="ajax-link listbutton" data-class="{$class}" data-function="edit" data-id="{$item.id}" style="float:right;color:black;"><span class="ui-icon ui-icon-pencil"></span></button>
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>

<script>
$(function () {
	$(".public-pages-registry-sort").sortable({
		handle: ".ppr-handle",
		axis: "y",
		update: function () {
			var log = $(this).sortable("toArray");
			var fd = new FormData();
			fd.append('class', '{$class}');
			fd.append('function', 'sort');
			fd.append('log', log);
			appcon('app.php', fd);
		}
	});
});
</script>
