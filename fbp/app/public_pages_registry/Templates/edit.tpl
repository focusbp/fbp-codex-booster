<form id="public_pages_registry_edit_form" onsubmit="return false;">
	<input type="hidden" name="id" value="{$data.id|default:''}">
	<input type="hidden" name="function_name" value="{$data.function_name|default:''|escape}"><p class="error_message error_function_name"></p>
	<table class="custom_events_table">
		<tbody>
			<tr><td style="width:30%;">{t key="common.title"}</td><td><input type="text" name="title" value="{$data.title|default:''|escape}"><p class="error_message error_title"></p></td></tr>
			<tr><td>{t key="public_pages_registry.show_in_admin_links"}</td><td>{html_options name="show_in_admin_links" options=$enabled_opt selected=$data.show_in_admin_links|default:1}<p class="error_message error_show_in_admin_links"></p></td></tr>
			<tr><td>{t key="public_pages_registry.admin_link_role"}</td><td>{html_options name="admin_link_role" options=$admin_link_role_opt selected=$data.admin_link_role|default:''}<p class="error_message error_admin_link_role"></p><p>{t key="public_pages_registry.admin_link_help"}</p></td></tr>
			<tr><td>{t key="public_pages_registry.link_type"}</td><td>{html_options name="link_requires_params" options=$link_requires_params_opt selected=$data.link_requires_params|default:0}<p class="error_message error_link_requires_params"></p></td></tr>
			<tr><td>{t key="public_pages_registry.show_in_menu"}</td><td>{html_options name="show_in_menu" options=$menu_opt selected=$data.show_in_menu|default:0}<p class="error_message error_show_in_menu"></p></td></tr>
			<tr><td>{t key="public_pages_registry.menu_label"}</td><td><input type="text" name="menu_label" value="{$data.menu_label|default:''|escape}"><p class="error_message error_menu_label"></p></td></tr>
			<tr><td>{t key="common.status"}</td><td>{html_options name="enabled" options=$enabled_opt selected=$data.enabled|default:1}<p class="error_message error_enabled"></p></td></tr>
		</tbody>
	</table>
	<div style="margin-top:10px;text-align:right;">
		<button class="ajax-link" data-class="{$class}" data-function="edit_exe" data-form="public_pages_registry_edit_form">{t key="common.save"}</button>
	</div>
</form>
