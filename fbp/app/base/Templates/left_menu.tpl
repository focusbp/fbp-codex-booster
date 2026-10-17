
<div id="left_menu">

	<div style="background:white; padding:10px;">
		
		{if $show_dashboard_menu}
			<h3>{$base_menu_i18n.dashboard}</h3>
			<a class="ajax-link lang" data-class="dashboard" data-function="page">{$base_menu_i18n.dashboard}</a>
		{/if}

		<h3 class="note-menu-heading"><span class="note-menu-title">{$base_menu_i18n.databases}</span>{if $spreadsheet_note_add_allowed}<button type="button" class="ajax-link note-create-button" data-class="base" data-function="spreadsheet_note_add" title="ノートを追加" aria-label="ノートを追加">＋</button>{/if}</h3>
			{foreach $database_menu as $d}
				<a class="ajax-link lang" data-class="db_exe" data-function="page" data-db_id="{$d.id}">{$d.menu_name|escape}</a>
			{/foreach}

		{if $menu_file != null}
			{include file="$menu_file"}
		{/if}

		{if $show_homepage_menu}
			<h3>{$base_menu_i18n.public_side}</h3>
			<a href="{$homepage_url|escape}" target="_blank" rel="noopener">{$base_menu_i18n.homepage}</a>
		{/if}

		{if $show_admin_console_menu}
			<h3>{$base_menu_i18n.admin_console}</h3>
		{/if}
		{if $can_show_project_portal}
			<a href="{$project_portal_url|escape}" target="_blank" rel="noopener">{$base_menu_i18n.project_portal}</a>
		{/if}
		{if $can_show_development_panel}
			<a class="ajax-link lang" data-class="panel" data-function="page">{$base_menu_i18n.development_panel}</a>
		{/if}
		{if $can_show_release_backup}
			<a class="ajax-link lang" data-class="panel" data-function="release_backup">{$base_menu_i18n.release_backup}</a>
		{/if}
		{if $can_show_user_management}
			<a class="ajax-link lang" data-class="user" data-function="page">{$base_menu_i18n.user_management}</a>
		{/if}
		{if $can_show_system_setting}
			<a class="ajax-link lang" data-class="setting" data-function="page">{$base_menu_i18n.system_setting}</a>
		{/if}
		{if $can_show_project_portal}
			{include file="project_portal_guide.tpl"}
		{/if}

	</div>
</div>
