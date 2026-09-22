<script setup>
import AppIcon from './AppIcon.vue';

// Form field standard (pages.html → UI standards → Form fields): label with * for required fields,
// the input (slot), then a short red message directly under the field.
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    help: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="text-sm font-semibold text-surface-800">
            {{ label }}<span v-if="required" class="text-red-600"> *</span>
        </label>
        <slot :invalid="!!error" :described-by="error ? `${id}-error` : help ? `${id}-help` : undefined" />
        <small v-if="error" :id="`${id}-error`" class="flex items-center gap-1 text-red-600">
            <AppIcon name="alert" /> {{ error }}
        </small>
        <small v-else-if="help" :id="`${id}-help`" class="text-surface-500">{{ help }}</small>
    </div>
</template>
