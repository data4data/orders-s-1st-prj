<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import StoreRequired from '../../StoreRequired.vue';
import OrderStateTag from '../../orders/OrderStateTag.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { t } from '../../../shared/i18n.js';
import { formatMoney } from '../../../../shared/format.js';

// Admin → Orders: newest first, filter by status (kept in the address), search by number, email or name.
const tenant = inject('tenant');
const route = useRoute();
const router = useRouter();
const rows = ref([]);
const total = ref(0);
const states = ref([]);
const loading = ref(false);
const search = ref(route.query.q ?? '');
const state = ref(route.query.state ?? null);
const page = ref(Number(route.query.page ?? 1));
const perPage = 20;

async function load() {
    if (tenant.mode !== 'store') return;
    loading.value = true;
    const query = { ...(state.value ? { state: state.value } : {}), ...(search.value ? { q: search.value } : {}), ...(page.value > 1 ? { page: page.value } : {}) };
    router.replace({ query });
    try {
        const result = await api.get(`/api/admin/orders?${new URLSearchParams(query)}`);
        rows.value = result.items;
        total.value = result.total;
        states.value = [{ value: null, label: t('admin.orders.all_states') }, ...result.states.map((s) => ({ value: s.value, label: t(s.label) }))];
    } catch (error) {
        handleApiError(error);
    } finally {
        loading.value = false;
    }
}

let debounce;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { page.value = 1; load(); }, 300); });
watch(state, () => { page.value = 1; load(); });
watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">{{ $t('admin.orders.title') }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Select v-model="state" :options="states" option-label="label" option-value="value" class="min-w-48" :aria-label="$t('admin.orders.status')">
                        <template #dropdownicon><AppIcon name="chevron-down" /></template>
                    </Select>
                    <span class="flex items-center gap-2 rounded-md border border-surface-300 bg-white px-3"><AppIcon name="search" class="text-surface-400" /><InputText v-model="search" :placeholder="$t('admin.orders.search')" class="!border-0 !shadow-none" :aria-label="$t('admin.orders.search')" /></span>
                </div>
            </div>
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable :value="rows" lazy paginator :rows="perPage" :total-records="total" :first="(page - 1) * perPage" :loading="loading" data-key="id" @page="(e) => { page = e.page + 1; load(); }">
                    <template #empty><EmptyState icon="orders" :title="$t('admin.orders.empty')" :text="$t('admin.orders.empty_text')" /></template>
                    <Column :header="$t('admin.orders.number')">
                        <template #body="{ data }"><router-link :to="`/orders/${data.id}`" class="font-semibold text-primary-700 hover:underline" data-router-link>{{ data.number }}</router-link></template>
                    </Column>
                    <Column :header="$t('admin.orders.placed')">
                        <template #body="{ data }">{{ new Date(data.placedAt).toLocaleString() }}</template>
                    </Column>
                    <Column :header="$t('admin.orders.customer')">
                        <template #body="{ data }">
                            <div>{{ data.customer }}<span v-if="data.company" class="text-surface-500">{{ `, ${data.company}` }}</span></div>
                            <div class="text-xs text-surface-500">{{ data.email }} <Tag v-if="data.guest" severity="secondary" :value="$t('admin.orders.guest')" class="ml-1 !text-xs" /></div>
                        </template>
                    </Column>
                    <Column :header="$t('admin.orders.status')"><template #body="{ data }"><OrderStateTag :badge="data.badge" :label="data.stateLabel" /></template></Column>
                    <Column :header="$t('admin.orders.items')" field="itemCount" />
                    <Column :header="$t('admin.orders.total')"><template #body="{ data }"><strong>{{ formatMoney(data.totalGross, data.currency) }}</strong></template></Column>
                </DataTable>
            </div>
        </div>
    </StoreRequired>
</template>
