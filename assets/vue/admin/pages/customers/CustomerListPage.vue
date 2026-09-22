<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import Badge from 'primevue/badge';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import StoreRequired from '../../StoreRequired.vue';
import ContactMessages from './ContactMessages.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { formatMoney } from '../../../../shared/format.js';

// Admin → Customers: registered customers of the store, and the Contact messages tab.
const tenant = inject('tenant');
const route = useRoute();
const router = useRouter();
const tab = ref(route.query.tab === 'messages' ? 'messages' : 'customers');
const rows = ref([]);
const total = ref(0);
const unread = ref(0);
const loading = ref(false);
const search = ref('');
const page = ref(1);

async function load() {
    if (tenant.mode !== 'store') return;
    loading.value = true;
    try {
        const [customers, messages] = await Promise.all([
            api.get(`/api/admin/customers?q=${encodeURIComponent(search.value)}&page=${page.value}`),
            api.get('/api/admin/contact-messages?unread=1'),
        ]);
        rows.value = customers.items;
        total.value = customers.total;
        unread.value = messages.unread;
    } catch (error) {
        handleApiError(error);
    } finally {
        loading.value = false;
    }
}

let debounce;
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { page.value = 1; load(); }, 300); });
watch(tab, (value) => router.replace({ query: value === 'messages' ? { tab: 'messages' } : {} }));
watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ $t('admin.nav.customers') }}</h1>
            <Tabs v-model:value="tab">
                <TabList>
                    <Tab value="customers">{{ $t('admin.customers.tab_customers') }}</Tab>
                    <Tab value="messages">{{ $t('admin.customers.tab_messages') }} <Badge v-if="unread" :value="unread" severity="warn" class="ml-1" /></Tab>
                </TabList>
            </Tabs>
            <template v-if="tab === 'customers'">
                <span class="flex max-w-md items-center gap-2 rounded-md border border-surface-300 bg-white px-3"><AppIcon name="search" class="text-surface-400" /><InputText v-model="search" :placeholder="$t('admin.customers.search')" class="!border-0 !shadow-none" :aria-label="$t('admin.customers.search')" /></span>
                <div class="rounded-lg border border-surface-200 bg-white">
                    <DataTable :value="rows" lazy paginator :rows="20" :total-records="total" :first="(page - 1) * 20" :loading="loading" data-key="id" @page="(e) => { page = e.page + 1; load(); }">
                        <template #empty><EmptyState icon="customers" :title="$t('admin.customers.empty')" :text="$t('admin.customers.empty_text')" /></template>
                        <Column :header="$t('admin.customers.name')">
                            <template #body="{ data }">
                                <router-link :to="`/customers/${data.id}`" class="font-semibold text-primary-700 hover:underline" data-router-link>{{ data.name }}</router-link>
                                <div class="text-xs text-surface-500">{{ data.email }}</div>
                            </template>
                        </Column>
                        <Column :header="$t('admin.customers.company')"><template #body="{ data }">{{ data.company }}<div class="text-xs text-surface-500">{{ data.city }}</div></template></Column>
                        <Column :header="$t('admin.customers.orders')" field="orders" />
                        <Column :header="$t('admin.customers.spent')"><template #body="{ data }">{{ formatMoney(data.spent, tenant.store.currencyCode ?? 'EUR') }}</template></Column>
                        <Column :header="$t('admin.customers.since')"><template #body="{ data }">{{ new Date(data.createdAt).toLocaleDateString() }}</template></Column>
                    </DataTable>
                </div>
            </template>
            <ContactMessages v-else @read="unread = Math.max(0, unread - 1)" />
        </div>
    </StoreRequired>
</template>
