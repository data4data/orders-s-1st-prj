<script setup>
import { computed, onMounted, provide, reactive, ref } from 'vue';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import AppIcon from '../components/AppIcon.vue';
import SystemLayer from '../components/SystemLayer.vue';
import StoreSwitcher from './StoreSwitcher.vue';
import { api, handleApiError } from '../shared/api.js';
import { t } from '../shared/i18n.js';

// Admin shell (docs/diagrams/pages.html → Admin pages): dark sidebar with the main navigation,
// Settings and (super-admin only) Platform; top bar with the store switcher and the user menu.
const props = defineProps({
    user: { type: Object, required: true },
    devTools: { type: Boolean, default: false },
});

const tenant = reactive({ mode: 'none', store: null, readOnly: false });
provide('tenant', tenant);
provide('user', props.user);
onMounted(async () => {
    try {
        Object.assign(tenant, await api.get('/api/admin/stores/current'));
    } catch (error) {
        handleApiError(error);
    }
});

const nav = computed(() => [
    { to: '/', icon: 'dashboard', label: 'admin.nav.dashboard' },
    { to: '/orders', icon: 'orders', label: 'admin.nav.orders' },
    { to: '/catalog/products', icon: 'catalog', label: 'admin.nav.catalog', children: [
        { to: '/catalog/products', label: 'admin.nav.products' },
        { to: '/catalog/categories', label: 'admin.nav.categories' },
        { to: '/catalog/attributes', label: 'admin.nav.attributes' },
    ] },
    { to: '/customers', icon: 'customers', label: 'admin.nav.customers' },
    { to: '/coupons', icon: 'coupons', label: 'admin.nav.coupons' },
]);

const userMenu = ref();
const logOut = async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    await fetch(`/api/admin/logout?_csrf_token=${encodeURIComponent(token)}`, { method: 'POST', credentials: 'same-origin' });
    window.location.href = '/login';
};
const userItems = computed(() => [{ label: t('admin.user.log_out'), command: logOut }]);
const initials = computed(() => `${props.user.firstName[0] ?? ''}${props.user.lastName[0] ?? ''}`);
</script>

<template>
    <div class="grid min-h-screen grid-cols-1 md:grid-cols-[15rem_1fr]">
        <aside class="bg-[#1f2430] px-3 py-4 text-sm text-slate-300">
            <div class="mb-4 flex items-center gap-2 px-2 text-base font-extrabold text-white"><AppIcon name="brand" class="text-sky-400" /> {{ $t('admin.brand') }}</div>
            <nav class="flex flex-col gap-0.5">
                <template v-for="item in nav" :key="item.label">
                    <router-link :to="item.to" data-router-link class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-white/10" active-class="bg-white/15 font-semibold text-white" :exact-active-class="item.to === '/' ? 'bg-white/15 font-semibold text-white' : ''">
                        <AppIcon :name="item.icon" /> {{ $t(item.label) }}
                    </router-link>
                    <router-link v-for="child in item.children ?? []" :key="child.to" :to="child.to" data-router-link class="rounded-md py-1 pl-9 pr-2 text-slate-400 hover:bg-white/10" active-class="text-white">{{ $t(child.label) }}</router-link>
                </template>
                <div class="my-3 border-t border-white/10" />
                <router-link to="/settings" data-router-link class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-white/10" active-class="bg-white/15 font-semibold text-white"><AppIcon name="settings" /> {{ $t('admin.nav.settings') }}</router-link>
                <template v-if="user.superAdmin">
                    <div class="px-2 pb-1 pt-3 text-[0.68rem] uppercase tracking-wider text-slate-500">{{ $t('admin.nav.super_admin') }}</div>
                    <router-link to="/platform" data-router-link class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-white/10" active-class="bg-white/15 font-semibold text-white"><AppIcon name="platform" /> {{ $t('admin.nav.platform') }}</router-link>
                </template>
                <router-link v-if="devTools" to="/ui-kit" data-router-link class="mt-3 flex items-center gap-2 rounded-md px-2 py-1.5 text-slate-400 hover:bg-white/10" active-class="bg-white/15 text-white"><AppIcon name="info" /> {{ $t('admin.nav.ui_kit') }}</router-link>
            </nav>
        </aside>
        <div class="flex min-w-0 flex-col">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 bg-white px-5 py-2.5">
                <StoreSwitcher :super-admin="user.superAdmin" />
                <Button text severity="secondary" aria-haspopup="true" :aria-label="$t('admin.user.menu')" @click="userMenu.toggle($event)">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-contrast">{{ initials }}</span>
                    <span class="text-surface-800">{{ user.firstName }}</span><AppIcon name="chevron-down" />
                </Button>
                <Menu ref="userMenu" :model="userItems" popup />
            </header>
            <main class="p-5"><router-view /></main>
        </div>
        <SystemLayer />
    </div>
</template>
