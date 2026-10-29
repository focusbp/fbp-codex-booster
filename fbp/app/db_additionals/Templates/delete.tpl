
<form>
<input type="hidden" name="list_scope" value="{$list_scope|default:''|escape}">

	<input type="hidden" name="id" value="{$data.id}">
	<input type="hidden" name="target_area" value="{$target_area|default:''}">
	<input type="hidden" name="reload_db_id" value="{$reload_db_id|default:0}">
	<input type="hidden" name="tb_name" value="{$data.tb_name|default:''}">

	<span class="lang">{t key="db_additionals.delete_label"}</span>
	<p>
		<b>
			{$data.button_title}
		</b>
	</p>

	<br>
	<p class="lang">{t key="db_additionals.delete_confirm"}</p>

	<button class="ajax-link lang" data-class="{$class}" data-function="delete_exe" data-reload_db_id="{$reload_db_id}">{t key="common.delete"}</button>
</form>
