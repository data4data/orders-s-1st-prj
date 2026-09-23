<script setup>
import { ref, watch } from 'vue';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import CartLineItem from '../cart/CartLineItem.vue';
import CartTotals from '../cart/CartTotals.vue';
import { cartState, loadCart, removeLine, updateLine } from '../shared/cart.js';
import { handleApiError } from '../shared/api.js';

// Mini-cart drawer (decision #40): opens from the header or after "Add to cart".
const busy = ref(false);

watch(() => cartState.drawerOpen, (open) => {
    if (open && !cartState.loading) loadCart().catch(handleApiError);
});

async function run(action) {
    busy.value = true;
    try {
        await action();
    } catch (error) {
        handleApiError(error);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Drawer v-model:visible="cartState.drawerOpen" position="right" :header="$t('cart.title')" class="!w-full sm:!w-[26rem]">
        <template #closeicon><AppIcon name="close" /></template>
        <div v-if="!cartState.cart" class="flex flex-col gap-3"><Skeleton height="4rem" /><Skeleton height="4rem" /></div>
        <EmptyState v-else-if="cartState.cart.lines.length === 0" icon="cart" :title="$t('cart.empty_title')" :text="$t('cart.empty_text')">
            <a href="/catalog" class="font-semibold text-primary-700 underline">{{ $t('cart.browse') }}</a>
        </EmptyState>
        <div v-else class="flex h-full flex-col">
            <ul class="grow divide-y divide-surface-200 overflow-y-auto">
                <CartLineItem
                    v-for="line in cartState.cart.lines" :key="line.variantId" :line="line" :currency="cartState.cart.currency" compact :busy="busy"
                    @quantity="(quantity) => run(() => updateLine(line.variantId, quantity))" @remove="run(() => removeLine(line.variantId))"
                />
            </ul>
            <div class="border-t border-surface-200 pt-3">
                <CartTotals :totals="cartState.cart.totals" :currency="cartState.cart.currency" :coupon-code="cartState.cart.coupon?.code" :shipping-label="cartState.cart.shippingEstimate?.name" />
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button as="a" href="/cart" severity="secondary" outlined :label="$t('cart.view')" />
                    <Button v-if="cartState.cart.canCheckout" as="a" href="/checkout" :label="$t('cart.checkout')" />
                    <Button v-else disabled :label="$t('cart.checkout')" />
                </div>
            </div>
        </div>
    </Drawer>
</template>
