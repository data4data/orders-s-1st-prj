<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Badge from 'primevue/badge';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../../../components/AppIcon.vue';
import FormField from '../../../components/FormField.vue';
import StoreRequired from '../../StoreRequired.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { confirmAction } from '../../../shared/confirm.js';
import { useUnsavedChanges } from '../../../shared/unsaved.js';
import { t } from '../../../shared/i18n.js';
import { slugify } from '../../../../shared/slug.js';
import { formatMoney } from '../../../../shared/format.js';

// Admin product editor: one form, five tabs, saved at once (ProductInput on the server).
const props = defineProps({ publicId: { type: String, default: null } });
const router = useRouter();
const tenant = inject('tenant');

const blank = () => ({ version: null, name: '', slug: '', brand: "MyOil's", description: '', taxCategoryId: null, categoryIds: [], isActive: true, variants: [newVariant()], specs: [], images: [], documents: [] });
function newVariant() {
    return { publicId: null, sku: '', name: '', volumeMl: null, weightG: null, priceNet: '', onHand: 0, reserved: 0, isActive: true };
}

const form = ref(blank());
const errors = reactive({});
const saved = ref('');
const loading = ref(true);
const saving = ref(false);
const slugTouched = ref(false);
const meta = reactive({ vatRate: null, currency: 'EUR', taxCategories: [], categories: [], attributes: [] });
const tab = ref('general');

const isDirty = () => JSON.stringify(form.value) !== saved.value;
useUnsavedChanges(isDirty, { router: true });

const flatCategories = computed(() => {
    const out = [];
    const walk = (nodes, depth) => nodes.forEach((node) => { out.push({ id: node.id, label: `${'— '.repeat(depth)}${node.name}` }); walk(node.children, depth + 1); });
    walk(meta.categories, 0);
    return out;
});
const vatRate = computed(() => meta.taxCategories.find((c) => c.id === form.value.taxCategoryId)?.rate ?? meta.vatRate ?? '0');
const gross = (priceNet) => (/^\d+(\.\d{1,2})?$/.test(priceNet) ? Math.round(Math.round(Number(priceNet) * 100) * (1 + Number(vatRate.value) / 100)) : null);
const errorsIn = (prefixes) => Object.keys(errors).filter((key) => prefixes.some((p) => key === p || key.startsWith(`${p}[`) || key.startsWith(`${p}.`))).length;
const tabErrors = computed(() => ({
    general: errorsIn(['name', 'slug', 'brand', 'description', 'taxCategoryId', 'categoryIds']),
    variants: errorsIn(['variants']),
    specs: errorsIn(['specs']),
    images: errorsIn(['images']),
    documents: errorsIn(['documents']),
}));
const packOptions = computed(() => [{ value: null, label: t('admin.catalog.product.all_packs') }, ...form.value.variants.filter((v) => v.sku).map((v) => ({ value: v.sku, label: `${v.name || v.sku} (${v.sku})` }))]);
const docTypes = computed(() => ['sds', 'tds', 'approval'].map((value) => ({ value, label: t(`admin.catalog.product.doc_types.${value}`) })));

function specFor(attribute) {
    let spec = form.value.specs.find((s) => s.attributeId === attribute.id);
    if (!spec) {
        spec = { attributeId: attribute.id, options: [], value: null };
        form.value.specs.push(spec);
    }
    return spec;
}

watch(() => form.value.name, (name) => { if (!slugTouched.value && !props.publicId) form.value.slug = slugify(name); });

async function load() {
    if (tenant.mode !== 'store') return;
    loading.value = true;
    try {
        const [taxCategories, categories, attributes] = await Promise.all([
            api.get('/api/admin/catalog/tax-categories'),
            api.get('/api/admin/catalog/categories'),
            api.get('/api/admin/catalog/attributes'),
        ]);
        Object.assign(meta, { taxCategories, categories, attributes });
        if (props.publicId) {
            const product = await api.get(`/api/admin/catalog/products/${props.publicId}`);
            meta.vatRate = product.vatRate;
            meta.currency = product.currency;
            form.value = { ...blank(), ...product, description: product.description ?? '' };
        } else {
            form.value = blank();
            form.value.taxCategoryId = taxCategories.find((c) => c.code === 'standard')?.id ?? taxCategories[0]?.id ?? null;
            meta.currency = tenant.store?.currencyCode ?? 'EUR';
        }
        attributes.forEach(specFor);
        saved.value = JSON.stringify(form.value);
    } catch (error) {
        handleApiError(error);
    } finally {
        loading.value = false;
    }
}

function payload() {
    const { publicId: _p, vatRate: _v, currency: _c, ...data } = form.value; // eslint-disable-line no-unused-vars
    return {
        ...data,
        specs: data.specs.filter((s) => s.options.length || (s.value !== null && `${s.value}` !== '')).map((s) => ({ ...s, value: s.value === null ? null : `${s.value}` })),
        variants: data.variants.map(({ reserved: _r, ...v }) => ({ ...v, volumeMl: v.volumeMl ?? 0, weightG: v.weightG ?? 0 })), // eslint-disable-line no-unused-vars
    };
}

async function save() {
    saving.value = true;
    try {
        const body = payload();
        const result = props.publicId
            ? await api.put(`/api/admin/catalog/products/${props.publicId}`, body)
            : await api.post('/api/admin/catalog/products', body);
        Object.keys(errors).forEach((key) => delete errors[key]);
        notify({ type: 'success', text: t('admin.catalog.saved') });
        saved.value = JSON.stringify(form.value);
        if (!props.publicId) {
            await router.replace(`/catalog/products/${result.publicId}`);
        } else {
            await load();
        }
    } catch (error) {
        if (await applyServerErrors(error, errors)) {
            const first = Object.entries(tabErrors.value).find(([, count]) => count > 0);
            if (first) tab.value = first[0];
        }
    } finally {
        saving.value = false;
    }
}

async function removeVariant(index) {
    const variant = form.value.variants[index];
    if (variant.publicId && !(await confirmAction({
        title: t('admin.catalog.product.remove_variant_title', { name: variant.name || variant.sku }),
        body: t('admin.catalog.product.remove_variant_body'),
        confirmLabel: t('admin.catalog.product.remove_variant_confirm'),
        cancelLabel: t('admin.catalog.product.remove_variant_cancel'),
    }))) return;
    form.value.variants.splice(index, 1);
}

async function deleteProduct() {
    const confirmed = await confirmAction({
        title: t('admin.catalog.products.delete_title', { name: form.value.name }),
        body: t('admin.catalog.products.delete_body'),
        confirmLabel: t('admin.catalog.products.delete_confirm'),
        cancelLabel: t('admin.catalog.products.delete_cancel'),
    });
    if (!confirmed) return;
    try {
        await api.delete(`/api/admin/catalog/products/${props.publicId}`);
        saved.value = JSON.stringify(form.value);
        notify({ type: 'success', text: t('admin.catalog.deleted') });
        await router.push('/catalog/products');
    } catch (error) {
        handleApiError(error);
    }
}

function move(list, index, step) {
    const target = index + step;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}

watch(() => tenant.store?.publicId, load);
watch(() => props.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <router-link to="/catalog/products" class="text-sm text-primary-700 hover:underline" data-router-link><AppIcon name="back" /> {{ $t('admin.catalog.back') }}</router-link>
                    <h1 class="text-xl font-semibold">{{ publicId ? form.name : $t('admin.catalog.product.new_title') }}</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="publicId" severity="danger" outlined @click="deleteProduct"><AppIcon name="delete" /> {{ $t('admin.catalog.delete') }}</Button>
                    <Button :loading="saving" :disabled="loading" @click="save"><AppIcon name="check" /> {{ $t('admin.catalog.save') }}</Button>
                </div>
            </div>

            <div v-if="loading" class="rounded-lg border border-surface-200 bg-white p-6"><Skeleton width="40%" /><Skeleton class="mt-3" /><Skeleton class="mt-3" width="70%" /></div>
            <div v-else class="rounded-lg border border-surface-200 bg-white">
                <Tabs v-model:value="tab">
                    <TabList>
                        <Tab v-for="name in ['general', 'variants', 'specs', 'images', 'documents']" :key="name" :value="name">
                            {{ $t(`admin.catalog.product.tabs.${name}`) }} <Badge v-if="tabErrors[name]" :value="tabErrors[name]" severity="danger" class="ml-1" />
                        </Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="general">
                            <div class="grid gap-4 md:grid-cols-2">
                                <FormField id="p-name" v-slot="{ invalid }" :label="$t('admin.catalog.product.name')" required :error="errors.name"><InputText id="p-name" v-model="form.name" :invalid="invalid" /></FormField>
                                <FormField id="p-slug" v-slot="{ invalid }" :label="$t('admin.catalog.product.slug')" required :error="errors.slug" :help="$t('admin.catalog.product.slug_help', { slug: form.slug || '…' })"><InputText id="p-slug" v-model="form.slug" :invalid="invalid" @input="slugTouched = true" /></FormField>
                                <FormField id="p-brand" v-slot="{ invalid }" :label="$t('admin.catalog.product.brand')" required :error="errors.brand"><InputText id="p-brand" v-model="form.brand" :invalid="invalid" /></FormField>
                                <FormField id="p-tax" v-slot="{ invalid }" :label="$t('admin.catalog.product.tax_category')" required :error="errors.taxCategoryId">
                                    <Select v-model="form.taxCategoryId" input-id="p-tax" :options="meta.taxCategories" option-value="id" :option-label="(c) => `${c.name} (${Number(c.rate)}%)`" :invalid="invalid"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                                </FormField>
                                <FormField id="p-categories" v-slot="{ invalid }" class="md:col-span-2" :label="$t('admin.catalog.product.categories')" :error="errors.categoryIds">
                                    <MultiSelect v-model="form.categoryIds" input-id="p-categories" :options="flatCategories" option-value="id" option-label="label" display="chip" :invalid="invalid"><template #dropdownicon><AppIcon name="chevron-down" /></template></MultiSelect>
                                </FormField>
                                <FormField id="p-description" v-slot="{ invalid }" class="md:col-span-2" :label="$t('admin.catalog.product.description')" :error="errors.description"><Textarea id="p-description" v-model="form.description" rows="4" :invalid="invalid" /></FormField>
                                <label class="flex items-center gap-2 text-sm font-semibold"><ToggleSwitch v-model="form.isActive" /> {{ $t('admin.catalog.product.is_active') }}</label>
                            </div>
                        </TabPanel>

                        <TabPanel value="variants">
                            <p class="mb-3 text-sm text-surface-500">{{ $t('admin.catalog.product.vat_hint', { rate: Number(vatRate), country: tenant.store?.countryCode ?? '' }) }}</p>
                            <p v-if="errors.variants" class="mb-3 text-sm text-red-600">{{ errors.variants }}</p>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[56rem] text-sm">
                                    <thead class="text-left text-surface-500">
                                        <tr>
                                            <th class="p-2">{{ $t('admin.catalog.product.sku') }} *</th><th class="p-2">{{ $t('admin.catalog.product.pack_name') }} *</th><th class="p-2">{{ $t('admin.catalog.product.volume') }} *</th><th class="p-2">{{ $t('admin.catalog.product.weight') }}</th>
                                            <th class="p-2">{{ $t('admin.catalog.product.price_net') }} *</th><th class="p-2">{{ $t('admin.catalog.product.gross') }}</th><th class="p-2">{{ $t('admin.catalog.product.on_hand') }}</th><th class="p-2">{{ $t('admin.catalog.product.reserved') }}</th><th class="p-2">{{ $t('admin.catalog.active') }}</th><th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(variant, index) in form.variants" :key="variant.publicId ?? `new-${index}`" class="border-t border-surface-100 align-top">
                                            <td class="p-2"><InputText v-model="variant.sku" :invalid="!!errors[`variants[${index}].sku`]" class="w-28" :aria-label="$t('admin.catalog.product.sku')" @input="variant.sku = variant.sku.toUpperCase()" /><small v-if="errors[`variants[${index}].sku`]" class="block text-red-600">{{ errors[`variants[${index}].sku`] }}</small></td>
                                            <td class="p-2"><InputText v-model="variant.name" :invalid="!!errors[`variants[${index}].name`]" class="w-28" :aria-label="$t('admin.catalog.product.pack_name')" /><small v-if="errors[`variants[${index}].name`]" class="block text-red-600">{{ errors[`variants[${index}].name`] }}</small></td>
                                            <td class="p-2"><InputNumber v-model="variant.volumeMl" :min="0" :use-grouping="false" :invalid="!!errors[`variants[${index}].volumeMl`]" input-class="w-24" :aria-label="$t('admin.catalog.product.volume')" /><small v-if="errors[`variants[${index}].volumeMl`]" class="block text-red-600">{{ errors[`variants[${index}].volumeMl`] }}</small></td>
                                            <td class="p-2"><InputNumber v-model="variant.weightG" :min="0" :use-grouping="false" input-class="w-24" :aria-label="$t('admin.catalog.product.weight')" /></td>
                                            <td class="p-2"><InputText v-model="variant.priceNet" :invalid="!!errors[`variants[${index}].priceNet`]" class="w-24" inputmode="decimal" :aria-label="$t('admin.catalog.product.price_net')" /><small v-if="errors[`variants[${index}].priceNet`]" class="block max-w-40 text-red-600">{{ errors[`variants[${index}].priceNet`] }}</small></td>
                                            <td class="p-2 pt-4 text-surface-600">{{ gross(variant.priceNet) !== null ? formatMoney(gross(variant.priceNet), meta.currency) : '–' }}</td>
                                            <td class="p-2"><InputNumber v-model="variant.onHand" :min="0" :use-grouping="false" :invalid="!!errors[`variants[${index}].onHand`]" input-class="w-20" :aria-label="$t('admin.catalog.product.on_hand')" /><small v-if="errors[`variants[${index}].onHand`]" class="block max-w-40 text-red-600">{{ errors[`variants[${index}].onHand`] }}</small></td>
                                            <td class="p-2 pt-4 text-surface-600">{{ variant.reserved }}</td>
                                            <td class="p-2 pt-3"><ToggleSwitch v-model="variant.isActive" :aria-label="$t('admin.catalog.active')" /></td>
                                            <td class="p-2"><Button text severity="danger" :aria-label="$t('admin.catalog.remove')" @click="removeVariant(index)"><AppIcon name="delete" /></Button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <Button class="mt-3" outlined @click="form.variants.push(newVariant())"><AppIcon name="add" /> {{ $t('admin.catalog.product.add_variant') }}</Button>
                        </TabPanel>

                        <TabPanel value="specs">
                            <p v-if="!meta.attributes.length" class="text-sm text-surface-500">{{ $t('admin.catalog.product.no_attributes') }}</p>
                            <div class="grid gap-4 md:grid-cols-2">
                                <template v-for="(attribute) in meta.attributes" :key="attribute.id">
                                    <FormField :id="`spec-${attribute.id}`" :label="attribute.unit ? `${attribute.name} (${attribute.unit})` : attribute.name" :error="errors[`specs[${form.specs.indexOf(specFor(attribute))}].options`] ?? errors[`specs[${form.specs.indexOf(specFor(attribute))}].value`]">
                                        <Select v-if="attribute.type === 'select'" :input-id="`spec-${attribute.id}`" :model-value="specFor(attribute).options[0] ?? null" :options="[null, ...attribute.options]" :option-label="(o) => o ?? $t('admin.catalog.none')" @update:model-value="(v) => (specFor(attribute).options = v ? [v] : [])"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                                        <MultiSelect v-else-if="attribute.type === 'multiselect'" v-model="specFor(attribute).options" :input-id="`spec-${attribute.id}`" :options="attribute.options" display="chip"><template #dropdownicon><AppIcon name="chevron-down" /></template></MultiSelect>
                                        <InputText v-else :id="`spec-${attribute.id}`" v-model="specFor(attribute).value" :inputmode="attribute.type === 'number' ? 'decimal' : 'text'" />
                                    </FormField>
                                </template>
                            </div>
                        </TabPanel>

                        <TabPanel value="images">
                            <div v-for="(image, index) in form.images" :key="index" class="mb-3 grid items-start gap-3 rounded-md border border-surface-200 p-3 md:grid-cols-[6rem_1fr_1fr_12rem_auto]">
                                <div class="aspect-square overflow-hidden rounded bg-surface-100"><img v-if="image.url.startsWith('https://')" :src="image.url" :alt="image.alt" class="h-full w-full object-cover"></div>
                                <FormField :id="`img-url-${index}`" v-slot="{ invalid }" :label="$t('admin.catalog.product.image_url')" required :error="errors[`images[${index}].url`]"><InputText :id="`img-url-${index}`" v-model="image.url" :invalid="invalid" /></FormField>
                                <FormField :id="`img-alt-${index}`" v-slot="{ invalid }" :label="$t('admin.catalog.product.image_alt')" required :error="errors[`images[${index}].alt`]"><InputText :id="`img-alt-${index}`" v-model="image.alt" :invalid="invalid" /></FormField>
                                <FormField :id="`img-variant-${index}`" :label="$t('admin.catalog.product.image_variant')" :error="errors[`images[${index}].variantSku`]"><Select v-model="image.variantSku" :input-id="`img-variant-${index}`" :options="packOptions" option-label="label" option-value="value"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select></FormField>
                                <div class="flex gap-1 pt-6">
                                    <Button text :aria-label="$t('admin.catalog.move_up')" @click="move(form.images, index, -1)"><AppIcon name="chevron-down" class="rotate-180" /></Button>
                                    <Button text :aria-label="$t('admin.catalog.move_down')" @click="move(form.images, index, 1)"><AppIcon name="chevron-down" /></Button>
                                    <Button text severity="danger" :aria-label="$t('admin.catalog.remove')" @click="form.images.splice(index, 1)"><AppIcon name="delete" /></Button>
                                </div>
                            </div>
                            <Button outlined @click="form.images.push({ url: '', alt: form.name, variantSku: null })"><AppIcon name="add" /> {{ $t('admin.catalog.product.add_image') }}</Button>
                        </TabPanel>

                        <TabPanel value="documents">
                            <div v-for="(doc, index) in form.documents" :key="index" class="mb-3 grid items-start gap-3 rounded-md border border-surface-200 p-3 md:grid-cols-[12rem_1fr_1.5fr_6rem_auto]">
                                <FormField :id="`doc-type-${index}`" :label="$t('admin.catalog.product.doc_type')" :error="errors[`documents[${index}].type`]"><Select v-model="doc.type" :input-id="`doc-type-${index}`" :options="docTypes" option-label="label" option-value="value"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select></FormField>
                                <FormField :id="`doc-title-${index}`" v-slot="{ invalid }" :label="$t('admin.catalog.product.doc_title')" required :error="errors[`documents[${index}].title`]"><InputText :id="`doc-title-${index}`" v-model="doc.title" :invalid="invalid" /></FormField>
                                <FormField :id="`doc-url-${index}`" v-slot="{ invalid }" :label="$t('admin.catalog.product.doc_url')" required :error="errors[`documents[${index}].url`]"><InputText :id="`doc-url-${index}`" v-model="doc.url" :invalid="invalid" /></FormField>
                                <FormField :id="`doc-locale-${index}`" v-slot="{ invalid }" :label="$t('admin.catalog.product.doc_locale')" :error="errors[`documents[${index}].locale`]"><InputText :id="`doc-locale-${index}`" v-model="doc.locale" :invalid="invalid" /></FormField>
                                <Button class="mt-6" text severity="danger" :aria-label="$t('admin.catalog.remove')" @click="form.documents.splice(index, 1)"><AppIcon name="delete" /></Button>
                            </div>
                            <Button outlined @click="form.documents.push({ type: 'sds', title: '', url: '', locale: 'en' })"><AppIcon name="add" /> {{ $t('admin.catalog.product.add_document') }}</Button>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </div>
    </StoreRequired>
</template>
