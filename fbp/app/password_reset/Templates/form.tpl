<div class="passkey-management-actions" style="margin:50px 0;display:flex;flex-direction:column;gap:20px">
{if $passkey_management_available}
    <button type="button" class="ajax-link" data-class="password_reset" data-function="manage_passkeys" style="width:100%;box-sizing:border-box;margin:0;white-space:normal">登録済みパスキーを管理</button>
    <button type="button" class="ajax-link" data-class="password_reset" data-function="other_device" style="width:100%;box-sizing:border-box;margin:0;white-space:normal">他の端末にパスキーを設定する</button>
{else}
    <p>このサイトではパスキーを利用できません。</p>
{/if}
</div>
