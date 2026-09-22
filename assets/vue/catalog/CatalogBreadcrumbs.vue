<script setup>
import AppIcon from '../components/AppIcon.vue';

defineProps({
    items: { type: Array, default: () => [] },
    current: { type: String, default: '' },
});
</script>

<template>
    <nav :aria-label="$t('catalog.breadcrumb')" class="text-sm text-surface-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="/" class="hover:text-surface-900">{{ $t('catalog.home') }}</a></li>
            <li v-for="item in items" :key="item.slug" class="flex items-center gap-1">
                <AppIcon name="chevron-right" /><a :href="`/c/${item.slug}`" class="hover:text-surface-900">{{ item.name }}</a>
            </li>
            <li v-if="current" class="flex items-center gap-1" aria-current="page"><AppIcon name="chevron-right" /><span class="text-surface-800">{{ current }}</span></li>
        </ol>
    </nav>
</template>
