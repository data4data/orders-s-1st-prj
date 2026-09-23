<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import Skeleton from 'primevue/skeleton';
import EmptyState from '../../../components/EmptyState.vue';
import StoresTab from './StoresTab.vue';
import TaxTab from './TaxTab.vue';
import StaffUsersTab from './StaffUsersTab.vue';
import SystemTab from './SystemTab.vue';
import { api, handleApiError } from '../../../shared/api.js';

// Platform (super-admins only): stores, countries & VAT rates, tax categories, staff users, system.
const props = defineProps({ section: { type: String, default: 'stores' } });
const user = inject('user');
const router = useRouter();
const overview = ref(null);
const SECTIONS = { stores: StoresTab, tax: TaxTab, staff: StaffUsersTab, system: SystemTab };
const current = computed({
    get: () => (SECTIONS[props.section] ? props.section : 'stores'),
    set: (value) => router.push(`/platform/${value}`),
});

onMounted(async () => {
    if (!user.superAdmin) return;
    try {
        overview.value = await api.get('/api/admin/platform');
    } catch (error) {
        handleApiError(error);
    }
});
</script>

<template>
    <div v-if="!user.superAdmin" class="rounded-lg border border-surface-200 bg-white p-6">
        <EmptyState icon="lock" :title="$t('admin.platform.only_super_admin')" />
    </div>
    <div v-else class="flex flex-col gap-4">
        <h1 class="text-xl font-semibold">{{ $t('admin.nav.platform') }}</h1>
        <Tabs v-model:value="current">
            <TabList>
                <Tab v-for="(component, key) in SECTIONS" :key="key" :value="key">{{ $t(`admin.platform.tab.${key}`) }}</Tab>
            </TabList>
        </Tabs>
        <Skeleton v-if="!overview && current !== 'system'" height="16rem" />
        <component :is="SECTIONS[current]" v-else :overview="overview" @update="(value) => { overview = value }" />
    </div>
</template>
