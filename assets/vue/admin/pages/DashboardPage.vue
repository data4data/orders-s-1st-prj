<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import Chart from 'primevue/chart';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import StoreRequired from '../StoreRequired.vue';
import OrderStateTag from '../orders/OrderStateTag.vue';
import { api, handleApiError } from '../../shared/api.js';
import { t } from '../../shared/i18n.js';
import { formatMoney } from '../../../shared/format.js';

// Admin dashboard (PLAN Phase 6): KPI cards (30 days), revenue per day (14 days), orders by
// status, low stock (store threshold) and the latest orders.
const tenant = inject('tenant');
const data = ref(null);

async function load() {
    if (tenant.mode !== 'store') return;
    data.value = null;
    try {
        data.value = await api.get('/api/admin/dashboard');
    } catch (error) {
        handleApiError(error);
    }
}
watch(() => tenant.store?.publicId, load);
onMounted(load);

const money = (cents) => formatMoney(cents, data.value.currency);
const brand = () => getComputedStyle(document.documentElement).getPropertyValue('--p-primary-500').trim() || '#0f2742';
const kpis = computed(() => data.value && [
    { key: 'revenue', icon: 'orders', value: money(data.value.kpis.revenue) },
    { key: 'paid_orders', icon: 'check', value: data.value.kpis.paidOrders },
    { key: 'average', icon: 'coupons', value: money(data.value.kpis.averageOrder) },
    { key: 'awaiting', icon: 'clock', value: data.value.kpis.awaitingPayment, link: '/orders?state=payment_pending' },
    { key: 'to_fulfil', icon: 'shipping', value: data.value.kpis.toFulfil, link: '/orders?state=paid' },
]);
const revenueChart = computed(() => data.value && {
    labels: data.value.revenueByDay.map((d) => new Date(d.day).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })),
    datasets: [{ label: t('admin.dashboard.revenue'), data: data.value.revenueByDay.map((d) => d.revenue / 100), backgroundColor: brand(), borderRadius: 4 }],
});
const chartOptions = computed(() => data.value && {
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => formatMoney(Math.round(item.raw * 100), data.value.currency) } } },
    scales: { y: { beginAtZero: true, ticks: { callback: (value) => formatMoney(value * 100, data.value.currency) } } },
});
const maxByState = computed(() => Math.max(1, ...(data.value?.ordersByState.map((s) => s.count) ?? [1])));
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ $t('admin.nav.dashboard') }}</h1>
            <div v-if="!data" class="grid gap-4 sm:grid-cols-5"><Skeleton v-for="n in 5" :key="n" height="6rem" /></div>
            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <component :is="kpi.link ? 'router-link' : 'div'" v-for="kpi in kpis" :key="kpi.key" :to="kpi.link" class="rounded-lg border border-surface-200 bg-white p-4" :class="{ 'hover:border-primary-300': kpi.link }" :data-testid="`kpi-${kpi.key}`">
                        <div class="flex items-center gap-2 text-sm text-surface-600"><AppIcon :name="kpi.icon" /> {{ $t(`admin.dashboard.kpi.${kpi.key}`) }}</div>
                        <div class="mt-2 text-2xl font-bold">{{ kpi.value }}</div>
                    </component>
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-lg border border-surface-200 bg-white p-4 lg:col-span-2">
                        <h2 class="mb-3 font-semibold">{{ $t('admin.dashboard.revenue_14') }}</h2>
                        <div class="h-64"><Chart type="bar" :data="revenueChart" :options="chartOptions" class="h-full" /></div>
                    </section>
                    <section class="rounded-lg border border-surface-200 bg-white p-4">
                        <h2 class="mb-3 font-semibold">{{ $t('admin.dashboard.by_state') }}</h2>
                        <ul class="flex flex-col gap-2 text-sm">
                            <li v-for="row in data.ordersByState" :key="row.state">
                                <router-link :to="`/orders?state=${row.state}`" class="flex items-center gap-2" data-router-link>
                                    <span class="w-32 shrink-0"><OrderStateTag :badge="row.badge" :label="row.label" /></span>
                                    <span class="h-2 grow rounded bg-surface-100"><span class="block h-2 rounded bg-primary-400" :style="{ width: `${(row.count / maxByState) * 100}%` }" /></span>
                                    <strong class="w-8 text-right">{{ row.count }}</strong>
                                </router-link>
                            </li>
                        </ul>
                    </section>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="rounded-lg border border-surface-200 bg-white p-4">
                        <h2 class="mb-3 font-semibold">{{ $t('admin.dashboard.latest') }}</h2>
                        <EmptyState v-if="!data.latestOrders.length" icon="orders" :title="$t('admin.orders.empty')" />
                        <ul v-else class="divide-y divide-surface-100 text-sm">
                            <li v-for="order in data.latestOrders" :key="order.id" class="flex flex-wrap items-center gap-2 py-2">
                                <router-link :to="`/orders/${order.id}`" class="w-28 font-semibold text-primary-700 hover:underline" data-router-link>{{ order.number }}</router-link>
                                <span class="grow">{{ order.customer }}</span>
                                <OrderStateTag :badge="order.badge" :label="order.stateLabel" />
                                <strong class="w-24 text-right">{{ money(order.totalGross) }}</strong>
                            </li>
                        </ul>
                    </section>
                    <section class="rounded-lg border border-surface-200 bg-white p-4">
                        <h2 class="mb-3 font-semibold">{{ $t('admin.dashboard.low_stock', { threshold: data.lowStockThreshold }) }}</h2>
                        <EmptyState v-if="!data.lowStock.length" icon="success" :title="$t('admin.dashboard.stock_ok')" />
                        <ul v-else class="divide-y divide-surface-100 text-sm">
                            <li v-for="row in data.lowStock" :key="row.sku" class="flex items-center gap-2 py-2">
                                <span class="grow">{{ row.product }} <span class="text-surface-500">{{ row.pack }} · {{ row.sku }}</span></span>
                                <strong :class="row.available === 0 ? 'text-red-700' : 'text-amber-700'">{{ row.available }}</strong>
                            </li>
                        </ul>
                    </section>
                </div>
            </template>
        </div>
    </StoreRequired>
</template>
