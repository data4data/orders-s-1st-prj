<script setup>
import { ref } from 'vue';
import AppIcon from '../components/AppIcon.vue';
import MiniCart from './MiniCart.vue';
import { cartState, itemCount } from '../shared/cart.js';

// Storefront header (Vue). The Bootstrap twin is templates/bootstrap/_header.html.twig: keep both in sync.
const props = defineProps({
    layout: { type: Object, required: true },
    scheme: { type: String, default: 'https' },
});
const shopsOpen = ref(false);
cartState.initialCount = props.layout.cartItemCount;
</script>

<template>
    <header>
        <div class="bg-[var(--brand-primary)] text-[var(--brand-on-primary)]">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-1 text-sm">
                <span><template v-if="layout.store.contactEmail"><AppIcon name="mail" /> {{ layout.store.contactEmail }}</template></span>
                <div v-if="layout.otherShops.length" class="relative">
                    <button type="button" class="flex items-center gap-1" :aria-expanded="shopsOpen" @click="shopsOpen = !shopsOpen">
                        <AppIcon name="store" /> {{ $t('layout.other_shops') }} <AppIcon name="chevron-down" />
                    </button>
                    <ul v-if="shopsOpen" class="absolute right-0 z-50 mt-1 min-w-56 rounded-md border border-surface-200 bg-white py-1 text-surface-800 shadow-lg">
                        <li v-for="shop in layout.otherShops" :key="shop.code">
                            <a class="block px-3 py-1.5 hover:bg-surface-100" :href="`${scheme}://${shop.host}/`">{{ shop.name }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <nav class="border-b border-surface-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
                <a href="/" class="flex items-center gap-2 text-xl font-extrabold text-[var(--brand-primary)]">
                    <img v-if="layout.store.logoUrl" :src="layout.store.logoUrl" :alt="layout.store.name" class="h-8">
                    <template v-else><AppIcon name="brand" class="text-[var(--brand-accent)]" /><span>{{ layout.store.name }}</span></template>
                </a>
                <ul v-if="layout.categories.length" class="flex gap-4 font-semibold">
                    <li v-for="category in layout.categories" :key="category.slug"><a :href="`/c/${category.slug}`">{{ category.name }}</a></li>
                </ul>
                <form class="flex min-w-48 grow items-center gap-2 rounded-md border border-surface-300 px-3 py-1.5 focus-within:border-primary-300 focus-within:ring-4 focus-within:ring-primary-100" role="search" action="/search" method="get">
                    <AppIcon name="search" class="text-surface-500" />
                    <input class="grow outline-none" type="search" name="q" :placeholder="$t('layout.search_placeholder')" :aria-label="$t('layout.search')">
                </form>
                <div class="flex items-center gap-4 text-xl">
                    <a v-if="layout.customerName" href="/account" class="flex items-center gap-1 text-base font-semibold" :aria-label="$t('layout.account')"><AppIcon name="account" class="text-xl" /> {{ layout.customerName }}</a>
                    <a v-else href="/login" class="flex items-center gap-1 text-base font-semibold"><AppIcon name="account" class="text-xl" /> {{ $t('layout.log_in') }}</a>
                    <a href="/cart" class="relative" :aria-label="$t('layout.cart')" data-testid="header-cart" @click.prevent="cartState.drawerOpen = true">
                        <AppIcon name="cart" />
                        <span v-if="itemCount() > 0" class="absolute -right-2 -top-2 rounded-full bg-[var(--brand-accent)] px-1.5 text-xs font-bold text-[var(--brand-on-accent)]" data-testid="cart-count">{{ itemCount() }}</span>
                    </a>
                </div>
            </div>
        </nav>
        <MiniCart />
    </header>
</template>
