<form class="public-form" onsubmit="return false;">
    <input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token" role="alert"></p>
    <dl class="sample-intake-confirm"><dt>お名前</dt><dd>{$row.name|escape}</dd><dt>メールアドレス</dt><dd>{$row.email|escape}</dd><dt>内容</dt><dd>{$row.message|public_text nofilter}</dd></dl>
    <div class="public-actions"><button type="button" class="ajax-link" data-class="sample_public_intake" invoke-function="save">送信する</button></div>
</form>
