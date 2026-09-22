<script setup>
import AppIcon from './AppIcon.vue';

// Empty state standard: icon, one-line title, one explanation line, one action (slot).
defineProps({
    icon: { type: String, default: 'empty' },
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center gap-1 py-6 text-center">
        <AppIcon :name="icon" class="text-3xl text-surface-400" />
        <div class="font-semibold text-surface-900">{{ title }}</div>
        <div v-if="text" class="text-sm text-surface-600">{{ text }}</div>
        <div class="mt-2"><slot /></div>
    </div>
</template>
