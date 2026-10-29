<form id="feature_mail_send_form" onsubmit="return false;">
<input type="hidden" name="token" value="{$token|escape}"><p class="error_message error_token"></p>
<p>ノートのリスト全体から、メールアドレスの重複と空欄を除いて個別に送信します。</p>
{fields_form_original name="subject" type="text" title="件名" value=""}<p class="error_message error_subject"></p>
{fields_form_original name="body" type="textarea" title="本文" value=""}<p class="error_message error_body"></p>

<div class="button_row"><button type="button" class="ajax-link" data-class="feature_mail_samples" data-function="send_confirm" data-form="feature_mail_send_form">送信内容を確認する</button></div></form>
