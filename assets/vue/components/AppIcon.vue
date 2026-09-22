<script setup>
import { computed } from 'vue';
import { iconComponent } from '../shared/icons.js';

// Lucide icon by semantic name (assets/shared/icons.json), the same map as Twig icon().
const props = defineProps({
    name: { type: String, required: true },
    spin: { type: Boolean, default: false },
});
const component = computed(() => iconComponent(props.name));
</script>

<template>
    <component :is="component" class="icon" :class="{ 'icon-spin': spin }" size="1em" aria-hidden="true" />
</template>
