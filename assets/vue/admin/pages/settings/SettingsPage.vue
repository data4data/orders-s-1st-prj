<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import Skeleton from 'primevue/skeleton';
import StoreRequired from '../../StoreRequired.vue';
import ProfileSettings from './ProfileSettings.vue';
import DomainSettings from './DomainSettings.vue';
import ShippingSettings from './ShippingSettings.vue';
import PaymentSettings from './PaymentSettings.vue';
import StaffSettings from './StaffSettings.vue';
import NotificationSettings from './NotificationSettings.vue';
import { api, handleApiError } from '../../../shared/api.js';

// Settings of the selected store (managers and owners), one tab per section: /settings/<section>.
const props = defineProps({ section: { type: String, default: 'profile' } });
const tenant = inject('tenant');
const router = useRouter();
const settings = ref(null);
const SECTIONS = { profile: ProfileSettings, domains: DomainSettings, shipping: ShippingSettings, payment: PaymentSettings, staff: StaffSettings, notifications: NotificationSettings };
const current = computed({
    get: () => (SECTIONS[props.section] ? props.section : 'profile'),
    set: (value) => router.push(`/settings/${value}`),
});

async function load() {
    if (tenant.mode !== 'store') return;
    settings.value = null;
    try {
        settings.value = await api.get('/api/admin/settings');
    } catch (error) {
        handleApiError(error);
    }
}
watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <h1 class="text-xl font-semibold">{{ $t('admin.nav.settings') }}</h1>
            <Tabs v-model:value="current">
                <TabList>
                    <Tab v-for="(component, key) in SECTIONS" :key="key" :value="key">{{ $t(`admin.settings.tab.${key}`) }}</Tab>
                </TabList>
            </Tabs>
            <Skeleton v-if="!settings" height="16rem" />
            <component :is="SECTIONS[current]" v-else :settings="settings" @update="(value) => { settings = value }" />
        </div>
    </StoreRequired>
</template>
