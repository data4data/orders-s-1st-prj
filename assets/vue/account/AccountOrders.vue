<script setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Paginator from 'primevue/paginator';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import OrderDetails from '../customer/OrderDetails.vue';
import { api, handleApiError } from '../shared/api.js';
import { formatMoney } from '../../shared/format.js';

// Account → Orders: the customer's placed orders, newest first, with the details in a dialog.
const list = ref(null);
const page = ref(1);
const open = ref(null);
const SEVERITY = { payment_pending: 'warn', paid: 'success', processing: 'info', shipped: 'info', delivered: 'success', cancelled: 'danger', refunded: 'secondary' };

async function load() {
    try {
        list.value = await api.get(`/api/account/orders?page=${page.value}`);
    } catch (error) {
        handleApiError(error);
    }
}
async function show(id) {
    try {
        open.value = await api.get(`/api/account/orders/${id}`);
    } catch (error) {
        handleApiError(error);
    }
}
onMounted(load);
</script>

<template>
    <div>
        <Skeleton v-if="!list" height="10rem" />
        <EmptyState v-else-if="list.total === 0" icon="orders" :title="$t('account.orders_empty')" :text="$t('account.orders_empty_text')">
            <Button as="a" href="/catalog" :label="$t('cart.browse')" />
        </EmptyState>
        <template v-else>
            <ul class="divide-y divide-surface-200">
                <li v-for="order in list.items" :key="order.id" class="flex flex-wrap items-center gap-3 py-3">
                    <strong class="min-w-32">{{ order.number }}</strong>
                    <span class="text-sm text-surface-600">{{ new Date(order.placedAt).toLocaleDateString() }}</span>
                    <Tag :severity="SEVERITY[order.state] ?? 'secondary'" :value="$t(order.stateLabel)" />
                    <span class="grow text-right font-semibold">{{ formatMoney(order.totals.totalGross, order.currency) }}</span>
                    <Button text size="small" :aria-label="$t('account.view_order', { number: order.number })" @click="show(order.id)">{{ $t('account.details') }} <AppIcon name="chevron-right" /></Button>
                </li>
            </ul>
            <Paginator v-if="list.total > 10" :rows="10" :total-records="list.total" :first="(page - 1) * 10" @page="(e) => { page = e.page + 1; load(); }" />
        </template>
        <Dialog :visible="!!open" modal :header="open ? $t('order.number', { number: open.number }) : ''" class="w-full max-w-2xl" @update:visible="open = null">
            <template #closeicon><AppIcon name="close" /></template>
            <OrderDetails v-if="open" :order="open" />
        </Dialog>
    </div>
</template>
