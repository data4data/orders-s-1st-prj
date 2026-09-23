<script setup>
import { onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Paginator from 'primevue/paginator';
import ToggleSwitch from 'primevue/toggleswitch';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import { api, handleApiError } from '../../../shared/api.js';

// Customers → Contact messages: newest first; opening a message marks it as read.
const emit = defineEmits(['read']);
const list = ref(null);
const unreadOnly = ref(false);
const page = ref(1);
const open = ref(null);

async function load() {
    try {
        list.value = await api.get(`/api/admin/contact-messages?page=${page.value}${unreadOnly.value ? '&unread=1' : ''}`);
    } catch (error) {
        handleApiError(error);
    }
}

async function toggle(message) {
    open.value = open.value === message.id ? null : message.id;
    if (!message.read) {
        try {
            await api.post(`/api/admin/contact-messages/${message.id}/read`);
            message.read = true;
            emit('read');
        } catch (error) {
            handleApiError(error);
        }
    }
}

watch(unreadOnly, () => { page.value = 1; load(); });
onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-3">
        <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="unreadOnly" /> {{ $t('admin.customers.unread_only') }}</label>
        <Skeleton v-if="!list" height="10rem" />
        <div v-else class="rounded-lg border border-surface-200 bg-white">
            <EmptyState v-if="!list.items.length" icon="mail" :title="$t('admin.customers.no_messages')" />
            <ul v-else class="divide-y divide-surface-100">
                <li v-for="message in list.items" :key="message.id">
                    <button type="button" class="flex w-full flex-wrap items-center gap-3 px-4 py-3 text-left hover:bg-surface-50" :aria-expanded="open === message.id" @click="toggle(message)">
                        <span class="h-2 w-2 rounded-full" :class="message.read ? 'bg-transparent' : 'bg-amber-500'" :aria-label="message.read ? undefined : $t('admin.customers.unread')" />
                        <span class="w-48 truncate" :class="{ 'font-semibold': !message.read }">{{ message.name }}</span>
                        <Tag severity="secondary" :value="$t(`admin.customers.subject.${message.subject}`)" />
                        <span v-if="message.orderNumber" class="text-sm text-primary-700">{{ message.orderNumber }}</span>
                        <span class="grow truncate text-sm text-surface-600">{{ message.message }}</span>
                        <span class="text-xs text-surface-500">{{ new Date(message.createdAt).toLocaleString() }}</span>
                    </button>
                    <div v-if="open === message.id" class="bg-surface-50 px-4 pb-4 pt-1 text-sm">
                        <p class="whitespace-pre-line">{{ message.message }}</p>
                        <Button as="a" :href="`mailto:${message.email}?subject=${encodeURIComponent('Re: ' + (message.orderNumber ?? $t(`admin.customers.subject.${message.subject}`)))}`" size="small" class="mt-2"><AppIcon name="mail" /> {{ $t('admin.customers.reply', { email: message.email }) }}</Button>
                    </div>
                </li>
            </ul>
            <Paginator v-if="list.total > 20" :rows="20" :total-records="list.total" :first="(page - 1) * 20" @page="(e) => { page = e.page + 1; load(); }" />
        </div>
    </div>
</template>
