<script setup>
import Tag from 'primevue/tag';
import AppIcon from '../components/AppIcon.vue';
import { formatMoney } from '../../shared/format.js';

// One product in the catalog grid or list (docs/diagrams/pages.html → Catalog & product).
defineProps({
    product: { type: Object, required: true },
    layout: { type: String, default: 'grid' },
});
const STOCK_CLASS = { in_stock: 'text-green-700', low_stock: 'text-amber-600', out_of_stock: 'text-surface-500' };
</script>

<template>
    <a :href="`/p/${product.slug}`" class="group flex rounded-lg border border-surface-200 bg-white p-3 transition hover:border-primary-300 hover:shadow-md" :class="layout === 'grid' ? 'flex-col' : 'flex-row gap-4'">
        <div class="aspect-square overflow-hidden rounded-md bg-surface-100" :class="layout === 'grid' ? 'w-full' : 'w-32 shrink-0'">
            <img v-if="product.imageUrl" :src="product.imageUrl" :alt="product.imageAlt ?? product.name" class="h-full w-full object-cover" loading="lazy">
        </div>
        <div class="flex grow flex-col gap-1" :class="layout === 'grid' ? 'mt-3' : ''">
            <div class="font-semibold text-surface-900 group-hover:text-primary-700">{{ product.name }}</div>
            <div class="flex flex-wrap gap-1">
                <Tag v-for="badge in product.badges" :key="badge" :value="badge" severity="secondary" class="!text-xs" />
            </div>
            <div v-if="product.fromPrice" class="mt-auto pt-2">
                <div class="text-lg font-extrabold text-surface-900">
                    {{ product.multiplePacks ? $t('catalog.from', { price: formatMoney(product.fromPrice.gross, product.fromPrice.currency) }) : formatMoney(product.fromPrice.gross, product.fromPrice.currency) }}
                    <span class="text-xs font-normal text-surface-500">{{ $t('catalog.incl_vat') }} · {{ product.fromPackName }}</span>
                </div>
                <div class="text-xs text-surface-500">
                    {{ $t('catalog.excl_vat', { price: formatMoney(product.fromPrice.net, product.fromPrice.currency) }) }}
                    <template v-if="product.fromPrice.perLitre"> · {{ $t('catalog.per_litre', { price: formatMoney(product.fromPrice.perLitre, product.fromPrice.currency) }) }}</template>
                </div>
            </div>
            <div class="flex items-center gap-1 text-xs font-semibold" :class="STOCK_CLASS[product.stock]">
                <AppIcon :name="product.stock === 'out_of_stock' ? 'close' : 'check'" /> {{ $t(`catalog.stock.${product.stock}`) }}
            </div>
        </div>
    </a>
</template>
