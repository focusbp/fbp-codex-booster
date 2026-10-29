<form class="sample_mail_config_form" onsubmit="return false;">
<input type="hidden" name="token" value="{$token|escape}">
<p class="error_message error_token" role="alert"></p>
<p>{$account.title|escape}</p>
<label>受信方式<select name="receive_protocol"><option value="imap" {if $account.receive_protocol == 'imap'}selected{/if}>IMAP</option><option value="pop" {if $account.receive_protocol == 'pop'}selected{/if}>POP</option></select></label>
<label>サーバー<input type="text" name="receive_server" value="{$account.receive_server|escape}" maxlength="255"></label>
<label>ポート<input type="text" name="receive_port" value="{$account.receive_port|escape}" maxlength="5" inputmode="numeric"></label>
<label>暗号化<select name="receive_secure"><option value="ssl" {if $account.receive_secure == 'ssl'}selected{/if}>SSL/TLS</option><option value="tls" {if $account.receive_secure == 'tls'}selected{/if}>STARTTLS</option><option value="none" {if $account.receive_secure == 'none'}selected{/if}>なし</option></select></label>
<label>ユーザー名<input type="text" name="receive_user" value="{$account.receive_user|escape}" maxlength="255" autocomplete="off"></label>
<label>パスワード（空欄で保持）<input type="password" name="receive_password" value="" maxlength="255" autocomplete="new-password"></label>
<label>IMAPフォルダー<input type="text" name="receive_folder" value="{$account.receive_folder|escape}" maxlength="255" placeholder="INBOX"></label>
<div class="button_row"><button type="button" class="ajax-link" data-class="sample_mail_import" invoke-function="save">保存</button></div>
</form>
