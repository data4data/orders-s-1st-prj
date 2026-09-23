<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import ToggleSwitch from 'primevue/toggleswitch';
import Checkbox from 'primevue/checkbox';
import AppIcon from '../../../components/AppIcon.vue';
import FormField from '../../../components/FormField.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';

// Platform → Staff users: global accounts; shop access is given under Settings → Staff & roles.
defineProps({ overview: { type: Object, required: true } });
const emit = defineEmits(['update']);
const dialog = ref(false);
const saving = ref(false);
const errors = reactive({});
const form = reactive({ email: '', firstName: '', lastName: '', password: '', superAdmin: false });

async function create() {
    saving.value = true;
    try {
        emit('update', await api.post('/api/admin/platform/staff-users', form));
        dialog.value = false;
        notify({ type: 'success', text: t('admin.platform.staff_created', { email: form.email }) });
        Object.assign(form, { email: '', firstName: '', lastName: '', password: '', superAdmin: false });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

async function setSuper(user, value) {
    try {
        emit('update', await api.put(`/api/admin/platform/staff-users/${user.id}/super-admin`, { superAdmin: value }));
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4">
        <div class="flex justify-end"><Button @click="dialog = true"><AppIcon name="add" /> {{ $t('admin.platform.new_staff') }}</Button></div>
        <ul class="divide-y divide-surface-100">
            <li v-for="u in overview.staffUsers" :key="u.id" class="flex flex-wrap items-center gap-3 py-2">
                <span class="grow"><strong>{{ u.name }}</strong> <span class="text-sm text-surface-500">{{ u.email }}</span>
                    <br><span class="text-xs text-surface-500">{{ u.lastLoginAt ? $t('admin.platform.last_login', { date: new Date(u.lastLoginAt).toLocaleString() }) : $t('admin.platform.never_logged_in') }}</span></span>
                <label class="flex items-center gap-2 text-sm"><ToggleSwitch :model-value="u.superAdmin" :disabled="u.isMe" @update:model-value="(value) => setSuper(u, value)" /> {{ $t('admin.nav.super_admin') }}</label>
            </li>
        </ul>
        <Dialog v-model:visible="dialog" modal :header="$t('admin.platform.new_staff')" class="w-full max-w-md">
            <template #closeicon><AppIcon name="close" /></template>
            <form class="flex flex-col gap-3" novalidate @submit.prevent="create">
                <FormField id="staff-new-email" v-slot="{ invalid }" :label="$t('register.email')" required :error="errors.email"><InputText id="staff-new-email" v-model="form.email" type="email" :invalid="invalid" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField id="staff-new-first" v-slot="{ invalid }" :label="$t('address.first_name')" required :error="errors.firstName"><InputText id="staff-new-first" v-model="form.firstName" :invalid="invalid" /></FormField>
                    <FormField id="staff-new-last" v-slot="{ invalid }" :label="$t('address.last_name')" required :error="errors.lastName"><InputText id="staff-new-last" v-model="form.lastName" :invalid="invalid" /></FormField>
                </div>
                <FormField id="staff-new-password" v-slot="{ invalid }" :label="$t('admin.platform.start_password')" required :help="$t('account.password_help')" :error="errors.password"><Password v-model="form.password" input-id="staff-new-password" :feedback="false" :invalid="invalid" fluid /></FormField>
                <label class="flex items-center gap-2 text-sm"><Checkbox v-model="form.superAdmin" binary /> {{ $t('admin.nav.super_admin') }}</label>
                <div class="flex justify-end gap-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.platform.create')" /></div>
            </form>
        </Dialog>
    </div>
</template>
