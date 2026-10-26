(function () {
    'use strict';
    const selector = '.sec-cart-controls select[name="quantity"]';
    $(document).off('change.rfCartQuantity', selector).on('change.rfCartQuantity', selector, function () {
        const form = this.closest('form');
        if (!form || !form.dataset.quantityUrl) return;
        const payload = new FormData(form);
        payload.set('class', 'simple_ec_public');
        payload.set('function', 'change');
        const checkout = document.getElementById('sec-checkout');
        if (checkout) {
            const draft = Object.fromEntries(new FormData(checkout));
            payload.set('checkout_draft', JSON.stringify(draft));
        }
        window.appcon(form.dataset.quantityUrl, payload);
    });
}());
