<script setup>
import { computed, onMounted, ref } from 'vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Tag from 'primevue/tag';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../components/AppIcon.vue';
import CatalogBreadcrumbs from '../catalog/CatalogBreadcrumbs.vue';
import ProductCard from '../catalog/ProductCard.vue';
import { api, handleApiError } from '../shared/api.js';
import { notify } from '../shared/notify.js';
import { t } from '../shared/i18n.js';
import { formatMoney } from '../../shared/format.js';

// Product page (docs/diagrams/pages.html → Catalog & product): pack-size selector with price per
// litre, gross price large and net small (decision #45), stock, tabs and related products.
const props = defineProps({ slug: { type: String, required: true } });

const product = ref(null);
const selectedId = ref(null);
const quantity = ref(1);
const activeImage = ref(0);

const selected = computed(() => product.value?.variants.find((v) => v.publicId === selectedId.value) ?? null);
const images = computed(() => {
    if (!product.value) return [];
    const specific = product.value.images.filter((i) => i.variantPublicId === selectedId.value);
    return specific.length ? specific : product.value.images.filter((i) => i.variantPublicId === null);
});
const money = (cents) => formatMoney(cents, product.value.currency);
const STOCK_CLASS = { in_stock: 'text-green-700', low_stock: 'text-amber-600', out_of_stock: 'text-red-700' };

function select(variant) {
    selectedId.value = variant.publicId;
    activeImage.value = 0;
    quantity.value = Math.min(Math.max(quantity.value, 1), Math.max(variant.available, 1));
}

onMounted(async () => {
    try {
        product.value = await api.get(`/api/products/${encodeURIComponent(props.slug)}`);
        const firstAvailable = product.value.variants.find((v) => v.available > 0) ?? product.value.variants[0];
        select(firstAvailable);
    } catch (error) {
        handleApiError(error);
    }
});

const addToCart = () => notify({ type: 'info', text: t('product.cart_coming') });
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-6">
        <div v-if="!product" class="grid gap-8 md:grid-cols-2"><Skeleton height="24rem" /><div><Skeleton width="70%" height="2rem" /><Skeleton class="mt-4" height="6rem" /></div></div>
        <template v-else>
            <CatalogBreadcrumbs :items="product.breadcrumbs" :current="product.name" />
            <div class="mt-4 grid gap-8 md:grid-cols-2">
                <div>
                    <div class="aspect-square overflow-hidden rounded-lg border border-surface-200 bg-surface-100">
                        <img v-if="images[activeImage]" :src="images[activeImage].url" :alt="images[activeImage].alt" class="h-full w-full object-cover">
                    </div>
                    <div v-if="images.length > 1" class="mt-2 grid grid-cols-5 gap-2">
                        <button v-for="(image, index) in images" :key="image.url" type="button" class="aspect-square overflow-hidden rounded border" :class="index === activeImage ? 'border-primary ring-2 ring-primary-200' : 'border-surface-200'" :aria-label="$t('product.image', { number: index + 1 })" @click="activeImage = index">
                            <img :src="image.url" :alt="image.alt" class="h-full w-full object-cover">
                        </button>
                    </div>
                </div>

                <div>
                    <h1 class="text-3xl font-bold text-surface-900">{{ product.name }}</h1>
                    <div class="mt-2 flex flex-wrap gap-1.5"><Tag v-for="badge in product.badges" :key="badge" :value="badge" severity="info" /></div>

                    <h2 class="mb-2 mt-6 text-sm font-semibold">{{ $t('product.pack_size') }}</h2>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" role="radiogroup" :aria-label="$t('product.pack_size')">
                        <button v-for="variant in product.variants" :key="variant.publicId" type="button" role="radio" :aria-checked="variant.publicId === selectedId" class="rounded-lg border-2 bg-white p-2 text-center text-sm transition" :class="[variant.publicId === selectedId ? 'border-primary ring-2 ring-primary-100' : 'border-surface-200 hover:border-primary-300', variant.available === 0 ? 'opacity-60' : '']" @click="select(variant)">
                            <div class="font-bold">{{ variant.name }}</div>
                            <div class="text-surface-700">{{ money(variant.price.gross) }}</div>
                            <div v-if="variant.price.perLitre" class="text-xs text-surface-500">{{ $t('catalog.per_litre', { price: money(variant.price.perLitre) }) }}</div>
                        </button>
                    </div>

                    <div v-if="selected" class="mt-6">
                        <div class="text-4xl font-extrabold text-surface-900" data-testid="price-gross">
                            {{ money(selected.price.gross) }}
                            <span class="text-sm font-normal text-surface-500">{{ $t('product.incl_vat', { rate: Number(selected.price.vatRate) }) }}</span>
                        </div>
                        <div class="text-sm text-surface-600">
                            {{ $t('product.excl_vat', { price: money(selected.price.net) }) }}
                            <template v-if="selected.price.perLitre"> · {{ $t('product.per_litre', { price: money(selected.price.perLitre) }) }}</template>
                        </div>
                        <div class="mt-2 flex items-center gap-1 text-sm font-semibold" :class="STOCK_CLASS[selected.stock]">
                            <AppIcon :name="selected.stock === 'out_of_stock' ? 'close' : 'check'" /> {{ $t(`product.stock.${selected.stock}`, { count: selected.available }) }}
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <InputNumber v-model="quantity" show-buttons button-layout="horizontal" :min="1" :max="Math.max(selected.available, 1)" :disabled="selected.available === 0" input-class="w-14 text-center" :aria-label="$t('product.quantity')">
                                <template #incrementicon><AppIcon name="add" /></template>
                                <template #decrementicon><AppIcon name="remove" /></template>
                            </InputNumber>
                            <Button :disabled="selected.available === 0" @click="addToCart"><AppIcon name="cart" /> {{ $t('product.add_to_cart') }}</Button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-surface-500">
                            <span><AppIcon name="shipping" /> {{ $t('product.free_shipping') }}</span>
                            <span v-if="product.documents.some((d) => d.type === 'sds')"><AppIcon name="document" /> {{ $t('product.sds_available') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <Tabs value="description" class="mt-10">
                <TabList>
                    <Tab value="description">{{ $t('product.tabs.description') }}</Tab>
                    <Tab value="specifications">{{ $t('product.tabs.specifications') }}</Tab>
                    <Tab value="documents">{{ $t('product.tabs.documents') }}</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="description"><p class="max-w-3xl leading-relaxed text-surface-700">{{ product.description || $t('product.no_description') }}</p></TabPanel>
                    <TabPanel value="specifications">
                        <table class="w-full max-w-2xl text-sm">
                            <tbody><tr v-for="spec in product.specs" :key="spec.name" class="border-b border-surface-200"><th class="w-1/3 py-2 pr-4 text-left font-medium text-surface-600">{{ spec.name }}</th><td class="py-2">{{ spec.value }}</td></tr></tbody>
                        </table>
                    </TabPanel>
                    <TabPanel value="documents">
                        <ul v-if="product.documents.length" class="flex flex-col gap-2">
                            <li v-for="doc in product.documents" :key="doc.url"><a :href="doc.url" target="_blank" rel="noopener" class="text-primary-700 underline"><AppIcon name="document" /> {{ doc.title }} ({{ $t('product.document_meta', { language: doc.locale.toUpperCase() }) }})</a></li>
                        </ul>
                        <p v-else class="text-surface-500">{{ $t('product.no_documents') }}</p>
                    </TabPanel>
                </TabPanels>
            </Tabs>

            <section v-if="product.related.length" class="mt-10">
                <h2 class="mb-3 text-xl font-bold">{{ $t('product.related') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><ProductCard v-for="item in product.related" :key="item.slug" :product="item" /></div>
            </section>
        </template>
    </div>
</template>
