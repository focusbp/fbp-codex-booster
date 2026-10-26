<main class="sec sec-checkout-panel sec-cart-panel">
{if $quote}
{foreach $quote.lines as $line}
<section class="sec-cart-line">
    <h2>{$line.product.name|escape}</h2>
    <p>{$line.code|escape} / {$line.price|number_format}円 × {$line.count|escape}</p>
    <form class="public-form sec-cart-controls" data-quantity-url="{$sec_change_url|escape}" id="sec-line-{$line.product.id|escape}-{$line.variant|escape}" onsubmit="return false">
        <input type="hidden" name="sec_csrf" value="{$sec_csrf|escape}">
        <input type="hidden" name="key" value="{$line.product.id|escape}:{$line.variant|escape}">
        <label>数量 <select name="quantity" aria-label="数量">{for $quantity=1 to 20}<option value="{$quantity}"{if $quantity == $line.count} selected{/if}>{$quantity}</option>{/for}</select></label>
        <button class="ajax-link" type="button" data-class="simple_ec_public" data-function="change" data-key="{$line.product.id|escape}:{$line.variant|escape}" data-remove="1" data-sec_csrf="{$sec_csrf|escape}">削除</button>
    </form>
</section>
{/foreach}
{include file="totals.tpl" subtotal=$quote.subtotal tax=$quote.tax shipping=$quote.shipping total=$quote.total}
<form class="public-form" id="sec-checkout" onsubmit="return false">
    <input type="hidden" name="sec_csrf" value="{$sec_csrf|escape}">
    <input type="hidden" name="checkout_key" value="{$checkout_key|escape}">
    <fieldset class="sec-contact-box">
        <legend>申込者</legend>
        {foreach ['from_name'=>'氏名','mail'=>'メールアドレス','from_tel'=>'電話番号','from_zipcode'=>'郵便番号','from_address'=>'住所'] as $key=>$label}
        <div class="public-field"><label for="sec-{$key}">{$label|escape}</label><input id="sec-{$key}" name="{$key|escape}" type="text" maxlength="255" value="{$contact[$key]|default:''|escape}"></div>
        {/foreach}
    </fieldset>
    <fieldset class="sec-contact-box sec-shipping-box">
        <legend>送付先</legend>
        <label class="public-check sec-same-address"><input type="checkbox" name="same_as_applicant" value="1" {if $same_as_applicant}checked{/if}>申込者と同じ</label>
        <div class="sec-shipping-fields">
        {foreach ['name'=>'氏名','tel'=>'電話番号','zipcode'=>'郵便番号','address'=>'住所'] as $key=>$label}
        <div class="public-field"><label for="sec-{$key}">{$label|escape}</label><input id="sec-{$key}" name="{$key|escape}" type="text" maxlength="255" value="{$contact[$key]|default:''|escape}"></div>
        {/foreach}
        </div>
    </fieldset>
    <fieldset class="sec-contact-box"><legend>連絡事項</legend><div class="public-field"><textarea id="sec-memo" name="memo" aria-label="連絡事項">{$contact.memo|default:$shop.memo_template|escape}</textarea></div></fieldset>
    <div class="error_checkout" role="alert"></div>
    <button class="ajax-link" type="button" data-class="simple_ec_public" data-function="confirm" data-form="sec-checkout">内容を確認する</button>
</form>
{else}<p>カートは空です。</p>{/if}
</main>
