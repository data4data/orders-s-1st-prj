<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import FormField from '../../../components/FormField.vue';
import { api } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { useUnsavedChanges } from '../../../shared/unsaved.js';
import { t } from '../../../shared/i18n.js';

// Settings → Store profile & branding. The colours drive the shop's look in both frontends.
const props = defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);
const form = reactive({ ...props.settings.profile });
const errors = reactive({});
const saving = ref(false);
const saved = ref(JSON.stringify(form));
useUnsavedChanges(() => JSON.stringify(form) !== saved.value, { router: true });

async function save() {
    saving.value = true;
    try {
        emit('update', await api.put('/api/admin/settings/profile', form));
        saved.value = JSON.stringify(form);
        Object.keys(errors).forEach((key) => delete errors[key]);
        notify({ type: 'success', text: t('admin.catalog.saved') });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <form class="grid gap-4 rounded-lg border border-surface-200 bg-white p-4 md:grid-cols-2" novalidate @submit.prevent="save">
        <FormField id="store-name" v-slot="{ invalid }" :label="$t('admin.settings.name')" required :error="errors.name"><InputText id="store-name" v-model="form.name" :invalid="invalid" /></FormField>
        <FormField id="store-email" v-slot="{ invalid }" :label="$t('admin.settings.contact_email')" :error="errors.contactEmail"><InputText id="store-email" v-model="form.contactEmail" type="email" :invalid="invalid" /></FormField>
        <FormField id="store-logo" v-slot="{ invalid }" :label="$t('admin.settings.logo_url')" :error="errors.logoUrl"><InputText id="store-logo" v-model="form.logoUrl" :invalid="invalid" /></FormField>
        <FormField id="store-favicon" v-slot="{ invalid }" :label="$t('admin.settings.favicon_url')" :error="errors.faviconUrl"><InputText id="store-favicon" v-model="form.faviconUrl" :invalid="invalid" /></FormField>
        <FormField id="store-primary" v-slot="{ invalid }" :label="$t('admin.settings.primary_color')" :error="errors.primaryColor">
            <div class="flex gap-2"><input v-model="form.primaryColor" type="color" class="h-10 w-12 rounded border border-surface-300" :aria-label="$t('admin.settings.primary_color')"><InputText id="store-primary" v-model="form.primaryColor" class="grow uppercase" :invalid="invalid" /></div>
        </FormField>
        <FormField id="store-accent" v-slot="{ invalid }" :label="$t('admin.settings.accent_color')" :error="errors.accentColor">
            <div class="flex gap-2"><input v-model="form.accentColor" type="color" class="h-10 w-12 rounded border border-surface-300" :aria-label="$t('admin.settings.accent_color')"><InputText id="store-accent" v-model="form.accentColor" class="grow uppercase" :invalid="invalid" /></div>
        </FormField>
        <FormField id="store-prefix" v-slot="{ invalid }" :label="$t('admin.settings.order_prefix')" :help="$t('admin.settings.order_prefix_help')" :error="errors.orderNumberPrefix"><InputText id="store-prefix" v-model="form.orderNumberPrefix" class="uppercase" :invalid="invalid" /></FormField>
        <FormField id="store-low" v-slot="{ invalid }" :label="$t('admin.settings.low_stock')" :help="$t('admin.settings.low_stock_help')" :error="errors.lowStockThreshold"><InputNumber v-model="form.lowStockThreshold" input-id="store-low" :min="0" :use-grouping="false" :invalid="invalid" /></FormField>
        <div class="flex items-center gap-3 rounded-md p-3 md:col-span-2" :style="{ background: form.primaryColor, color: '#fff' }">
            <strong>{{ form.name }}</strong><span class="rounded px-2 py-0.5 text-sm font-semibold" :style="{ background: form.accentColor, color: '#111' }">{{ $t('admin.settings.preview') }}</span>
        </div>
        <div class="md:col-span-2"><Button type="submit" :loading="saving" :label="$t('admin.catalog.save')" /></div>
    </form>
</template>
