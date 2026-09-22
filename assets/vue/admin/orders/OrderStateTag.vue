<script setup>
import { computed } from 'vue';
import Tag from 'primevue/tag';

// Admin badge of an order place (OrderState::badge()). PrimeVue severities where they exist,
// fixed colours for indigo, teal and purple (architecture.md §4 → status mapping).
const props = defineProps({
    badge: { type: String, required: true },
    label: { type: String, required: true },
});
const CUSTOM = {
    indigo: '!bg-indigo-100 !text-indigo-800',
    teal: '!bg-teal-100 !text-teal-800',
    purple: '!bg-purple-100 !text-purple-800',
};
const severity = computed(() => (CUSTOM[props.badge] ? undefined : props.badge));
</script>

<template>
    <Tag :severity="severity" :class="CUSTOM[badge]" :value="$t(label)" />
</template>
