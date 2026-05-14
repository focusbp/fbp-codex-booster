
<div id="left_menu">

	<div style="background:white; padding:10px;">
		
		{if $show_dashboard_menu}
			<h3>{$base_menu_i18n.dashboard}</h3>
			<a class="ajax-link lang" data-class="dashboard" data-function="page">{$base_menu_i18n.dashboard}</a>
		{/if}

		{if count($database_menu) > 0}
			<h3>{$base_menu_i18n.databases}</h3>
			{foreach $database_menu as $d}
				<a class="ajax-link lang" data-class="db_exe" data-function="page" data-db_id="{$d.id}">{$d.menu_name}</a>
			{/foreach}
		{/if}

		{if $menu_file != null}
			{include file="$menu_file"}
		{/if}

		{if $setting["show_menu_homepage"] == 1}
			<h3>{$base_menu_i18n.public_side}</h3>
			{if $setting["show_menu_homepage"] == 1}
				<a href="{$root_url}" target="_blank">{$base_menu_i18n.homepage}</a>
			{/if}
		{/if}

		{if $MYSESSION.app_admin || $MYSESSION.developer_permission == 1 || $MYSESSION.data_manager_permission == 1}
			<h3>{$base_menu_i18n.admin_console}</h3>
		{/if}
		{if $MYSESSION.app_admin || $MYSESSION.developer_permission == 1}
			<a class="ajax-link lang" data-class="wizard" data-function="run">{$base_menu_i18n.codex_terminal}</a>
		{/if}
		{if $MYSESSION.app_admin || $MYSESSION.developer_permission == 1}
			{if $setting["force_testmode"] == 1 ||
				($setting["force_testmode"] == 0 && $setting["show_developer_panel"] == 1) }
			<a class="ajax-link lang" data-class="panel" data-function="page">{$base_menu_i18n.development_panel}</a>
			{/if}
		{/if}
		{if $MYSESSION.app_admin || $MYSESSION.data_manager_permission == 1 }
			<a class="ajax-link lang" data-class="panel" data-function="release_backup">{$base_menu_i18n.release_backup}</a>
		{/if}
		{if $MYSESSION.app_admin}
			<a class="ajax-link lang" data-class="user" data-function="page">{$base_menu_i18n.user_management}</a>
			<a class="ajax-link lang" data-class="setting" data-function="page">{$base_menu_i18n.system_setting}</a>
		{/if}

	</div>
</div>
