<p>{if $payment.refund_key}依頼済みの返金結果をSquareに確認します。{else}この決済を全額返金します。{/if}</p>
<p>{$payment.name|escape} / {$payment.mail|escape}</p>
<p>返金額：{$payment.amount|number_format}円</p>
<form id="sec-refund-form" onsubmit="return false;">
    <input type="hidden" name="id" value="{$payment_token|escape}">
    <input type="hidden" name="refund_csrf" value="{$refund_csrf|escape}">
    <div class="error_id error_message"></div>
    <div class="error_refund_csrf error_message"></div>
    <div class="error_refund error_message" role="alert"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
        <button type="button" class="ajax-link" data-class="simple_ec_admin" data-function="refund" data-form="sec-refund-form">{if $payment.refund_key}返金結果を確認する{else}全額返金する{/if}</button>
    </div>
</form>
