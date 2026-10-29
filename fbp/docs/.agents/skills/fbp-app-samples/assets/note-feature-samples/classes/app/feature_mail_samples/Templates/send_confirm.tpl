<form id="feature_mail_send_confirm" onsubmit="return false;">
<input type="hidden" name="token" value="{$mail.token|escape}"><p class="error_message error_token"></p>
<input type="hidden" name="confirmation" value="{$mail.confirmation|escape}"><p class="error_message error_confirmation"></p>
<p>送信先：{$mail.recipients|count}件（重複除外：{$mail.duplicates}件、空欄除外：{$mail.empty}件）</p>
<ul>{foreach $mail.recipients as $recipient}<li>{$recipient.name|escape} &lt;{$recipient.email|escape}&gt;</li>{/foreach}</ul>
<dl><dt>件名</dt><dd>{$mail.subject|escape}</dd><dt>本文</dt><dd>{$mail.body|escape|nl2br}</dd></dl>
<div class="button_row"><button type="button" class="ajax-link" data-class="feature_mail_samples" data-function="send_exe" data-form="feature_mail_send_confirm">{$mail.recipients|count}件にメール送信</button></div></form>
