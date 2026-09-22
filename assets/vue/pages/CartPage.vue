<script setup>
import { onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import CartLineItem from '../cart/CartLineItem.vue';
import CartTotals from '../cart/CartTotals.vue';
import { applyCoupon, cartState, loadCart, removeCoupon, removeLine, updateLine } from '../shared/cart.js';
import { handleApiError } from '../shared/api.js';
import { notify } from '../shared/notify.js';
import { t } from '../shared/i18n.js';

// Cart page (docs/diagrams/pages.html → Cart): lines, coupon, summary, go to checkout.
const busy = ref(false);
const couponCode = ref('');
const errors = reactive({ code: '', quantity: '' });

onMounted(() => loadCart().catch(handleApiError));

async function run(action) {
    busy.value = true;
    errors.quantity = '';
    try {
        await action();
    } catch (error) {
        await handleApiError(error, { errors });
        if (errors.quantity) notify({ type: 'warning', text: errors.quantity });
    } finally {
        busy.value = false;
    }
}

async function submitCoupon() {
    errors.code = '';
    if (!couponCode.value.trim()) {
        errors.code = t('cart.coupon_required');
        return;
    }
    await run(async () => {
        await applyCoupon(couponCode.value);
        notify({ type: 'success', text: t('cart.coupon_applied', { code: couponCode.value.toUpperCase() }) });
        couponCode.value = '';
    });
}
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-6">
        <h1 class="mb-4 text-2xl font-bold">{{ $t('cart.title') }}</h1>
        <div v-if="!cartState.cart" class="grid gap-6 md:grid-cols-3"><Skeleton class="md:col-span-2" height="12rem" /><Skeleton height="12rem" /></div>
        <div v-else-if="cartState.cart.lines.length === 0" class="rounded-lg border border-surface-200 bg-white">
            <EmptyState icon="cart" :title="$t('cart.empty_title')" :text="$t('cart.empty_text')">
                <Button as="a" href="/catalog" :label="$t('cart.browse')" />
            </EmptyState>
        </div>
        <div v-else class="grid items-start gap-6 md:grid-cols-3">
            <div class="rounded-lg border border-surface-200 bg-white px-4 md:col-span-2">
                <ul class="divide-y divide-surface-200">
                    <CartLineItem
                        v-for="line in cartState.cart.lines" :key="line.variantId" :line="line" :currency="cartState.cart.currency" :busy="busy"
                        @quantity="(quantity) => run(() => updateLine(line.variantId, quantity))" @remove="run(() => removeLine(line.variantId))"
                    />
                </ul>
            </div>
            <aside class="flex flex-col gap-4 rounded-lg border border-surface-200 bg-white p-4 md:sticky md:top-4">
                <form v-if="!cartState.cart.coupon" class="flex flex-col gap-1" novalidate @submit.prevent="submitCoupon">
                    <label for="coupon-code" class="text-sm font-semibold">{{ $t('cart.coupon') }}</label>
                    <div class="flex gap-2">
                        <InputText id="coupon-code" v-model="couponCode" class="grow uppercase" :invalid="!!errors.code" autocomplete="off" :aria-describedby="errors.code ? 'coupon-code-error' : undefined" @input="errors.code = ''" />
                        <Button type="submit" severity="secondary" :loading="busy" :label="$t('cart.apply')" />
                    </div>
                    <small v-if="errors.code" id="coupon-code-error" class="flex items-center gap-1 text-red-600"><AppIcon name="alert" /> {{ errors.code }}</small>
                </form>
                <div v-else class="flex items-center justify-between rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
                    <span class="flex items-center gap-2"><AppIcon name="coupons" /> {{ cartState.cart.coupon.code }}</span>
                    <Button text size="small" severity="secondary" :disabled="busy" :label="$t('cart.remove_coupon')" @click="run(removeCoupon)" />
                </div>
                <Message v-if="cartState.cart.coupon?.error" severity="warn" :closable="false">{{ $t(`cart.coupon_error.${cartState.cart.coupon.error}`) }}</Message>

                <CartTotals :totals="cartState.cart.totals" :currency="cartState.cart.currency" :coupon-code="cartState.cart.coupon?.code" :shipping-label="cartState.cart.shippingEstimate?.name" />
                <Message v-if="!cartState.cart.canCheckout" severity="error" :closable="false">{{ $t('cart.fix_before_checkout') }}</Message>
                <Button v-if="cartState.cart.canCheckout" as="a" href="/checkout" size="large" class="w-full justify-center"><AppIcon name="lock" /> {{ $t('cart.checkout') }}</Button>
                <Button v-else size="large" class="w-full justify-center" disabled><AppIcon name="lock" /> {{ $t('cart.checkout') }}</Button>
                <a href="/catalog" class="text-center text-sm text-primary-700 underline">{{ $t('cart.continue') }}</a>
            </aside>
        </div>
    </div>
</template>
