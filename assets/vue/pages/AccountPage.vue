<script setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../components/AppIcon.vue';
import AccountOrders from '../account/AccountOrders.vue';
import AccountAddresses from '../account/AccountAddresses.vue';
import AccountProfile from '../account/AccountProfile.vue';
import OrderDetails from '../customer/OrderDetails.vue';
import { addressLine } from '../customer/address.js';
import { api, handleApiError } from '../shared/api.js';

// Customer account (decision #41): Dashboard, Orders, Addresses, Profile & security. Each section
// has its own address (/account/orders…) so it can be bookmarked and reloaded.
const props = defineProps({
    section: { type: String, default: 'dashboard' },
    logoutToken: { type: String, required: true },
});
const SECTIONS = [
    { key: 'dashboard', icon: 'dashboard', path: '/account' },
    { key: 'orders', icon: 'orders', path: '/account/orders' },
    { key: 'addresses', icon: 'address', path: '/account/addresses' },
    { key: 'profile', icon: 'lock', path: '/account/profile' },
];
const current = ref(props.section);
const account = ref(null);

function go(item) {
    current.value = item.key;
    window.history.pushState({}, '', item.path);
}
window.addEventListener('popstate', () => {
    current.value = SECTIONS.find((s) => s.path === window.location.pathname)?.key ?? 'dashboard';
});

onMounted(async () => {
    try {
        account.value = await api.get('/api/account');
    } catch (error) {
        handleApiError(error);
    }
});
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold">{{ account ? $t('account.hello', { name: account.profile.firstName }) : $t('account.title') }}</h1>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf_token" :value="logoutToken">
                <Button type="submit" severity="secondary" text><AppIcon name="logout" /> {{ $t('account.log_out') }}</Button>
            </form>
        </div>
        <div class="grid gap-6 md:grid-cols-4">
            <nav class="flex gap-1 overflow-x-auto md:flex-col" :aria-label="$t('account.title')">
                <a
                    v-for="item in SECTIONS" :key="item.key" :href="item.path" class="flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2"
                    :class="current === item.key ? 'bg-primary-50 font-semibold text-primary-800' : 'hover:bg-surface-100'" :aria-current="current === item.key ? 'page' : undefined"
                    @click.prevent="go(item)"
                ><AppIcon :name="item.icon" /> {{ $t(`account.nav.${item.key}`) }}</a>
            </nav>
            <div class="md:col-span-3">
                <Skeleton v-if="!account" height="14rem" />
                <template v-else-if="current === 'dashboard'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-surface-200 bg-white p-4">
                            <h2 class="mb-2 font-bold">{{ $t('account.default_billing') }}</h2>
                            <p class="text-sm">{{ addressLine(account.addresses.find((a) => a.isDefaultBilling) ?? account.addresses[0]) }}</p>
                        </div>
                        <div class="rounded-lg border border-surface-200 bg-white p-4">
                            <h2 class="mb-2 font-bold">{{ $t('account.default_shipping') }}</h2>
                            <p class="text-sm">{{ addressLine(account.addresses.find((a) => a.isDefaultShipping) ?? account.addresses[0]) }}</p>
                        </div>
                    </div>
                    <h2 class="mb-2 mt-6 font-bold">{{ $t('account.recent_orders') }}</h2>
                    <p v-if="account.recentOrders.length === 0" class="text-surface-600">{{ $t('account.orders_empty') }}</p>
                    <div v-for="order in account.recentOrders" :key="order.id" class="mb-3 rounded-lg border border-surface-200 bg-white p-4"><OrderDetails :order="order" /></div>
                </template>
                <AccountOrders v-else-if="current === 'orders'" />
                <AccountAddresses v-else-if="current === 'addresses'" v-model:addresses="account.addresses" :countries="account.countries" />
                <AccountProfile v-else :profile="account.profile" @saved="(p) => Object.assign(account.profile, p)" />
            </div>
        </div>
    </div>
</template>
