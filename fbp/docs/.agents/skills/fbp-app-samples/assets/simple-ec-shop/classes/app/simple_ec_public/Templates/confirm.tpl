<main class="sec sec-checkout-panel">
{foreach $quote.lines as $line}<section class="sec-cart-line"><h2>{$line.product.name|escape}</h2><p>{$line.code|escape} / {$line.price|number_format}円 × {$line.count|escape}</p></section>{/foreach}
{include file="totals.tpl" subtotal=$quote.subtotal tax=$quote.tax shipping=$quote.shipping total=$quote.total}
<fieldset class="sec-contact-box"><legend>申込者</legend><p>{$contact.from_name|escape}</p><p>{$contact.mail|escape}</p><p>{$contact.from_tel|escape}</p><p>〒{$contact.from_zipcode|escape}<br>{$contact.from_address|escape}</p></fieldset>
<fieldset class="sec-contact-box"><legend>送付先</legend><p>{$contact.name|escape}</p><p>{$contact.tel|escape}</p><p>〒{$contact.zipcode|escape}<br>{$contact.address|escape}</p></fieldset>
{if $contact.memo}<fieldset class="sec-contact-box"><legend>連絡事項</legend><p>{$contact.memo|public_text nofilter}</p></fieldset>{/if}
<form id="sec-confirm" onsubmit="return false"><input type="hidden" name="sec_csrf" value="{$sec_csrf|escape}"><input type="hidden" name="checkout_key" value="{$checkout_key|escape}"><div class="public-form-actions"><button type="button" class="ajax-link" data-class="simple_ec_public" data-function="cart">入力内容を修正する</button><button type="button" class="ajax-link" data-class="simple_ec_public" data-function="submit" data-form="sec-confirm">この内容で申し込む</button></div></form>
</main>
