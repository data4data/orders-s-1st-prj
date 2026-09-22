<script setup>
import Tag from 'primevue/tag';
import { formatMoney } from '../../shared/format.js';

// A placed order: lines with the frozen prices, addresses, shipping and totals (snapshots).
const props = defineProps({ order: { type: Object, required: true } });
const money = (cents) => formatMoney(cents, props.order.currency);
const lineOf = (a) => [a.company, `${a.firstName ?? ''} ${a.lastName ?? ''}`.trim(), `${a.street ?? ''} ${a.houseNumber ?? ''}`.trim(), `${a.postcode ?? ''} ${a.city ?? ''}`.trim(), a.countryCode].filter(Boolean);
const SEVERITY = { payment_pending: 'warn', paid: 'success', processing: 'info', shipped: 'info', delivered: 'success', cancelled: 'danger', refunded: 'secondary' };
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <strong class="text-lg">{{ $t('order.number', { number: order.number }) }}</strong>
            <Tag :severity="SEVERITY[order.state] ?? 'secondary'" :value="$t(order.stateLabel)" />
            <span v-if="order.placedAt" class="text-sm text-surface-600">{{ new Date(order.placedAt).toLocaleString() }}</span>
        </div>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-surface-200 text-left text-surface-600"><th class="py-1">{{ $t('order.product') }}</th><th class="py-1 text-right">{{ $t('order.quantity') }}</th><th class="py-1 text-right">{{ $t('order.line_total') }}</th></tr></thead>
            <tbody>
                <tr v-for="item in order.items" :key="item.sku" class="border-b border-surface-100">
                    <td class="py-2">{{ item.productName }} <span class="text-surface-500">{{ item.variantName }} · {{ item.sku }}</span></td>
                    <td class="py-2 text-right">{{ item.quantity }}</td>
                    <td class="py-2 text-right">{{ money(item.lineGross) }}</td>
                </tr>
            </tbody>
        </table>
        <dl class="ml-auto flex w-full max-w-xs flex-col gap-1 text-sm">
            <div v-if="order.totals.discountNet > 0" class="flex justify-between text-green-700"><dt>{{ $t('order.discount', { code: order.couponCode }) }}</dt><dd>{{ money(-order.totals.discountNet) }} {{ $t('order.excl_vat') }}</dd></div>
            <div class="flex justify-between"><dt>{{ $t('order.shipping', { name: order.shippingMethod }) }}</dt><dd>{{ money(order.totals.shippingNet) }} {{ $t('order.excl_vat') }}</dd></div>
            <div class="flex justify-between"><dt>{{ $t('order.vat') }}</dt><dd>{{ money(order.totals.totalTax) }}</dd></div>
            <div class="flex justify-between border-t border-surface-200 pt-1 text-base font-bold"><dt>{{ $t('cart.total') }}</dt><dd data-testid="order-total">{{ money(order.totals.totalGross) }}</dd></div>
        </dl>
        <div class="grid gap-4 text-sm sm:grid-cols-2">
            <div><h3 class="mb-1 font-semibold">{{ $t('checkout.billing') }}</h3><div v-for="line in lineOf(order.billingAddress)" :key="line">{{ line }}</div></div>
            <div><h3 class="mb-1 font-semibold">{{ $t('checkout.delivery') }}</h3><div v-for="line in lineOf(order.shippingAddress)" :key="line">{{ line }}</div></div>
        </div>
    </div>
</template>
