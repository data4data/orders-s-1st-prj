<script setup>
import { onBeforeUnmount, ref } from 'vue';
import Toast from 'primevue/toast';
import AppIcon from './AppIcon.vue';

// The one toast outlet (pages.html → UI standards → Toasts). Lucide icons replace PrimeVue's own.
// PrimeVue already marks each message as a live region (role="alert"), so the content has no role.
const ICONS = { success: 'success', info: 'info', warn: 'warning', error: 'error' };
const BORDER = { success: 'border-l-green-600', info: 'border-l-sky-600', warn: 'border-l-amber-500', error: 'border-l-red-600' };
const ICON_COLOR = { success: 'text-green-600', info: 'text-sky-600', warn: 'text-amber-500', error: 'text-red-600' };

// Countdowns for rate-limit toasts ("Please wait 45 s…").
const now = ref(Date.now());
const timer = setInterval(() => { now.value = Date.now(); }, 1000);
onBeforeUnmount(() => clearInterval(timer));
const secondsLeft = (message) => {
    message.startedAt ??= Date.now();
    return Math.max(0, message.countdown - Math.floor((now.value - message.startedAt) / 1000));
};
</script>

<template>
    <Toast group="app" position="top-right" :breakpoints="{ '640px': { width: 'calc(100vw - 2rem)', right: '1rem', left: '1rem' } }">
        <template #container="{ message, closeCallback }">
            <div class="flex w-full items-start gap-3 rounded-md border-l-[5px] bg-white p-3 shadow-lg" :class="BORDER[message.severity]">
                <AppIcon :name="ICONS[message.severity]" class="mt-0.5 text-xl" :class="ICON_COLOR[message.severity]" />
                <div class="grow text-sm">
                    <div v-if="message.summary" class="font-semibold text-surface-900">{{ message.summary }}</div>
                    <div class="text-surface-600">
                        {{ message.countdown ? $t('errors.rate_limited', { seconds: secondsLeft(message) }) : message.detail }}
                    </div>
                    <div v-if="message.requestId" class="mt-1 text-xs text-surface-500">{{ $t('errors.reference', { code: message.requestId }) }}</div>
                </div>
                <button type="button" class="text-surface-500 hover:text-surface-900" :aria-label="$t('common.close')" @click="closeCallback">
                    <AppIcon name="close" />
                </button>
            </div>
        </template>
    </Toast>
</template>
