<script setup>
import { formatMoney } from '../../shared/format.js';

// Order summary: subtotal, discount, shipping, total incl. VAT (gross large, VAT shown separately).
const props = defineProps({
    totals: { type: Object, required: true },
    currency: { type: String, required: true },
    couponCode: { type: String, default: null },
    shippingLabel: { type: String, default: null },
    shippingPending: { type: Boolean, default: false },
});
const money = (cents) => formatMoney(cents, props.currency);
</script>

<template>
    <dl class="flex flex-col gap-1.5 text-sm">
        <div class="flex justify-between"><dt>{{ $t('cart.subtotal') }}</dt><dd>{{ money(totals.itemsGross) }}</dd></div>
        <div v-if="totals.discountGross > 0" class="flex justify-between text-green-700">
            <dt>{{ couponCode ? $t('cart.discount_code', { code: couponCode }) : $t('cart.discount') }}</dt>
            <dd>{{ money(-totals.discountGross) }}</dd>
        </div>
        <div class="flex justify-between">
            <dt>{{ shippingLabel ? $t('cart.shipping_named', { name: shippingLabel }) : $t('cart.shipping') }}</dt>
            <dd>
                <template v-if="shippingPending || totals.shippingGross === null">{{ $t('cart.shipping_at_checkout') }}</template>
                <template v-else-if="totals.shippingGross === 0">{{ $t('cart.free') }}</template>
                <template v-else>{{ money(totals.shippingGross) }}</template>
            </dd>
        </div>
        <div class="mt-2 flex items-baseline justify-between border-t border-surface-200 pt-2">
            <dt class="font-bold">{{ $t('cart.total') }}</dt>
            <dd class="text-xl font-extrabold" data-testid="cart-total">{{ money(totals.totalGross) }}</dd>
        </div>
        <div class="flex justify-between text-xs text-surface-500"><dt>{{ $t('cart.vat_included') }}</dt><dd>{{ money(totals.totalTax) }}</dd></div>
    </dl>
</template>
