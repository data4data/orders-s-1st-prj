<script setup>
import { inject } from 'vue';
import EmptyState from '../components/EmptyState.vue';

// Store-scoped admin screens need one selected store (not "All stores").
const tenant = inject('tenant');
</script>

<template>
    <div v-if="tenant.mode !== 'store'" class="rounded-lg border border-surface-200 bg-white p-6">
        <EmptyState icon="store" :title="$t('admin.coming_soon.pick_store_title')" :text="$t('admin.coming_soon.pick_store_text')" />
    </div>
    <slot v-else :store="tenant.store" />
</template>
