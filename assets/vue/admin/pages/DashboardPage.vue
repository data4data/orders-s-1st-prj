<script setup>
import { inject } from 'vue';
import EmptyState from '../../components/EmptyState.vue';

const tenant = inject('tenant');
</script>

<template>
    <div class="rounded-lg border border-surface-200 bg-white p-6">
        <EmptyState v-if="tenant.mode === 'none'" icon="store" :title="$t('admin.coming_soon.pick_store_title')" :text="$t('admin.coming_soon.pick_store_text')" />
        <EmptyState v-else icon="dashboard" :title="$t('admin.coming_soon.title', { page: $t('admin.nav.dashboard'), phase: 6 })" :text="$t('admin.coming_soon.text')" />
    </div>
</template>
