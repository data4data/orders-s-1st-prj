<script setup>
import { computed, reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import AppIcon from '../../../components/AppIcon.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { confirmAction } from '../../../shared/confirm.js';
import { t } from '../../../shared/i18n.js';

// Settings → Staff & roles: who works in this shop. Only owners appoint owners; a shop keeps one owner.
const props = defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);
const form = reactive({ email: '', role: 'staff' });
const errors = reactive({ email: '', role: '' });
const adding = ref(false);
const roles = computed(() => ['staff', 'manager', 'owner'].map((value) => ({ value, label: t(`admin.settings.role.${value}`), disabled: value === 'owner' && !props.settings.canGrantOwner })));

async function add() {
    adding.value = true;
    errors.email = '';
    try {
        emit('update', await api.post('/api/admin/settings/staff', form));
        form.email = '';
    } catch (error) {
        await handleApiError(error, { errors });
    } finally {
        adding.value = false;
    }
}

async function change(member, role) {
    try {
        emit('update', await api.put(`/api/admin/settings/staff/${member.id}`, { role }));
    } catch (error) {
        handleApiError(error);
    }
}

async function remove(member) {
    if (await confirmAction({ title: t('admin.settings.remove_staff_title', { name: member.name }), body: t('admin.settings.remove_staff_body'), confirmLabel: t('admin.settings.remove_staff'), cancelLabel: t('admin.catalog.cancel') })) {
        try {
            emit('update', await api.delete(`/api/admin/settings/staff/${member.id}`));
        } catch (error) {
            handleApiError(error);
        }
    }
}
</script>

<template>
    <div class="flex flex-col gap-4 rounded-lg border border-surface-200 bg-white p-4">
        <ul class="divide-y divide-surface-100">
            <li v-for="member in settings.staff" :key="member.id" class="flex flex-wrap items-center gap-3 py-2">
                <span class="grow"><strong>{{ member.name }}</strong> <span class="text-sm text-surface-500">{{ member.email }}</span></span>
                <Select :model-value="member.role" :options="roles" option-label="label" option-value="value" option-disabled="disabled" class="w-40" :aria-label="$t('admin.settings.role_of', { name: member.name })" @update:model-value="(role) => change(member, role)">
                    <template #dropdownicon><AppIcon name="chevron-down" /></template>
                </Select>
                <Button text severity="danger" :aria-label="$t('admin.settings.remove_staff')" @click="remove(member)"><AppIcon name="delete" /></Button>
            </li>
        </ul>
        <form class="flex flex-wrap items-end gap-2" novalidate @submit.prevent="add">
            <div class="flex grow flex-col gap-1">
                <label for="staff-email" class="text-sm font-semibold">{{ $t('admin.settings.add_staff') }}</label>
                <InputText id="staff-email" v-model="form.email" type="email" :placeholder="$t('admin.settings.staff_email')" :invalid="!!errors.email" />
            </div>
            <Select v-model="form.role" :options="roles" option-label="label" option-value="value" option-disabled="disabled" class="w-40" :aria-label="$t('admin.settings.role_label')"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
            <Button type="submit" :loading="adding" :label="$t('admin.catalog.add')" />
            <small v-if="errors.email" class="w-full text-red-600">{{ errors.email }}</small>
        </form>
    </div>
</template>
