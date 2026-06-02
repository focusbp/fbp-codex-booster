<div id="setting_panel_tabs">
	<ul>
		<li><a href="#tabs-1" invoke-class="panel" invoke-function="release_backup">{t key="panel.release_backup.title"}</a></li>
		{if $bcp_export_enabled}
			<li><a href="#tabs-bcp-export">BCP Export</a></li>
		{/if}
	</ul>
	<div id="tabs-1" style="display: block;overflow: hidden;">

		<table class="release-table">
			{if !$MYSESSION.testserver}
				<tr>
					<td>
						<button class="ajax-link lang"
								data-class="release"
								data-function="release"
								style="float:inherit;margin-top:0px;">{t key="release.release_button"}</button>
					</td>
					<td>
						<span>{t key="panel.release_backup.release_help"}</span>
					</td>
				</tr>
			{/if}
			<tr>
				<td>
					<button class="ajax-link"
							data-class="restore"
							data-function="download_zip"
							style="float:inherit;margin-top:0px;">{t key="panel.release_backup.backup_button"}</button>
				</td>
				<td>
					<span>{t key="panel.release_backup.backup_help"}</span>
				</td>
			</tr>
			<tr>
				<td>
					<button class="ajax-link"
							data-class="restore"
							data-function="restore"
							style="float:inherit;margin-top:0px;">{t key="restore.restore_button"}</button>
				</td>
				<td>
					<span>{t key="panel.release_backup.restore_help"}</span>
				</td>
			</tr>
		</table>


	</div>
	{if $bcp_export_enabled}
		<div id="tabs-bcp-export" style="overflow: hidden;">
			<div class="bcp-export-panel">
				<p>{t key="bcp_export.description"}</p>

				<button class="download-link"
						data-class="bcp_export"
						data-function="download_zip"
						data-filename="{$bcp_export_download_filename|escape}"
						style="float:inherit;margin-top:0px;background:#b91c1c;color:#fff;border-color:#991b1b;">{t key="bcp_export.button"}</button>
			</div>
		</div>
	{/if}
</div>

<script>
	$(function () {
		// jQuery UI Tabs 初期化
		$("#setting_panel_tabs").tabs({
		});
	});
</script>
