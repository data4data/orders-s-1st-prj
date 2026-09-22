<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import StoreRequired from '../../StoreRequired.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { formatMoney } from '../../../../shared/format.js';

const tenant = inject('tenant');
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const page = ref(1);
const perPage = 20;

async function load() {
    if (tenant.mode !== 'store') return;
    loading.value = true;
    try {
        const result = await api.get(`/api/admin/catalog/products?q=${encodeURIComponent(search.value)}&page=${page.value}&perPage=${perPage}`);
        rows.value = result.items;
        total.value = result.total;
    } catch (error) {
        handleApiError(error);
    } finally {
        loading.value = false;
    }
}

let debounce;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { page.value = 1; load(); }, 300); });
watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">{{ $t('admin.catalog.products.title') }}</h1>
                <div class="flex flex-wrap gap-2">
                    <span class="flex items-center gap-2 rounded-md border border-surface-300 bg-white px-3"><AppIcon name="search" class="text-surface-400" /><InputText v-model="search" :placeholder="$t('admin.catalog.search')" class="!border-0 !shadow-none" :aria-label="$t('admin.catalog.search')" /></span>
                    <router-link v-slot="{ navigate }" to="/catalog/products/new" custom><Button @click="navigate"><AppIcon name="add" /> {{ $t('admin.catalog.products.new') }}</Button></router-link>
                </div>
            </div>
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable :value="rows" lazy paginator :rows="perPage" :total-records="total" :first="(page - 1) * perPage" :loading="loading" data-key="publicId" @page="(e) => { page = e.page + 1; load(); }">
                    <template #empty><EmptyState :title="$t('admin.catalog.products.empty')" :text="$t('admin.catalog.products.empty_text')" /></template>
                    <Column :header="$t('admin.catalog.products.name')">
                        <template #body="{ data }"><router-link :to="`/catalog/products/${data.publicId}`" class="font-semibold text-primary-700 hover:underline" data-router-link>{{ data.name }}</router-link><div class="text-xs text-surface-500">{{ `/p/${data.slug}` }}</div></template>
                    </Column>
                    <Column field="packs" :header="$t('admin.catalog.products.packs')" />
                    <Column :header="$t('admin.catalog.products.price')">
                        <template #body="{ data }"><span v-if="data.fromGross !== null">{{ formatMoney(data.fromGross, data.currency) }}<template v-if="data.toGross !== data.fromGross">{{ ` – ${formatMoney(data.toGross, data.currency)}` }}</template></span></template>
                    </Column>
                    <Column :header="$t('admin.catalog.products.stock')">
                        <template #body="{ data }">{{ data.stock }} <Tag v-if="data.lowStock" severity="warn" :value="$t('admin.catalog.products.low_stock')" class="ml-1" /></template>
                    </Column>
                    <Column :header="$t('admin.catalog.products.status')">
                        <template #body="{ data }"><Tag :severity="data.isActive ? 'success' : 'secondary'" :value="data.isActive ? $t('admin.catalog.active') : $t('admin.catalog.inactive')" /></template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </StoreRequired>
</template>
