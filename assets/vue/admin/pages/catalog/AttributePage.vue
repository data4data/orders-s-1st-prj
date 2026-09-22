<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
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

// Admin: specification attributes and their options (the catalog filters).
const tenant = inject('tenant');
const rows = ref([]);
const dialog = ref(false);
const editingId = ref(null);
const form = reactive({ code: '', name: '', type: 'select', unit: '', isFilterable: true, position: 0, options: [] });
const newOption = ref('');
const errors = reactive({});
const saving = ref(false);

const typeOptions = computed(() => ['select', 'multiselect', 'number', 'text'].map((value) => ({ value, label: t(`admin.catalog.attributes.types.${value}`) })));
const usesOptions = computed(() => form.type === 'select' || form.type === 'multiselect');

async function load() {
    if (tenant.mode !== 'store') return;
    try {
        rows.value = await api.get('/api/admin/catalog/attributes');
    } catch (error) {
        handleApiError(error);
    }
}

function open(attribute = null) {
    editingId.value = attribute?.id ?? null;
    Object.assign(form, attribute
        ? { code: attribute.code, name: attribute.name, type: attribute.type, unit: attribute.unit ?? '', isFilterable: attribute.isFilterable, position: attribute.position, options: [...attribute.options] }
        : { code: '', name: '', type: 'select', unit: '', isFilterable: true, position: rows.value.length, options: [] });
    newOption.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);
    dialog.value = true;
}

function addOption() {
    const value = newOption.value.trim();
    if (value && !form.options.includes(value)) form.options.push(value);
    newOption.value = '';
}

function move(index, step) {
    const target = index + step;
    if (target < 0 || target >= form.options.length) return;
    [form.options[index], form.options[target]] = [form.options[target], form.options[index]];
}

async function save() {
    saving.value = true;
    try {
        const body = { ...form, unit: form.unit || null, options: usesOptions.value ? form.options : [] };
        if (editingId.value) await api.put(`/api/admin/catalog/attributes/${editingId.value}`, body);
        else await api.post('/api/admin/catalog/attributes', body);
        dialog.value = false;
        notify({ type: 'success', text: t('admin.catalog.saved') });
        await load();
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

async function remove(attribute) {
    const confirmed = await confirmAction({
        title: t('admin.catalog.attributes.delete_title', { name: attribute.name }),
        body: t('admin.catalog.attributes.delete_body'),
        confirmLabel: t('admin.catalog.attributes.delete_confirm'),
        cancelLabel: t('admin.catalog.attributes.delete_cancel'),
    });
    if (!confirmed) return;
    try {
        await api.delete(`/api/admin/catalog/attributes/${attribute.id}`);
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
                <h1 class="text-xl font-semibold">{{ $t('admin.catalog.attributes.title') }}</h1>
                <Button @click="open()"><AppIcon name="add" /> {{ $t('admin.catalog.attributes.new') }}</Button>
            </div>
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable :value="rows" data-key="id">
                    <template #empty><EmptyState :title="$t('admin.catalog.attributes.empty')" :text="$t('admin.catalog.attributes.empty_text')" /></template>
                    <Column :header="$t('admin.catalog.attributes.name')"><template #body="{ data }"><span class="font-semibold">{{ data.name }}</span><div class="text-xs text-surface-500">{{ data.code }}</div></template></Column>
                    <Column :header="$t('admin.catalog.attributes.type')"><template #body="{ data }">{{ $t(`admin.catalog.attributes.types.${data.type}`) }}<span v-if="data.unit" class="text-surface-500"> ({{ data.unit }})</span></template></Column>
                    <Column :header="$t('admin.catalog.attributes.options')"><template #body="{ data }"><span class="text-sm text-surface-600">{{ data.options.join(', ') }}</span></template></Column>
                    <Column :header="$t('admin.catalog.attributes.is_filterable')"><template #body="{ data }"><Tag :severity="data.isFilterable ? 'success' : 'secondary'" :value="data.isFilterable ? $t('admin.catalog.yes') : $t('admin.catalog.no')" /></template></Column>
                    <Column :header="$t('admin.catalog.attributes.products')" field="productCount" />
                    <Column>
                        <template #body="{ data }"><span class="flex gap-1"><Button text size="small" @click="open(data)"><AppIcon name="edit" /> {{ $t('admin.catalog.edit') }}</Button><Button text size="small" severity="danger" @click="remove(data)"><AppIcon name="delete" /> {{ $t('admin.catalog.delete') }}</Button></span></template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? form.name : $t('admin.catalog.attributes.new')" :style="{ width: 'min(36rem, calc(100vw - 2rem))' }">
            <form class="grid gap-4" novalidate @submit.prevent="save">
                <FormField id="a-name" v-slot="{ invalid }" :label="$t('admin.catalog.attributes.name')" required :error="errors.name"><InputText id="a-name" v-model="form.name" :invalid="invalid" autofocus /></FormField>
                <FormField id="a-code" v-slot="{ invalid }" :label="$t('admin.catalog.attributes.code')" required :error="errors.code"><InputText id="a-code" v-model="form.code" :invalid="invalid" /></FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="a-type" :label="$t('admin.catalog.attributes.type')" :error="errors.type"><Select v-model="form.type" input-id="a-type" :options="typeOptions" option-label="label" option-value="value"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select></FormField>
                    <FormField id="a-unit" :label="$t('admin.catalog.attributes.unit')" :error="errors.unit"><InputText id="a-unit" v-model="form.unit" /></FormField>
                </div>
                <FormField v-if="usesOptions" id="a-option" :label="$t('admin.catalog.attributes.options')" required :error="errors.options ?? Object.entries(errors).find(([k]) => k.startsWith('options['))?.[1]">
                    <div class="flex gap-2"><InputText id="a-option" v-model="newOption" :placeholder="$t('admin.catalog.attributes.option_placeholder')" class="grow" @keydown.enter.prevent="addOption" /><Button type="button" outlined @click="addOption"><AppIcon name="add" /> {{ $t('admin.catalog.attributes.add_option') }}</Button></div>
                    <ul class="mt-2 flex flex-col gap-1">
                        <li v-for="(option, index) in form.options" :key="option" class="flex items-center gap-2 rounded bg-surface-100 px-2 py-1 text-sm">
                            <span class="grow">{{ option }}</span>
                            <Button type="button" text size="small" :aria-label="$t('admin.catalog.move_up')" @click="move(index, -1)"><AppIcon name="chevron-down" class="rotate-180" /></Button>
                            <Button type="button" text size="small" :aria-label="$t('admin.catalog.move_down')" @click="move(index, 1)"><AppIcon name="chevron-down" /></Button>
                            <Button type="button" text size="small" severity="danger" :aria-label="$t('admin.catalog.remove')" @click="form.options.splice(index, 1)"><AppIcon name="close" /></Button>
                        </li>
                    </ul>
                </FormField>
                <FormField id="a-position" :label="$t('admin.catalog.attributes.position')" :error="errors.position"><InputNumber v-model="form.position" input-id="a-position" :min="0" :use-grouping="false" /></FormField>
                <label class="flex items-center gap-2 text-sm font-semibold"><ToggleSwitch v-model="form.isFilterable" /> {{ $t('admin.catalog.attributes.is_filterable') }}</label>
                <div class="flex justify-end gap-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.catalog.save')" /></div>
            </form>
        </Dialog>
    </StoreRequired>
</template>
