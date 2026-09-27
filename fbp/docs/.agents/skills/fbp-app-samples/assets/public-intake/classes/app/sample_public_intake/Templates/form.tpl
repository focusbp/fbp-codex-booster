<main class="sample-intake"><h1>お問い合わせ</h1><p>内容をご入力のうえ、確認画面へお進みください。</p>
<form class="public-form" onsubmit="return false;">
    <input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token" role="alert"></p>
    <div class="public-field">{fields_form_original name="name" type="text" value="" title="お名前"}<p class="error_message error_name" role="alert"></p></div>
    <div class="public-field">{fields_form_original name="email" type="text" value="" title="メールアドレス"}<p class="error_message error_email" role="alert"></p></div>
    <div class="public-field">{fields_form_original name="message" type="textarea" value="" title="お問い合わせ内容"}<p class="error_message error_message" role="alert"></p></div>
    <div class="public-actions"><button type="button" class="ajax-link" data-class="sample_public_intake" invoke-function="confirm">内容を確認する</button></div>
</form></main>
