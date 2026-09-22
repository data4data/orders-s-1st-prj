<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import AppIcon from '../components/AppIcon.vue';
import AddressFields from '../customer/AddressFields.vue';
import { addressLine, emptyAddress } from '../customer/address.js';
import { api, handleApiError } from '../shared/api.js';
import { applyServerErrors } from '../shared/forms.js';
import { confirmAction } from '../shared/confirm.js';
import { notify } from '../shared/notify.js';
import { t } from '../shared/i18n.js';

// Account → Addresses: at least one billing and one delivery address always remain (decision #35);
// the server refuses the change otherwise and the message is shown as a toast.
const props = defineProps({
    addresses: { type: Array, required: true },
    countries: { type: Array, required: true },
});
const emit = defineEmits(['update:addresses']);

const editing = ref(null);
const draft = reactive(emptyAddress());
const errors = reactive({});
const saving = ref(false);

function edit(address = null) {
    Object.keys(errors).forEach((key) => delete errors[key]);
    Object.assign(draft, emptyAddress(), address ? { ...address } : {});
    editing.value = address ? address.id : 'new';
}

async function save() {
    saving.value = true;
    const body = Object.fromEntries(Object.keys(emptyAddress()).map((key) => [key, draft[key]]));
    try {
        const url = editing.value === 'new' ? '/api/account/addresses' : `/api/account/addresses/${editing.value}`;
        const addresses = editing.value === 'new' ? await api.post(url, body) : await api.put(url, body);
        emit('update:addresses', addresses);
        editing.value = null;
        notify({ type: 'success', text: t('account.address_saved') });
    } catch (error) {
        const prefixed = {};
        await applyServerErrors(error, prefixed);
        Object.entries(prefixed).forEach(([path, message]) => { errors[`address.${path}`] = message; });
    } finally {
        saving.value = false;
    }
}

async function remove(address) {
    const ok = await confirmAction({
        title: t('account.delete_address_title'),
        body: addressLine(address),
        confirmLabel: t('account.delete_address'),
        cancelLabel: t('account.keep_address'),
    });
    if (!ok) return;
    try {
        emit('update:addresses', await api.delete(`/api/account/addresses/${address.id}`));
        notify({ type: 'success', text: t('account.address_deleted') });
    } catch (error) {
        handleApiError(error);
    }
}

async function makeDefault(address, role) {
    const current = (flag) => props.addresses.find((a) => a[flag])?.id;
    const body = {
        billingId: role === 'billing' ? address.id : current('isDefaultBilling'),
        shippingId: role === 'shipping' ? address.id : current('isDefaultShipping'),
    };
    try {
        emit('update:addresses', await api.put('/api/account/addresses/defaults', body));
    } catch (error) {
        handleApiError(error);
    }
}
</script>

<template>
    <div>
        <div class="mb-4 flex justify-end"><Button @click="edit()"><AppIcon name="add" /> {{ $t('account.add_address') }}</Button></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <article v-for="address in addresses" :key="address.id" class="flex flex-col gap-2 rounded-lg border border-surface-200 bg-white p-4" data-testid="address-card">
                <div class="flex flex-wrap gap-1">
                    <Tag v-if="address.isDefaultBilling" severity="info" :value="$t('account.default_billing')" />
                    <Tag v-if="address.isDefaultShipping" severity="info" :value="$t('account.default_shipping')" />
                    <Tag v-if="address.usableForBilling && !address.isDefaultBilling" severity="secondary" :value="$t('account.billing')" />
                    <Tag v-if="address.usableForShipping && !address.isDefaultShipping" severity="secondary" :value="$t('account.delivery')" />
                </div>
                <div>
                    <strong>{{ address.firstName }} {{ address.lastName }}</strong><span v-if="address.company">, {{ address.company }}</span>
                    <div class="text-sm text-surface-700">{{ addressLine(address) }}</div>
                </div>
                <div class="mt-auto flex flex-wrap gap-1">
                    <Button text size="small" @click="edit(address)"><AppIcon name="edit" /> {{ $t('account.edit') }}</Button>
                    <Button v-if="address.usableForBilling && !address.isDefaultBilling" text size="small" severity="secondary" :label="$t('account.make_default_billing')" @click="makeDefault(address, 'billing')" />
                    <Button v-if="address.usableForShipping && !address.isDefaultShipping" text size="small" severity="secondary" :label="$t('account.make_default_shipping')" @click="makeDefault(address, 'shipping')" />
                    <Button text size="small" severity="danger" :aria-label="$t('account.delete_address')" @click="remove(address)"><AppIcon name="delete" /></Button>
                </div>
            </article>
        </div>

        <Dialog :visible="editing !== null" modal :header="editing === 'new' ? $t('account.add_address') : $t('account.edit_address')" class="w-full max-w-2xl" @update:visible="editing = null">
            <template #closeicon><AppIcon name="close" /></template>
            <form novalidate @submit.prevent="save">
                <AddressFields :address="draft" :errors="errors" prefix="address" :countries="countries" show-roles />
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" severity="secondary" outlined :label="$t('account.cancel')" @click="editing = null" />
                    <Button type="submit" :loading="saving" :label="$t('account.save')" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
