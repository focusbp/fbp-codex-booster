<div id="setting_panel_tabs">
	<ul>
		<li><a href="#tabs-db" invoke-class="db" invoke-function="page">{$panel_tab_labels.db}</a></li>
		<li><a href="#tabs-dashboard" invoke-class="dashboard" invoke-function="list">{$panel_tab_labels.dashboard}</a></li>
		<li><a href="#tabs-constants" invoke-class="panel_constants" invoke-function="page">{$panel_tab_labels.constants}</a></li>
		<li><a href="#tabs-webhook" invoke-class="webhook_rule" invoke-function="page">{$panel_tab_labels.webhook}</a></li>
		<li><a href="#tabs-embed-app" invoke-class="embed_app" invoke-function="page">{$panel_tab_labels.embed_app}</a></li>
		<li><a href="#tabs-mcp-server" invoke-class="mcp_manage" invoke-function="page">{$panel_tab_labels.mcp_server}</a></li>
		<li><a href="#tabs-public-pages" invoke-class="public_pages_registry" invoke-function="page">{$panel_tab_labels.public_pages}</a></li>
		<li><a href="#tabs-public-assets" invoke-class="public_assets" invoke-function="page">{$panel_tab_labels.public_assets}</a></li>
		<li><a href="#tabs-buttons" invoke-class="db_additionals" invoke-function="list">{$panel_tab_labels.db_additionals}</a></li>
		<li><a href="#tabs-cron" invoke-class="cron" invoke-function="page">{$panel_tab_labels.cron}</a></li>
		<li><a href="#tabs-mail" invoke-class="email_format" invoke-function="page">{$panel_tab_labels.email_templates}</a></li>
	</ul>
	<div id="tabs-db">
	</div>
	<div id="tabs-dashboard">
	</div>
	<div id="tabs-constants">
	</div>
	<div id="tabs-webhook">
	</div>
	<div id="tabs-embed-app">
	</div>
	<div id="tabs-mcp-server">
	</div>
	<div id="tabs-public-pages">
	</div>
	<div id="tabs-public-assets">
	</div>
	<div id="tabs-buttons">
	</div>
	<div id="tabs-cron">
	</div>
	<div id="tabs-mail">
	</div>
</div>

<script>
	$(function () {
		// jQuery UI Tabs 初期化
		$("#setting_panel_tabs").tabs({
		});
	});
</script>
