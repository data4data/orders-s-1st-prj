<script setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { confirmAction } from '../../../shared/confirm.js';
import { t } from '../../../shared/i18n.js';

// Platform → System: waiting messages per queue, failed jobs (retry or delete) and recent webhooks.
defineProps({ overview: { type: Object, default: null } });
const status = ref(null);

async function load(request = () => api.get('/api/admin/platform/system')) {
    try {
        status.value = await request();
    } catch (error) {
        handleApiError(error);
    }
}

async function remove(message) {
    if (await confirmAction({ title: t('admin.platform.delete_failed_title'), body: message.message, confirmLabel: t('admin.catalog.delete'), cancelLabel: t('admin.catalog.cancel') })) {
        load(() => api.delete(`/api/admin/platform/system/failed/${message.id}`));
    }
}
onMounted(() => load());
</script>

<template>
    <Skeleton v-if="!status" height="12rem" />
    <div v-else class="flex flex-col gap-4">
        <div class="flex justify-end"><Button text @click="load()"><AppIcon name="refresh" /> {{ $t('admin.platform.refresh') }}</Button></div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="queue in status.queues" :key="queue.queue" class="rounded-lg border border-surface-200 bg-white p-4">
                <div class="text-sm text-surface-600">{{ $t('admin.platform.queue', { name: queue.queue }) }}</div>
                <div class="text-2xl font-bold" :class="queue.queue === 'failed' && queue.waiting ? 'text-red-700' : ''">{{ queue.waiting }}</div>
                <div v-if="queue.oldest" class="text-xs text-surface-500">{{ $t('admin.platform.oldest', { date: queue.oldest }) }}</div>
            </div>
        </div>
        <section class="rounded-lg border border-surface-200 bg-white p-4">
            <h2 class="mb-2 font-semibold">{{ $t('admin.platform.failed') }}</h2>
            <EmptyState v-if="!status.failed.length" icon="success" :title="$t('admin.platform.no_failed')" />
            <ul v-else class="divide-y divide-surface-100 text-sm">
                <li v-for="message in status.failed" :key="message.id" class="flex flex-wrap items-center gap-3 py-2">
                    <span class="grow"><span class="font-mono">{{ message.message }}</span><br><span class="text-red-700">{{ message.error }}</span></span>
                    <span class="text-xs text-surface-500">{{ message.failedAt }}</span>
                    <Button text size="small" :label="$t('admin.platform.retry')" @click="load(() => api.post(`/api/admin/platform/system/failed/${message.id}/retry`))" />
                    <Button text size="small" severity="danger" :aria-label="$t('admin.catalog.delete')" @click="remove(message)"><AppIcon name="delete" /></Button>
                </li>
            </ul>
        </section>
        <section class="rounded-lg border border-surface-200 bg-white p-4">
            <h2 class="mb-2 font-semibold">{{ $t('admin.platform.webhooks') }}</h2>
            <EmptyState v-if="!status.webhooks.length" icon="payment" :title="$t('admin.platform.no_webhooks')" />
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr v-for="hook in status.webhooks" :key="hook.eventId" class="border-b border-surface-100">
                        <td class="py-1">{{ hook.receivedAt }}</td><td>{{ hook.store }}</td><td>{{ hook.gateway }} · {{ hook.type }}</td><td class="font-mono text-xs">{{ hook.eventId }}</td>
                        <td :class="hook.result?.startsWith('ignored') ? 'text-amber-700' : hook.result ? 'text-green-700' : 'text-surface-500'">{{ hook.result ?? $t('admin.platform.waiting') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
