<script setup>
// Storefront footer (Vue). The Bootstrap twin is templates/bootstrap/_footer.html.twig: keep both in sync.
defineProps({
    layout: { type: Object, required: true },
});
const links = { about: '/about', faq: '/faq', contact: '/contact', shipping_info: '/shipping-info', safety_data_sheets: '/safety-data-sheets', terms: '/terms', privacy: '/privacy' };
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-12 border-t border-surface-200">
        <div class="mx-auto flex max-w-6xl flex-wrap gap-4 px-4 py-6 text-sm">
            <a v-for="(path, key) in links" :key="key" :href="path" class="text-surface-600 hover:text-surface-900">{{ $t(`layout.footer.${key}`) }}</a>
            <span class="ml-auto text-surface-500">&copy; {{ year }} {{ layout.store.name }} · {{ $t('layout.footer.rights') }}</span>
        </div>
    </footer>
</template>
