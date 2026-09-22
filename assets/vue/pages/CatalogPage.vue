<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import InputNumber from 'primevue/inputnumber';
import Paginator from 'primevue/paginator';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../components/AppIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import CatalogBreadcrumbs from '../catalog/CatalogBreadcrumbs.vue';
import ProductCard from '../catalog/ProductCard.vue';
import { api, handleApiError } from '../shared/api.js';
import { t } from '../shared/i18n.js';

// Catalog page (docs/diagrams/pages.html → Catalog & product). Filters live in the URL, so a
// filtered view can be shared or bookmarked.
const props = defineProps({
    category: { type: String, default: null },
    q: { type: String, default: '' },
});

const params = new URLSearchParams(window.location.search);
const state = reactive({
    filters: {},
    packs: params.getAll('pack').map(Number),
    minPrice: params.has('min') ? Number(params.get('min')) : null,
    maxPrice: params.has('max') ? Number(params.get('max')) : null,
    inStock: params.get('stock') === '1',
    sort: params.get('sort') ?? 'relevance',
    page: Number(params.get('page') ?? 1),
    view: params.get('view') === 'list' ? 'list' : 'grid',
});
for (const [key, value] of params.entries()) {
    const match = key.match(/^f\[(\w+)\]$/);
    if (match) (state.filters[match[1]] ??= []).push(value);
}

const data = ref(null);
const categories = ref([]);
const loading = ref(true);
const showSkeleton = ref(true);
const drawer = ref(false);
const perPage = 12;

const sortOptions = computed(() => ['relevance', 'price_asc', 'price_desc', 'name'].map((value) => ({ value, label: t(`catalog.sort.${value}`) })));
const viewOptions = computed(() => [{ value: 'grid', label: t('catalog.view.grid'), icon: 'catalog' }, { value: 'list', label: t('catalog.view.list'), icon: 'menu' }]);
const title = computed(() => data.value?.category?.name ?? (props.q ? t('catalog.search_results', { q: props.q }) : t('catalog.all_products')));
const currency = computed(() => data.value?.currency ?? 'EUR');

const activeChips = computed(() => {
    const chips = [];
    for (const [code, values] of Object.entries(state.filters)) {
        values.forEach((value) => chips.push({ key: `${code}:${value}`, label: value, remove: () => toggleOption(code, value) }));
    }
    state.packs.forEach((volume) => chips.push({ key: `pack:${volume}`, label: data.value?.packSizes.find((p) => p.volumeMl === volume)?.label ?? `${volume} ml`, remove: () => togglePack(volume) }));
    if (state.minPrice !== null || state.maxPrice !== null) chips.push({ key: 'price', label: `${state.minPrice ?? 0}–${state.maxPrice ?? '…'} ${currency.value}`, remove: () => { state.minPrice = null; state.maxPrice = null; } });
    if (state.inStock) chips.push({ key: 'stock', label: t('catalog.in_stock_only'), remove: () => { state.inStock = false; } });
    return chips;
});

function query() {
    const search = new URLSearchParams();
    if (props.category) search.set('category', props.category);
    if (props.q) search.set('q', props.q);
    for (const [code, values] of Object.entries(state.filters)) values.forEach((v) => search.append(`filters[${code}][]`, v));
    state.packs.forEach((p) => search.append('packs[]', String(p)));
    if (state.minPrice !== null) search.set('minPrice', String(Math.round(state.minPrice * 100)));
    if (state.maxPrice !== null) search.set('maxPrice', String(Math.round(state.maxPrice * 100)));
    if (state.inStock) search.set('inStock', '1');
    search.set('sort', state.sort);
    search.set('page', String(state.page));
    search.set('perPage', String(perPage));
    return search;
}

function syncUrl() {
    const url = new URLSearchParams();
    if (props.q) url.set('q', props.q);
    for (const [code, values] of Object.entries(state.filters)) values.forEach((v) => url.append(`f[${code}]`, v));
    state.packs.forEach((p) => url.append('pack', String(p)));
    if (state.minPrice !== null) url.set('min', String(state.minPrice));
    if (state.maxPrice !== null) url.set('max', String(state.maxPrice));
    if (state.inStock) url.set('stock', '1');
    if (state.sort !== 'relevance') url.set('sort', state.sort);
    if (state.page > 1) url.set('page', String(state.page));
    if (state.view !== 'grid') url.set('view', state.view);
    const qs = url.toString();
    history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);
}

let requestId = 0;
async function load() {
    const current = ++requestId;
    loading.value = true;
    const skeleton = setTimeout(() => { showSkeleton.value = true; }, 300);
    try {
        const result = await api.get(`/api/products?${query()}`);
        if (current === requestId) data.value = result;
    } catch (error) {
        handleApiError(error);
    } finally {
        clearTimeout(skeleton);
        if (current === requestId) {
            loading.value = false;
            showSkeleton.value = false;
        }
    }
}

function toggleOption(code, value) {
    const list = state.filters[code] ?? [];
    state.filters[code] = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
    if (state.filters[code].length === 0) delete state.filters[code];
}
function togglePack(volume) {
    state.packs = state.packs.includes(volume) ? state.packs.filter((v) => v !== volume) : [...state.packs, volume];
}
function clearAll() {
    state.filters = {};
    state.packs = [];
    state.minPrice = null;
    state.maxPrice = null;
    state.inStock = false;
}

watch(() => [JSON.stringify(state.filters), state.packs.join(), state.minPrice, state.maxPrice, state.inStock, state.sort], () => {
    state.page = 1;
    syncUrl();
    load();
});
watch(() => state.page, () => { syncUrl(); load(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
watch(() => state.view, syncUrl);

onMounted(async () => {
    load();
    try {
        categories.value = await api.get('/api/categories');
    } catch (error) {
        handleApiError(error);
    }
});
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-6">
        <CatalogBreadcrumbs :items="data?.breadcrumbs?.slice(0, -1) ?? []" :current="data?.category?.name ?? ''" />
        <div class="mb-4 mt-2 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">{{ title }}</h1>
                <div v-if="data" class="text-sm text-surface-500">{{ $t('catalog.products', { count: data.total }, data.total) }}</div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button class="md:!hidden" severity="secondary" outlined @click="drawer = true"><AppIcon name="menu" /> {{ $t('catalog.show_filters') }}</Button>
                <Select v-model="state.sort" :options="sortOptions" option-label="label" option-value="value" :aria-label="$t('catalog.sort.label')" class="min-w-48">
                    <template #dropdownicon><AppIcon name="chevron-down" /></template>
                </Select>
                <SelectButton v-model="state.view" :options="viewOptions" option-label="label" option-value="value" :allow-empty="false">
                    <template #option="{ option }"><AppIcon :name="option.icon" /><span class="sr-only">{{ option.label }}</span></template>
                </SelectButton>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-[15rem_1fr]">
            <component :is="'aside'" class="hidden md:block">
                <div id="catalog-filters" class="flex flex-col gap-5 rounded-lg border border-surface-200 bg-white p-4 text-sm">
                    <section v-if="categories.length">
                        <h2 class="mb-2 font-semibold">{{ $t('catalog.categories') }}</h2>
                        <ul class="flex flex-col gap-1">
                            <li v-for="node in categories" :key="node.slug">
                                <a :href="`/c/${node.slug}`" class="hover:text-primary-700" :class="{ 'font-semibold text-primary-700': node.slug === category }">{{ node.name }}</a>
                                <ul v-if="node.children.length" class="ml-3 mt-1 flex flex-col gap-1 border-l border-surface-200 pl-3">
                                    <li v-for="child in node.children" :key="child.slug"><a :href="`/c/${child.slug}`" class="text-surface-600 hover:text-primary-700" :class="{ 'font-semibold text-primary-700': child.slug === category }">{{ child.name }}</a></li>
                                </ul>
                            </li>
                        </ul>
                    </section>
                    <section v-for="facet in data?.facets ?? []" :key="facet.code">
                        <h2 class="mb-2 font-semibold">{{ facet.name }}</h2>
                        <div v-for="option in facet.options" :key="option.value" class="flex items-center gap-2 py-0.5">
                            <Checkbox :input-id="`f-${facet.code}-${option.value}`" :model-value="state.filters[facet.code]?.includes(option.value) ?? false" binary @update:model-value="toggleOption(facet.code, option.value)" />
                            <label :for="`f-${facet.code}-${option.value}`" class="grow cursor-pointer">{{ option.value }}</label>
                            <span class="text-xs text-surface-500">{{ option.count }}</span>
                        </div>
                    </section>
                    <section v-if="data?.packSizes?.length">
                        <h2 class="mb-2 font-semibold">{{ $t('catalog.pack_size') }}</h2>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="pack in data.packSizes" :key="pack.volumeMl" type="button" class="rounded-full border px-2.5 py-0.5 text-xs" :class="state.packs.includes(pack.volumeMl) ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 bg-white hover:border-primary-300'" :aria-pressed="state.packs.includes(pack.volumeMl)" @click="togglePack(pack.volumeMl)">{{ pack.label }}</button>
                        </div>
                    </section>
                    <section>
                        <h2 class="mb-2 font-semibold">{{ $t('catalog.price') }}</h2>
                        <div class="flex items-center gap-2">
                            <InputNumber v-model="state.minPrice" :placeholder="$t('catalog.price_min')" :min="0" mode="currency" :currency="currency" :max-fraction-digits="0" input-class="w-full" class="w-full" :aria-label="$t('catalog.price_min')" />
                            <InputNumber v-model="state.maxPrice" :placeholder="$t('catalog.price_max')" :min="0" mode="currency" :currency="currency" :max-fraction-digits="0" input-class="w-full" class="w-full" :aria-label="$t('catalog.price_max')" />
                        </div>
                    </section>
                    <label class="flex items-center gap-2 font-semibold"><ToggleSwitch v-model="state.inStock" /> {{ $t('catalog.in_stock_only') }}</label>
                </div>
            </component>

            <div class="min-w-0">
                <div v-if="activeChips.length" class="mb-3 flex flex-wrap items-center gap-1.5 text-sm">
                    <span class="text-surface-500">{{ $t('catalog.active') }}</span>
                    <button v-for="chip in activeChips" :key="chip.key" type="button" class="flex items-center gap-1 rounded-full bg-primary px-2.5 py-0.5 text-xs text-primary-contrast" :aria-label="$t('catalog.remove_filter', { name: chip.label })" @click="chip.remove">{{ chip.label }} <AppIcon name="close" /></button>
                    <button type="button" class="text-primary-700 underline" @click="clearAll">{{ $t('catalog.clear_all') }}</button>
                </div>

                <div v-if="showSkeleton && loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="n in 6" :key="n" class="rounded-lg border border-surface-200 bg-white p-3"><Skeleton height="10rem" /><Skeleton class="mt-3" width="70%" /><Skeleton class="mt-2" width="40%" /></div>
                </div>
                <div v-else-if="data && data.items.length === 0" class="rounded-lg border border-surface-200 bg-white">
                    <EmptyState :title="$t('catalog.empty_title')" :text="$t('catalog.empty_text')">
                        <Button :label="$t('catalog.clear_all')" @click="clearAll" />
                    </EmptyState>
                </div>
                <div v-else-if="data" :class="state.view === 'grid' ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3' : 'flex flex-col gap-3'">
                    <ProductCard v-for="product in data.items" :key="product.slug" :product="product" :layout="state.view" />
                </div>

                <Paginator v-if="data && data.total > perPage" class="mt-6" :rows="perPage" :total-records="data.total" :first="(state.page - 1) * perPage" @page="state.page = $event.page + 1" />
            </div>
        </div>

        <Drawer v-model:visible="drawer" :header="$t('catalog.filters')" position="left" class="!w-80">
            <div class="flex flex-col gap-5 text-sm">
                <section v-for="facet in data?.facets ?? []" :key="facet.code">
                    <h2 class="mb-2 font-semibold">{{ facet.name }}</h2>
                    <div v-for="option in facet.options" :key="option.value" class="flex items-center gap-2 py-0.5">
                        <Checkbox :input-id="`m-${facet.code}-${option.value}`" :model-value="state.filters[facet.code]?.includes(option.value) ?? false" binary @update:model-value="toggleOption(facet.code, option.value)" />
                        <label :for="`m-${facet.code}-${option.value}`" class="grow">{{ option.value }}</label>
                        <span class="text-xs text-surface-500">{{ option.count }}</span>
                    </div>
                </section>
                <label class="flex items-center gap-2 font-semibold"><ToggleSwitch v-model="state.inStock" /> {{ $t('catalog.in_stock_only') }}</label>
            </div>
        </Drawer>
    </div>
</template>
