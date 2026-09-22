<script setup>
import Button from 'primevue/button';
import EmptyState from '../../components/EmptyState.vue';
</script>

<template>
    <div class="rounded-lg border border-surface-200 bg-white p-6">
        <EmptyState icon="alert" :title="$t('admin.not_found.title')" :text="$t('admin.not_found.text')">
            <router-link v-slot="{ navigate }" to="/" custom><Button :label="$t('admin.not_found.back')" @click="navigate" /></router-link>
        </EmptyState>
    </div>
</template>
