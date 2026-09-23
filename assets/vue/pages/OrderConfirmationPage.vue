<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import OrderDetails from '../customer/OrderDetails.vue';
import { api, handleApiError, ApiError } from '../shared/api.js';
import { cartState } from '../shared/cart.js';
import { confirmAction } from '../shared/confirm.js';
import { notify } from '../shared/notify.js';
import { t } from '../shared/i18n.js';

// Order confirmation, after the payment page. The payment is confirmed by the provider's webhook
// (processed by a worker), so the page checks the status a few times until it changes.
const props = defineProps({ id: { type: String, required: true } });
const order = ref(null);
const missing = ref(false);
const busy = ref(false);
const paymentResult = new URLSearchParams(window.location.search).get('payment');
let polls = 0;
let timer = null;
/** Back from the payment page, but the provider's answer has not been processed yet. */
const waiting = () => paymentResult !== null && order.value?.state === 'payment_pending' && order.value?.paymentState === 'pending' && polls < 15;

async function load() {
    try {
        order.value = await api.get(`/api/orders/${props.id}`);
        if (waiting() && polls < 15) {
            polls += 1;
            timer = setTimeout(load, 2000);
        }
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            error.handled = true;
            missing.value = true;
            return;
        }
        handleApiError(error);
    }
}

async function retry() {
    busy.value = true;
    try {
        window.location.href = (await api.post(`/api/orders/${props.id}/payment`)).redirectUrl;
    } catch (error) {
        busy.value = false;
        handleApiError(error);
    }
}

async function cancel() {
    const ok = await confirmAction({ title: t('order.cancel_title'), body: t('order.cancel_body'), confirmLabel: t('order.cancel'), cancelLabel: t('order.keep') });
    if (!ok) return;
    busy.value = true;
    try {
        order.value = await api.post(`/api/orders/${props.id}/cancel`);
        notify({ type: 'success', text: t('order.cancelled') });
    } catch (error) {
        handleApiError(error);
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    cartState.initialCount = 0;
    load();
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <EmptyState v-if="missing" icon="orders" :title="$t('order.not_found')" :text="$t('order.not_found_text')">
            <Button as="a" href="/account/orders" :label="$t('account.nav.orders')" />
        </EmptyState>
        <Skeleton v-else-if="!order" height="20rem" />
        <template v-else>
            <div class="mb-6 text-center">
                <AppIcon :name="order.state === 'cancelled' ? 'error' : 'success'" class="text-5xl" :class="order.state === 'cancelled' ? 'text-red-600' : 'text-green-600'" />
                <h1 class="mt-2 text-2xl font-bold">{{ order.state === 'cancelled' ? $t('order.cancelled_title') : $t('order.thanks') }}</h1>
                <p class="text-surface-600">{{ $t('order.email_sent', { email: order.email }) }}</p>
            </div>
            <template v-if="order.state === 'payment_pending'">
                <Message v-if="order.paymentState === 'failed'" severity="error" class="mb-4" :closable="false">{{ $t('order.payment_failed') }}</Message>
                <Message v-else-if="waiting()" severity="info" class="mb-4" :closable="false"><AppIcon name="loading" class="animate-spin" /> {{ $t('order.payment_processing') }}</Message>
                <Message v-else severity="info" class="mb-4" :closable="false">{{ $t('order.payment_open') }}</Message>
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    <Button v-if="!waiting()" :loading="busy" data-testid="pay-again" @click="retry"><AppIcon name="payment" /> {{ order.paymentState === 'failed' ? $t('order.try_again') : $t('order.pay_now') }}</Button>
                    <Button severity="secondary" text :disabled="busy" :label="$t('order.cancel')" @click="cancel" />
                </div>
            </template>
            <Message v-else-if="order.state === 'paid'" severity="success" class="mb-4" :closable="false">{{ $t('order.payment_received') }}</Message>
            <div class="rounded-lg border border-surface-200 bg-white p-5"><OrderDetails :order="order" /></div>
            <div class="mt-6 flex justify-center gap-3">
                <Button as="a" href="/catalog" severity="secondary" outlined :label="$t('order.continue')" />
            </div>
        </template>
    </div>
</template>
