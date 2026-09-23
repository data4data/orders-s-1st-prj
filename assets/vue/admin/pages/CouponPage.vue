<script setup>
import { inject, onMounted, reactive, ref, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import StoreRequired from '../StoreRequired.vue';
import { api, handleApiError } from '../../shared/api.js';
import { applyServerErrors } from '../../shared/forms.js';
import { notify } from '../../shared/notify.js';
import { t } from '../../shared/i18n.js';

// Admin → Coupons: percentage or fixed amount (net), optional minimum order, period and usage limit.
// Coupons are switched off instead of deleted: placed orders refer to them.
const tenant = inject('tenant');
const rows = ref([]);
const loading = ref(false);
const dialog = ref(false);
const saving = ref(false);
const editing = ref(null);
const errors = reactive({});
const EMPTY = { code: '', type: 'percentage', percent: '10', amount: '', minOrderNet: '', validFrom: '', validTo: '', usageLimit: null, isActive: true };
const form = reactive({ ...EMPTY });
const types = [{ value: 'percentage', label: t('admin.coupons.percentage') }, { value: 'fixed', label: t('admin.coupons.fixed') }];

async function load() {
    if (tenant.mode !== 'store') return;
    loading.value = true;
    try {
        rows.value = await api.get('/api/admin/coupons');
    } catch (error) {
        handleApiError(error);
    } finally {
        loading.value = false;
    }
}

function edit(coupon = null) {
    Object.keys(errors).forEach((key) => delete errors[key]);
    Object.assign(form, EMPTY, coupon ? { ...coupon, percent: coupon.percent ?? '', amount: coupon.amount ?? '', minOrderNet: coupon.minOrderNet ?? '', validFrom: coupon.validFrom ?? '', validTo: coupon.validTo ?? '' } : {});
    editing.value = coupon?.id ?? null;
    dialog.value = true;
}

async function save() {
    saving.value = true;
    const body = { ...form, percent: form.type === 'percentage' ? String(form.percent) : null, amount: form.type === 'fixed' ? form.amount : null, minOrderNet: form.minOrderNet || null, validFrom: form.validFrom || null, validTo: form.validTo || null };
    try {
        await (editing.value ? api.put(`/api/admin/coupons/${editing.value}`, body) : api.post('/api/admin/coupons', body));
        dialog.value = false;
        notify({ type: 'success', text: t('admin.catalog.saved') });
        load();
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ $t('admin.nav.coupons') }}</h1>
                <Button @click="edit()"><AppIcon name="add" /> {{ $t('admin.coupons.new') }}</Button>
            </div>
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable :value="rows" :loading="loading" data-key="id">
                    <template #empty><EmptyState icon="coupons" :title="$t('admin.coupons.empty')" /></template>
                    <Column :header="$t('admin.coupons.code')"><template #body="{ data }"><button type="button" class="font-mono font-semibold text-primary-700 hover:underline" @click="edit(data)">{{ data.code }}</button></template></Column>
                    <Column :header="$t('admin.coupons.discount')"><template #body="{ data }">{{ data.type === 'percentage' ? $t('admin.percent', { value: Number(data.percent) }) : $t('admin.coupons.amount_off', { amount: data.amount }) }}</template></Column>
                    <Column :header="$t('admin.coupons.conditions')">
                        <template #body="{ data }">
                            <div v-if="data.minOrderNet" class="text-sm">{{ $t('admin.coupons.min_order', { amount: data.minOrderNet }) }}</div>
                            <div v-if="data.validFrom || data.validTo" class="text-xs text-surface-500">{{ $t('admin.period', { from: data.validFrom ?? '…', to: data.validTo ?? '…' }) }}</div>
                        </template>
                    </Column>
                    <Column :header="$t('admin.coupons.used')"><template #body="{ data }">{{ data.timesUsed }}<template v-if="data.usageLimit">{{ ` / ${data.usageLimit}` }}</template></template></Column>
                    <Column :header="$t('admin.catalog.products.status')"><template #body="{ data }"><Tag :severity="data.isActive ? 'success' : 'secondary'" :value="data.isActive ? $t('admin.catalog.active') : $t('admin.catalog.inactive')" /></template></Column>
                </DataTable>
            </div>
        </div>
        <Dialog v-model:visible="dialog" modal :header="editing ? $t('admin.coupons.edit') : $t('admin.coupons.new')" class="w-full max-w-lg">
            <template #closeicon><AppIcon name="close" /></template>
            <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
                <FormField id="coupon-code" v-slot="{ invalid }" :label="$t('admin.coupons.code')" required :error="errors.code"><InputText id="coupon-code" v-model="form.code" class="uppercase" :invalid="invalid" /></FormField>
                <SelectButton v-model="form.type" :options="types" option-label="label" option-value="value" :allow-empty="false" :aria-label="$t('admin.coupons.discount')" />
                <FormField v-if="form.type === 'percentage'" id="coupon-percent" v-slot="{ invalid }" :label="$t('admin.coupons.percent')" required :error="errors.percent"><InputText id="coupon-percent" v-model="form.percent" inputmode="decimal" :invalid="invalid" /></FormField>
                <FormField v-else id="coupon-amount" v-slot="{ invalid }" :label="$t('admin.coupons.amount')" required :help="$t('admin.coupons.net_help')" :error="errors.amount"><InputText id="coupon-amount" v-model="form.amount" inputmode="decimal" :invalid="invalid" /></FormField>
                <FormField id="coupon-min" v-slot="{ invalid }" :label="$t('admin.coupons.min_order_label')" :help="$t('admin.coupons.net_help')" :error="errors.minOrderNet"><InputText id="coupon-min" v-model="form.minOrderNet" inputmode="decimal" :invalid="invalid" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField id="coupon-from" v-slot="{ invalid }" :label="$t('admin.coupons.valid_from')" :error="errors.validFrom"><InputText id="coupon-from" v-model="form.validFrom" type="date" :invalid="invalid" /></FormField>
                    <FormField id="coupon-to" v-slot="{ invalid }" :label="$t('admin.coupons.valid_to')" :error="errors.validTo || errors.periodValid"><InputText id="coupon-to" v-model="form.validTo" type="date" :invalid="invalid" /></FormField>
                </div>
                <FormField id="coupon-limit" v-slot="{ invalid }" :label="$t('admin.coupons.usage_limit')" :help="$t('admin.coupons.usage_help')" :error="errors.usageLimit"><InputNumber v-model="form.usageLimit" input-id="coupon-limit" :min="1" :use-grouping="false" :invalid="invalid" /></FormField>
                <label class="flex items-center gap-2"><ToggleSwitch v-model="form.isActive" /> {{ $t('admin.catalog.active') }}</label>
                <div class="flex justify-end gap-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.catalog.save')" /></div>
            </form>
        </Dialog>
    </StoreRequired>
</template>
