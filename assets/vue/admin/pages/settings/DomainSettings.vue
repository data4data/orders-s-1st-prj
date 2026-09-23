<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import { api, handleApiError } from '../../../shared/api.js';
import { confirmAction } from '../../../shared/confirm.js';
import { t } from '../../../shared/i18n.js';

// Settings → Domains: host names that open this shop; the primary one is used in links.
defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);
const host = ref('');
const error = ref('');

async function run(request) {
    try {
        emit('update', await request());
        return true;
    } catch (e) {
        await handleApiError(e);
        return false;
    }
}

async function add() {
    error.value = '';
    try {
        emit('update', await api.post('/api/admin/settings/domains', { host: host.value }));
        host.value = '';
    } catch (e) {
        const errors = { host: '' };
        await handleApiError(e, { errors });
        error.value = errors.host;
    }
}

async function remove(domain) {
    if (await confirmAction({ title: t('admin.settings.remove_domain_title', { host: domain.host }), body: t('admin.settings.remove_domain_body'), confirmLabel: t('admin.settings.remove_domain'), cancelLabel: t('admin.catalog.cancel') })) {
        run(() => api.delete(`/api/admin/settings/domains/${domain.id}`));
    }
}
</script>

<template>
    <div class="flex flex-col gap-4 rounded-lg border border-surface-200 bg-white p-4">
        <ul class="divide-y divide-surface-100">
            <li v-for="domain in settings.domains" :key="domain.id" class="flex flex-wrap items-center gap-3 py-2">
                <span class="grow font-mono">{{ domain.host }}</span>
                <Tag v-if="domain.isPrimary" severity="info" :value="$t('admin.settings.primary')" />
                <Button v-else text size="small" :label="$t('admin.settings.make_primary')" @click="run(() => api.put(`/api/admin/settings/domains/${domain.id}/primary`))" />
                <Button v-if="!domain.isPrimary" text size="small" severity="danger" :aria-label="$t('admin.settings.remove_domain')" @click="remove(domain)"><AppIcon name="delete" /></Button>
            </li>
        </ul>
        <form class="flex max-w-lg flex-col gap-1" novalidate @submit.prevent="add">
            <label for="new-host" class="text-sm font-semibold">{{ $t('admin.settings.add_domain') }}</label>
            <div class="flex gap-2"><InputText id="new-host" v-model="host" class="grow" placeholder="shop.example.com" :invalid="!!error" /><Button type="submit" :label="$t('admin.catalog.add')" /></div>
            <small v-if="error" class="text-red-600">{{ error }}</small>
            <small class="text-surface-500">{{ $t('admin.settings.domain_help') }}</small>
        </form>
    </div>
</template>
