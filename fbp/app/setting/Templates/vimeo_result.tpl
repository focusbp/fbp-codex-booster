<p style="font-size:50px;">SUCCESS</p>
<table class="setting_detail_table">
{foreach from=$vimeo_permissions key=permission item=granted}
<tr><th>{t key="setting.vimeo_permission_{$permission}"}</th><td>{if $granted}{t key="setting.vimeo_permission_yes"}{else}{t key="setting.vimeo_permission_no"}{/if}</td></tr>
{/foreach}
</table>
<p>{t key="setting.vimeo_permission_note"}</p>
