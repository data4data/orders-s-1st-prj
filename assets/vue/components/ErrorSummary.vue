<script setup>
import { computed } from 'vue';

// Error summary for long forms: lists the invalid fields with links that focus them.
const props = defineProps({
    errors: { type: Object, required: true },
    labels: { type: Object, required: true },
    idPrefix: { type: String, default: '' },
});
const invalid = computed(() => Object.keys(props.errors).filter((field) => props.errors[field]));
const focus = (field) => document.getElementById(`${props.idPrefix}${field}`)?.focus();
</script>

<template>
    <div v-if="invalid.length" class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert">
        <strong>{{ $t('form.summary', { count: invalid.length }) }}</strong>
        <template v-for="(field, index) in invalid" :key="field">
            <span v-if="index"> · </span>
            <a :href="`#${idPrefix}${field}`" class="underline" @click.prevent="focus(field)">{{ labels[field] }}</a>
        </template>
    </div>
</template>
