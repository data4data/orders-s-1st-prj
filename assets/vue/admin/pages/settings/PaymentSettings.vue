<script setup>
import RadioButton from 'primevue/radiobutton';
import AppIcon from '../../../components/AppIcon.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';

// Settings → Payment gateway: one of the installed gateways (PaymentGatewayRegistry).
defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);

async function choose(code) {
    try {
        emit('update', await api.put('/api/admin/settings/payment-gateway', { code }));
        notify({ type: 'success', text: t('admin.catalog.saved') });
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4">
        <label v-for="code in settings.gateway.available" :key="code" class="flex cursor-pointer items-center gap-3 rounded-md border p-3" :class="settings.gateway.current === code ? 'border-primary bg-primary-50' : 'border-surface-200'">
            <RadioButton :model-value="settings.gateway.current" :value="code" name="gateway" @update:model-value="choose" />
            <AppIcon name="payment" />
            <span><strong>{{ $t(`admin.settings.gateway.${code}.name`) }}</strong><br><span class="text-sm text-surface-600">{{ $t(`admin.settings.gateway.${code}.text`) }}</span></span>
        </label>
        <p class="text-sm text-surface-500">{{ $t('admin.settings.gateway_help') }}</p>
    </div>
</template>
