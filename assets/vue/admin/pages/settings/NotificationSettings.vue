<script setup>
import ToggleSwitch from 'primevue/toggleswitch';
import { api, handleApiError } from '../../../shared/api.js';

// Settings → Email notifications: order emails to customers and the contact form email to the shop.
const props = defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);

async function toggle(key, value) {
    try {
        emit('update', await api.put('/api/admin/settings/notifications', { ...props.settings.notifications, [key]: value }));
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <ul class="flex flex-col divide-y divide-surface-100 rounded-lg border border-surface-200 bg-white px-4">
        <li v-for="(enabled, key) in settings.notifications" :key="key" class="flex items-center justify-between gap-4 py-3">
            <span><strong>{{ $t(`admin.settings.notification.${key}.title`) }}</strong><br><span class="text-sm text-surface-600">{{ $t(`admin.settings.notification.${key}.text`) }}</span></span>
            <ToggleSwitch :model-value="enabled" :aria-label="$t(`admin.settings.notification.${key}.title`)" @update:model-value="(value) => toggle(key, value)" />
        </li>
    </ul>
</template>
