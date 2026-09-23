<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import StoreRequired from '../../StoreRequired.vue';
import OrderStateTag from '../../orders/OrderStateTag.vue';
import { addressLine } from '../../../customer/address.js';
import { api, handleApiError, ApiError } from '../../../shared/api.js';
import { formatMoney } from '../../../../shared/format.js';

// Admin → Customers → one customer: contact data, address book and orders.
const props = defineProps({ id: { type: String, required: true } });
const tenant = inject('tenant');
const customer = ref(null);
const missing = ref(false);

async function load() {
    if (tenant.mode !== 'store') return;
    try {
        customer.value = await api.get(`/api/admin/customers/${props.id}`);
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            error.handled = true;
            missing.value = true;
            return;
        }
        handleApiError(error);
    }
}
watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <EmptyState v-if="missing" icon="customers" :title="$t('admin.customers.not_found')" />
        <Skeleton v-else-if="!customer" height="18rem" />
        <div v-else class="flex flex-col gap-4">
            <router-link to="/customers" class="flex items-center gap-1 text-sm text-surface-600 hover:underline" data-router-link><AppIcon name="back" /> {{ $t('admin.customers.back') }}</router-link>
            <h1 class="text-xl font-semibold">{{ customer.name }}</h1>
            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm">
                    <h2 class="mb-2 font-semibold">{{ $t('admin.customers.contact') }}</h2>
                    <a :href="`mailto:${customer.email}`" class="text-primary-700 underline">{{ customer.email }}</a>
                    <div v-if="customer.phone">{{ customer.phone }}</div>
                    <div class="mt-2 text-surface-600">{{ $t('admin.customers.stats', { orders: customer.orders, spent: formatMoney(customer.spent, tenant.store.currencyCode ?? 'EUR') }) }}</div>
                    <div class="text-surface-500">{{ $t('admin.customers.member_since', { date: new Date(customer.createdAt).toLocaleDateString() }) }}</div>
                </section>
                <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm lg:col-span-2">
                    <h2 class="mb-2 font-semibold">{{ $t('account.nav.addresses') }}</h2>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li v-for="address in customer.addresses" :key="address.id" class="rounded-md border border-surface-100 p-2">
                            <div class="font-semibold">{{ address.firstName }} {{ address.lastName }}<span v-if="address.company">{{ `, ${address.company}` }}</span></div>
                            <div>{{ addressLine(address) }}</div>
                            <div class="text-xs text-surface-500">{{ [address.isDefaultBilling ? $t('account.default_billing') : null, address.isDefaultShipping ? $t('account.default_shipping') : null].filter(Boolean).join(' · ') }}</div>
                        </li>
                    </ul>
                </section>
            </div>
            <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm">
                <h2 class="mb-2 font-semibold">{{ $t('admin.nav.orders') }}</h2>
                <p v-if="!customer.orderList.length" class="text-surface-500">{{ $t('admin.orders.empty') }}</p>
                <ul v-else class="divide-y divide-surface-100">
                    <li v-for="order in customer.orderList" :key="order.id" class="flex flex-wrap items-center gap-3 py-2">
                        <router-link :to="`/orders/${order.id}`" class="w-28 font-semibold text-primary-700 hover:underline" data-router-link>{{ order.number }}</router-link>
                        <span class="grow text-surface-600">{{ new Date(order.placedAt).toLocaleString() }}</span>
                        <OrderStateTag :badge="order.badge" :label="order.stateLabel" />
                        <strong class="w-24 text-right">{{ formatMoney(order.totalGross, order.currency) }}</strong>
                    </li>
                </ul>
            </section>
        </div>
    </StoreRequired>
</template>
