<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import FormField from '../../../components/FormField.vue';
import StoreRequired from '../../StoreRequired.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { confirmAction } from '../../../shared/confirm.js';
import { t } from '../../../shared/i18n.js';
import { slugify } from '../../../../shared/slug.js';

// Admin: the store's category tree (shown in the shop header and catalog filters).
const tenant = inject('tenant');
const tree = ref([]);
const dialog = ref(false);
const editingId = ref(null);
const form = reactive({ name: '', slug: '', parentId: null, description: '', position: 0, isActive: true });
const errors = reactive({});
const saving = ref(false);
const slugTouched = ref(false);

const flat = computed(() => {
    const out = [];
    const walk = (nodes, depth) => nodes.forEach((node) => { out.push({ ...node, depth }); walk(node.children, depth + 1); });
    walk(tree.value, 0);
    return out;
});
const parentOptions = computed(() => [{ id: null, label: t('admin.catalog.categories.top_level') }, ...flat.value.filter((c) => c.id !== editingId.value).map((c) => ({ id: c.id, label: `${'— '.repeat(c.depth)}${c.name}` }))]);

async function load() {
    if (tenant.mode !== 'store') return;
    try {
        tree.value = await api.get('/api/admin/catalog/categories');
    } catch (error) {
        handleApiError(error);
    }
}

function open(category = null, parentId = null) {
    editingId.value = category?.id ?? null;
    Object.assign(form, category
        ? { name: category.name, slug: category.slug, parentId: category.parentId, description: category.description ?? '', position: category.position, isActive: category.isActive }
        : { name: '', slug: '', parentId, description: '', position: 0, isActive: true });
    slugTouched.value = !!category;
    Object.keys(errors).forEach((key) => delete errors[key]);
    dialog.value = true;
}

watch(() => form.name, (name) => { if (!slugTouched.value) form.slug = slugify(name); });

async function save() {
    saving.value = true;
    try {
        const body = { ...form, description: form.description || null };
        if (editingId.value) await api.put(`/api/admin/catalog/categories/${editingId.value}`, body);
        else await api.post('/api/admin/catalog/categories', body);
        dialog.value = false;
        notify({ type: 'success', text: t('admin.catalog.saved') });
        await load();
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

async function remove(category) {
    const confirmed = await confirmAction({
        title: t('admin.catalog.categories.delete_title', { name: category.name }),
        body: t('admin.catalog.categories.delete_body'),
        confirmLabel: t('admin.catalog.categories.delete_confirm'),
        cancelLabel: t('admin.catalog.categories.delete_cancel'),
    });
    if (!confirmed) return;
    try {
        await api.delete(`/api/admin/catalog/categories/${category.id}`);
        notify({ type: 'success', text: t('admin.catalog.deleted') });
        await load();
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">{{ $t('admin.catalog.categories.title') }}</h1>
                <Button @click="open()"><AppIcon name="add" /> {{ $t('admin.catalog.categories.new') }}</Button>
            </div>
            <div class="rounded-lg border border-surface-200 bg-white">
                <EmptyState v-if="!flat.length" :title="$t('admin.catalog.categories.empty')" :text="$t('admin.catalog.categories.empty_text')" />
                <ul v-else>
                    <li v-for="category in flat" :key="category.id" class="flex flex-wrap items-center gap-2 border-b border-surface-100 px-4 py-2" :style="{ paddingLeft: `${1 + category.depth * 1.5}rem` }">
                        <AppIcon v-if="category.depth" name="chevron-right" class="text-surface-400" />
                        <span class="font-semibold">{{ category.name }}</span>
                        <span class="text-xs text-surface-500">{{ `/c/${category.slug} · ${$t('admin.catalog.categories.products', { count: category.productCount })}` }}</span>
                        <Tag v-if="!category.isActive" severity="secondary" :value="$t('admin.catalog.inactive')" />
                        <span class="ml-auto flex gap-1">
                            <Button text size="small" @click="open(null, category.id)"><AppIcon name="add" /> {{ $t('admin.catalog.categories.add_child') }}</Button>
                            <Button text size="small" @click="open(category)"><AppIcon name="edit" /> {{ $t('admin.catalog.edit') }}</Button>
                            <Button text size="small" severity="danger" @click="remove(category)"><AppIcon name="delete" /> {{ $t('admin.catalog.delete') }}</Button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? form.name : $t('admin.catalog.categories.new')" :style="{ width: 'min(36rem, calc(100vw - 2rem))' }">
            <form class="grid gap-4" novalidate @submit.prevent="save">
                <FormField id="c-name" v-slot="{ invalid }" :label="$t('admin.catalog.categories.name')" required :error="errors.name"><InputText id="c-name" v-model="form.name" :invalid="invalid" autofocus /></FormField>
                <FormField id="c-slug" v-slot="{ invalid }" :label="$t('admin.catalog.categories.slug')" required :error="errors.slug"><InputText id="c-slug" v-model="form.slug" :invalid="invalid" @input="slugTouched = true" /></FormField>
                <FormField id="c-parent" :label="$t('admin.catalog.categories.parent')" :error="errors.parentId"><Select v-model="form.parentId" input-id="c-parent" :options="parentOptions" option-label="label" option-value="id"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select></FormField>
                <FormField id="c-position" :label="$t('admin.catalog.categories.position')" :error="errors.position"><InputNumber v-model="form.position" input-id="c-position" :min="0" :use-grouping="false" /></FormField>
                <FormField id="c-description" :label="$t('admin.catalog.categories.description')" :error="errors.description"><Textarea id="c-description" v-model="form.description" rows="3" /></FormField>
                <label class="flex items-center gap-2 text-sm font-semibold"><ToggleSwitch v-model="form.isActive" /> {{ $t('admin.catalog.categories.is_active') }}</label>
                <div class="flex justify-end gap-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.catalog.save')" /></div>
            </form>
        </Dialog>
    </StoreRequired>
</template>
