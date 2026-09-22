<script setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import OrderDetails from '../customer/OrderDetails.vue';
import { api, handleApiError, ApiError } from '../shared/api.js';
import { cartState } from '../shared/cart.js';

// Order confirmation (after the payment page). Payment confirmation by webhook arrives in Phase 6,
// so the order shows "Awaiting payment" until then.
const props = defineProps({ id: { type: String, required: true } });
const order = ref(null);
const missing = ref(false);
const paymentResult = new URLSearchParams(window.location.search).get('payment');

onMounted(async () => {
    cartState.initialCount = 0;
    try {
        order.value = await api.get(`/api/orders/${props.id}`);
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            error.handled = true;
            missing.value = true;
            return;
        }
        handleApiError(error);
    }
});
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <EmptyState v-if="missing" icon="orders" :title="$t('order.not_found')" :text="$t('order.not_found_text')">
            <Button as="a" href="/account/orders" :label="$t('account.nav.orders')" />
        </EmptyState>
        <Skeleton v-else-if="!order" height="20rem" />
        <template v-else>
            <div class="mb-6 text-center">
                <AppIcon name="success" class="text-5xl text-green-600" />
                <h1 class="mt-2 text-2xl font-bold">{{ $t('order.thanks') }}</h1>
                <p class="text-surface-600">{{ $t('order.email_sent', { email: order.email }) }}</p>
            </div>
            <Message v-if="paymentResult === 'failed'" severity="error" class="mb-4" :closable="false">{{ $t('order.payment_failed') }}</Message>
            <Message v-else-if="order.state === 'payment_pending'" severity="info" class="mb-4" :closable="false">
                {{ paymentResult === 'paid' ? $t('order.payment_processing') : $t('order.payment_open') }}
                <a v-if="order.paymentUrl && paymentResult !== 'paid'" :href="order.paymentUrl" class="ml-1 font-semibold underline">{{ $t('order.pay_now') }}</a>
            </Message>
            <div class="rounded-lg border border-surface-200 bg-white p-5"><OrderDetails :order="order" /></div>
            <div class="mt-6 flex justify-center gap-3">
                <Button as="a" href="/catalog" severity="secondary" outlined :label="$t('order.continue')" />
            </div>
        </template>
    </div>
</template>
