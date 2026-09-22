<script setup>
import EmptyState from '../../components/EmptyState.vue';

defineProps({
    page: { type: String, required: true },
    phase: { type: Number, required: true },
});
</script>

<template>
    <div class="rounded-lg border border-surface-200 bg-white p-6">
        <EmptyState icon="clock" :title="$t('admin.coming_soon.title', { page: $t(page), phase })" :text="$t('admin.coming_soon.text')" />
    </div>
</template>
