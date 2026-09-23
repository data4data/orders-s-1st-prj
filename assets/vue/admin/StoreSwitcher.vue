<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import AppIcon from '../components/AppIcon.vue';
import { api, handleApiError } from '../shared/api.js';
import { notify } from '../shared/notify.js';
import { confirmLeave } from '../shared/confirm.js';
import { hasUnsavedChanges } from '../shared/unsaved.js';
import { t } from '../shared/i18n.js';

// Store switcher (decision #29): only the staff member's stores; "All stores" for super-admins.
const props = defineProps({ superAdmin: { type: Boolean, default: false } });
const tenant = inject('tenant');
const stores = ref([]);
const options = computed(() => [
    ...(props.superAdmin ? [{ value: 'all', label: t('admin.store_switcher.all_stores') }] : []),
    ...stores.value.map((store) => ({ value: store.publicId, label: store.name })),
]);
const selected = computed(() => (tenant.mode === 'all' ? 'all' : tenant.store?.publicId ?? null));

onMounted(async () => {
    try {
        stores.value = await api.get('/api/admin/stores');
    } catch (error) {
        handleApiError(error);
    }
});

async function change(value) {
    if (value === selected.value) return;
    if (hasUnsavedChanges() && !(await confirmLeave())) return;
    try {
        await api.put('/api/admin/stores/current', { store: value });
        Object.assign(tenant, await api.get('/api/admin/stores/current'));
        notify({ type: 'success', text: value === 'all' ? t('admin.store_switcher.switched_all') : t('admin.store_switcher.switched', { store: tenant.store?.name ?? '' }) });
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <AppIcon name="store" class="text-surface-500" />
        <Select :model-value="selected" :options="options" option-label="label" option-value="value" :placeholder="$t('admin.store_switcher.placeholder')" :aria-label="$t('admin.store_switcher.label')" class="min-w-64" @update:model-value="change">
            <template #dropdownicon><AppIcon name="chevron-down" /></template>
        </Select>
        <Tag v-if="tenant.readOnly" severity="warn" :value="$t('admin.store_switcher.read_only')" />
    </div>
</template>
