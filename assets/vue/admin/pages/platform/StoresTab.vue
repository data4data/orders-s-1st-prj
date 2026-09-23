<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import AppIcon from '../../../components/AppIcon.vue';
import FormField from '../../../components/FormField.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { confirmAction } from '../../../shared/confirm.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';

// Platform → Stores: create shops and switch them on or off (an inactive shop shows "Store not found").
defineProps({ overview: { type: Object, required: true } });
const emit = defineEmits(['update']);
const dialog = ref(false);
const saving = ref(false);
const errors = reactive({});
const form = reactive({ code: '', name: '', countryCode: 'NL', currencyCode: 'EUR', orderNumberPrefix: '', host: '' });

async function create() {
    saving.value = true;
    try {
        emit('update', await api.post('/api/admin/platform/stores', form));
        dialog.value = false;
        notify({ type: 'success', text: t('admin.platform.store_created', { name: form.name }) });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

async function setActive(store, active) {
    if (!active && !(await confirmAction({ title: t('admin.platform.deactivate_title', { name: store.name }), body: t('admin.platform.deactivate_body'), confirmLabel: t('admin.platform.deactivate'), cancelLabel: t('admin.catalog.cancel') }))) {
        return;
    }
    try {
        emit('update', await api.put(`/api/admin/platform/stores/${store.id}/active`, { active }));
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4">
        <div class="flex justify-end"><Button @click="dialog = true"><AppIcon name="add" /> {{ $t('admin.platform.new_store') }}</Button></div>
        <ul class="divide-y divide-surface-100">
            <li v-for="store in overview.stores" :key="store.id" class="flex flex-wrap items-center gap-3 py-2">
                <span class="grow"><strong>{{ store.name }}</strong> <span class="text-sm text-surface-500">{{ store.code }} · {{ store.orderNumberPrefix }} · {{ store.country }} · {{ store.currency }}</span>
                    <br><span class="font-mono text-xs text-surface-600">{{ store.domains.map((d) => d.host).join(', ') }}</span></span>
                <label class="flex items-center gap-2 text-sm"><ToggleSwitch :model-value="store.isActive" @update:model-value="(value) => setActive(store, value)" /> {{ store.isActive ? $t('admin.catalog.active') : $t('admin.catalog.inactive') }}</label>
            </li>
        </ul>
        <Dialog v-model:visible="dialog" modal :header="$t('admin.platform.new_store')" class="w-full max-w-lg">
            <template #closeicon><AppIcon name="close" /></template>
            <form class="grid gap-3 sm:grid-cols-2" novalidate @submit.prevent="create">
                <FormField id="store-new-name" v-slot="{ invalid }" :label="$t('admin.settings.name')" required :error="errors.name"><InputText id="store-new-name" v-model="form.name" :invalid="invalid" /></FormField>
                <FormField id="store-new-code" v-slot="{ invalid }" :label="$t('admin.settings.code')" required :error="errors.code"><InputText id="store-new-code" v-model="form.code" placeholder="myoils-marine" :invalid="invalid" /></FormField>
                <FormField id="store-new-host" v-slot="{ invalid }" class="sm:col-span-2" :label="$t('admin.platform.host')" required :error="errors.host"><InputText id="store-new-host" v-model="form.host" placeholder="myoils-marine.shop.test" :invalid="invalid" /></FormField>
                <FormField id="store-new-country" :label="$t('address.country')" :error="errors.countryCode">
                    <Select v-model="form.countryCode" input-id="store-new-country" :options="overview.countries" option-label="name" option-value="code"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                </FormField>
                <FormField id="store-new-currency" v-slot="{ invalid }" :label="$t('admin.platform.currency')" :error="errors.currencyCode"><InputText id="store-new-currency" v-model="form.currencyCode" class="uppercase" :invalid="invalid" /></FormField>
                <FormField id="store-new-prefix" v-slot="{ invalid }" :label="$t('admin.settings.order_prefix')" required :error="errors.orderNumberPrefix"><InputText id="store-new-prefix" v-model="form.orderNumberPrefix" class="uppercase" :invalid="invalid" /></FormField>
                <div class="flex justify-end gap-2 sm:col-span-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.platform.create')" /></div>
            </form>
        </Dialog>
    </div>
</template>
