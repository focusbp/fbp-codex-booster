<form id="restore_confirm_form" onsubmit="return false;">
	<table style="margin-bottom:10px;">
		<tr>
			<td>{t key="release.project_release_code"}</td>
			<td>{$info.project_release_code}</td>
		</tr>
		<tr>
			<td>{t key="release.datetime"}</td>
			<td>{$info.datetime}</td>
		</tr>
		<tr>
			<td>{t key="release.timezone"}</td>
			<td>{$info.timezone}</td>
		</tr>
		<tr>
			<td>{t key="release.memo"}</td>
			<td>{$info.memo}</td>
		</tr>
		<tr>
			<td>{t key="release.type"}</td>
			<td>{$info.type}</td>
		</tr>
	</table>

	<div style="margin-bottom:10px;">
		<label>
			<input type="checkbox" name="restore_user_data" value="1">
			{t key="restore.restore_user_data"}
		</label>
	</div>

	<div style="margin-bottom:10px;">
		<label>
			<input type="checkbox" name="restore_setting" value="1">
			{t key="restore.restore_setting"}
		</label>
	</div>

	<div style="margin-bottom:10px;">
		<label>
			<input type="checkbox" name="restore_sensitive_setting" value="1">
			{t key="restore.restore_sensitive_setting"}
		</label>
	</div>
</form>

{if $flg}
	<button class="ajax-link" data-form="restore_confirm_form" data-class="{$class}" data-function="restore_exe">{t key="restore.restore_button"}</button>
{else}
	<p class="error">{$message}</p>
{/if}
