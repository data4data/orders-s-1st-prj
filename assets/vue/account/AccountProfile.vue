<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import FormField from '../components/FormField.vue';
import { api } from '../shared/api.js';
import { applyServerErrors } from '../shared/forms.js';
import { notify } from '../shared/notify.js';
import { useUnsavedChanges } from '../shared/unsaved.js';
import { t } from '../shared/i18n.js';

// Account → Profile & security: name and phone, and a password change.
const props = defineProps({ profile: { type: Object, required: true } });
const emit = defineEmits(['saved']);

const form = reactive({ firstName: props.profile.firstName, lastName: props.profile.lastName, phone: props.profile.phone ?? '' });
const password = reactive({ currentPassword: '', newPassword: '' });
const errors = reactive({});
const passwordErrors = reactive({});
const saving = ref(false);
const changing = ref(false);

useUnsavedChanges(() => form.firstName !== props.profile.firstName || form.lastName !== props.profile.lastName || form.phone !== (props.profile.phone ?? '') || password.newPassword !== '');

async function saveProfile() {
    saving.value = true;
    try {
        await api.put('/api/account/profile', form);
        emit('saved', { ...form });
        notify({ type: 'success', text: t('account.profile_saved') });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

async function changePassword() {
    changing.value = true;
    try {
        await api.put('/api/account/password', password);
        password.currentPassword = '';
        password.newPassword = '';
        notify({ type: 'success', text: t('account.password_changed') });
    } catch (error) {
        await applyServerErrors(error, passwordErrors);
    } finally {
        changing.value = false;
    }
}
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <form class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4" novalidate @submit.prevent="saveProfile">
            <h3 class="font-bold">{{ $t('account.profile') }}</h3>
            <div class="text-sm text-surface-600">{{ profile.email }}</div>
            <FormField id="profile-firstName" v-slot="{ invalid }" :label="$t('address.first_name')" required :error="errors.firstName">
                <InputText id="profile-firstName" v-model="form.firstName" :invalid="invalid" autocomplete="given-name" />
            </FormField>
            <FormField id="profile-lastName" v-slot="{ invalid }" :label="$t('address.last_name')" required :error="errors.lastName">
                <InputText id="profile-lastName" v-model="form.lastName" :invalid="invalid" autocomplete="family-name" />
            </FormField>
            <FormField id="profile-phone" v-slot="{ invalid }" :label="$t('address.phone')" :error="errors.phone">
                <InputText id="profile-phone" v-model="form.phone" :invalid="invalid" type="tel" autocomplete="tel" />
            </FormField>
            <div><Button type="submit" :loading="saving" :label="$t('account.save')" /></div>
        </form>
        <form class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4" novalidate @submit.prevent="changePassword">
            <h3 class="font-bold">{{ $t('account.security') }}</h3>
            <FormField id="current-password" v-slot="{ invalid }" :label="$t('account.current_password')" required :error="passwordErrors.currentPassword">
                <Password v-model="password.currentPassword" input-id="current-password" :feedback="false" :invalid="invalid" autocomplete="current-password" fluid />
            </FormField>
            <FormField id="new-password" v-slot="{ invalid }" :label="$t('account.new_password')" required :help="$t('account.password_help')" :error="passwordErrors.newPassword">
                <Password v-model="password.newPassword" input-id="new-password" :feedback="false" :invalid="invalid" autocomplete="new-password" fluid />
            </FormField>
            <div><Button type="submit" severity="secondary" :loading="changing" :label="$t('account.change_password')" /></div>
        </form>
    </div>
</template>
