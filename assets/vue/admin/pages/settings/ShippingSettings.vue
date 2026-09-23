<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';
import AppIcon from '../../../components/AppIcon.vue';
import FormField from '../../../components/FormField.vue';
import { api } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';

// Settings → Shipping methods: flat price, free over an order value (incl. VAT), or by weight.
// Prices are net; the shop adds VAT like on products.
const props = defineProps({ settings: { type: Object, required: true } });
const emit = defineEmits(['update']);
const dialog = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = reactive({});
const EMPTY = { code: '', name: '', description: '', calculator: 'flat', amount: '', threshold: '', brackets: [{ upToKg: '', amount: '' }], allowedCountries: [], position: 0, isActive: true };
const form = reactive(structuredClone(EMPTY));
const calculators = ['flat', 'free_over_threshold', 'weight_based'].map((value) => ({ value, label: t(`admin.settings.calculator.${value}`) }));

function edit(method = null) {
    Object.keys(errors).forEach((key) => delete errors[key]);
    Object.assign(form, structuredClone(EMPTY), method ? structuredClone({ ...method, amount: method.amount ?? '', threshold: method.threshold ?? '', brackets: method.brackets.length ? method.brackets : EMPTY.brackets }) : { position: props.settings.shippingMethods.length + 1 });
    editing.value = method?.id ?? null;
    dialog.value = true;
}

async function save() {
    saving.value = true;
    try {
        const body = { ...form, brackets: form.calculator === 'weight_based' ? form.brackets : [] };
        emit('update', await (editing.value ? api.put(`/api/admin/settings/shipping-methods/${editing.value}`, body) : api.post('/api/admin/settings/shipping-methods', body)));
        dialog.value = false;
        notify({ type: 'success', text: t('admin.catalog.saved') });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        saving.value = false;
    }
}

const summary = (m) => (m.calculator === 'weight_based'
    ? t('admin.settings.weight_summary', { count: m.brackets.length })
    : m.calculator === 'free_over_threshold' ? t('admin.settings.free_summary', { amount: m.amount, threshold: m.threshold }) : t('admin.settings.flat_summary', { amount: m.amount }));
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4">
        <div class="flex justify-end"><Button @click="edit()"><AppIcon name="add" /> {{ $t('admin.settings.new_shipping') }}</Button></div>
        <ul class="divide-y divide-surface-100">
            <li v-for="method in settings.shippingMethods" :key="method.id" class="flex flex-wrap items-center gap-3 py-2">
                <button type="button" class="font-semibold text-primary-700 hover:underline" @click="edit(method)">{{ method.name }}</button>
                <span class="text-sm text-surface-600">{{ summary(method) }}</span>
                <span class="grow text-xs text-surface-500">{{ method.allowedCountries.length ? method.allowedCountries.join(', ') : $t('admin.settings.all_countries') }}</span>
                <Tag :severity="method.isActive ? 'success' : 'secondary'" :value="method.isActive ? $t('admin.catalog.active') : $t('admin.catalog.inactive')" />
            </li>
        </ul>
        <Dialog v-model:visible="dialog" modal :header="editing ? $t('admin.settings.edit_shipping') : $t('admin.settings.new_shipping')" class="w-full max-w-xl">
            <template #closeicon><AppIcon name="close" /></template>
            <form class="grid gap-3 sm:grid-cols-2" novalidate @submit.prevent="save">
                <FormField id="ship-name" v-slot="{ invalid }" :label="$t('admin.settings.shipping_name')" required :error="errors.name"><InputText id="ship-name" v-model="form.name" :invalid="invalid" /></FormField>
                <FormField id="ship-code" v-slot="{ invalid }" :label="$t('admin.settings.code')" required :error="errors.code"><InputText id="ship-code" v-model="form.code" :invalid="invalid" /></FormField>
                <FormField id="ship-desc" v-slot="{ invalid }" class="sm:col-span-2" :label="$t('admin.settings.description')" :error="errors.description"><InputText id="ship-desc" v-model="form.description" :invalid="invalid" /></FormField>
                <FormField id="ship-calc" :label="$t('admin.settings.calculator_label')" class="sm:col-span-2" :error="errors.calculator">
                    <Select v-model="form.calculator" input-id="ship-calc" :options="calculators" option-label="label" option-value="value"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                </FormField>
                <FormField v-if="form.calculator !== 'weight_based'" id="ship-amount" v-slot="{ invalid }" :label="$t('admin.settings.price_net')" required :error="errors.amount"><InputText id="ship-amount" v-model="form.amount" inputmode="decimal" :invalid="invalid" /></FormField>
                <FormField v-if="form.calculator === 'free_over_threshold'" id="ship-threshold" v-slot="{ invalid }" :label="$t('admin.settings.free_from')" required :help="$t('admin.settings.free_from_help')" :error="errors.threshold"><InputText id="ship-threshold" v-model="form.threshold" inputmode="decimal" :invalid="invalid" /></FormField>
                <div v-if="form.calculator === 'weight_based'" class="flex flex-col gap-2 sm:col-span-2">
                    <span class="text-sm font-semibold">{{ $t('admin.settings.brackets') }}</span>
                    <div v-for="(bracket, index) in form.brackets" :key="index" class="flex items-center gap-2">
                        <InputText v-model="bracket.upToKg" class="w-32" inputmode="decimal" :placeholder="$t('admin.settings.up_to_kg')" :aria-label="$t('admin.settings.up_to_kg')" />
                        <InputText v-model="bracket.amount" class="w-32" inputmode="decimal" :placeholder="$t('admin.settings.price_net')" :aria-label="$t('admin.settings.price_net')" :invalid="!!errors[`brackets[${index}].amount`]" />
                        <Button text severity="danger" :aria-label="$t('admin.catalog.remove')" @click="form.brackets.splice(index, 1)"><AppIcon name="delete" /></Button>
                    </div>
                    <small class="text-surface-500">{{ $t('admin.settings.brackets_help') }}</small>
                    <small v-if="errors.brackets" class="text-red-600">{{ errors.brackets }}</small>
                    <div><Button text size="small" @click="form.brackets.push({ upToKg: '', amount: '' })"><AppIcon name="add" /> {{ $t('admin.settings.add_bracket') }}</Button></div>
                </div>
                <FormField id="ship-countries" :label="$t('admin.settings.countries')" class="sm:col-span-2" :help="$t('admin.settings.countries_help')">
                    <MultiSelect v-model="form.allowedCountries" input-id="ship-countries" :options="settings.countries" option-label="name" option-value="code" display="chip" class="w-full"><template #dropdownicon><AppIcon name="chevron-down" /></template></MultiSelect>
                </FormField>
                <FormField id="ship-position" :label="$t('admin.settings.position')"><InputNumber v-model="form.position" input-id="ship-position" :use-grouping="false" /></FormField>
                <label class="flex items-center gap-2 self-end pb-2"><ToggleSwitch v-model="form.isActive" /> {{ $t('admin.catalog.active') }}</label>
                <div class="flex justify-end gap-2 sm:col-span-2"><Button type="button" severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="dialog = false" /><Button type="submit" :loading="saving" :label="$t('admin.catalog.save')" /></div>
            </form>
        </Dialog>
    </div>
</template>
