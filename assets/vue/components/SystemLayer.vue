<script setup>
import { onMounted } from 'vue';
import AppToast from './AppToast.vue';
import AppConfirmDialog from './AppConfirmDialog.vue';
import OfflineBanner from './OfflineBanner.vue';
import { notify } from '../shared/notify.js';

// Mounted once per page: toast outlet, confirmation dialog and offline banner for every island.
const props = defineProps({
    flashes: { type: Array, default: () => [] },
});
onMounted(() => props.flashes.forEach(({ type, text }) => notify({ type, text })));
</script>

<template>
    <AppToast />
    <AppConfirmDialog />
    <OfflineBanner />
</template>
