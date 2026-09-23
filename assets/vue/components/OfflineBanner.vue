<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { notify } from '../shared/notify.js';
import { t } from '../shared/i18n.js';

// "You're offline" banner; submit buttons are paused by the .is-offline body class.
const offline = ref(navigator.onLine === false);
const update = () => {
    offline.value = navigator.onLine === false;
    document.body.classList.toggle('is-offline', offline.value);
};
const backOnline = () => {
    update();
    notify({ type: 'success', text: t('offline.back') });
};
onMounted(() => {
    update();
    window.addEventListener('offline', update);
    window.addEventListener('online', backOnline);
});
onBeforeUnmount(() => {
    window.removeEventListener('offline', update);
    window.removeEventListener('online', backOnline);
});
</script>

<template>
    <div v-if="offline" class="fixed inset-x-0 bottom-0 z-[1100] bg-amber-400 px-4 py-2 text-center font-semibold text-surface-900" role="status">
        <AppIcon name="offline" /> {{ $t('offline.banner') }}
    </div>
</template>
