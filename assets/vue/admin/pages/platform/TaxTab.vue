<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import AppIcon from '../../../components/AppIcon.vue';
import FormField from '../../../components/FormField.vue';
import { api } from '../../../shared/api.js';
import { applyServerErrors } from '../../../shared/forms.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';

// Platform → Countries, VAT rates and tax categories. Rates have validity dates; periods of one
// country and category may not overlap, so a new rate starts the day after the current one ends.
const props = defineProps({ overview: { type: Object, required: true } });
const emit = defineEmits(['update']);
const rate = reactive({ countryCode: 'NL', taxCategory: props.overview.taxCategories[0]?.code ?? 'standard', rate: '', validFrom: '', validTo: '' });
const country = reactive({ code: '', name: '', isEu: true });
const category = reactive({ code: '', name: '' });
const errors = reactive({});
const busy = ref(false);

async function submit(url, body, reset) {
    busy.value = true;
    try {
        emit('update', await api.post(url, body));
        Object.keys(errors).forEach((key) => delete errors[key]);
        reset();
        notify({ type: 'success', text: t('admin.catalog.saved') });
    } catch (error) {
        await applyServerErrors(error, errors);
    } finally {
        busy.value = false;
    }
}
const today = (r) => r.validFrom <= new Date().toISOString().slice(0, 10) && (!r.validTo || r.validTo >= new Date().toISOString().slice(0, 10));
</script>

<template>
    <div class="flex flex-col gap-4">
        <section class="rounded-lg border border-surface-200 bg-white p-4">
            <h2 class="mb-2 font-semibold">{{ $t('admin.platform.vat_rates') }}</h2>
            <div v-for="c in overview.countries" :key="c.code" class="mb-3">
                <div class="font-semibold">{{ c.name }} <span class="text-sm text-surface-500">{{ c.code }}{{ c.isEu ? ' · EU' : '' }}</span></div>
                <p v-if="!c.rates.length" class="text-sm text-surface-500">{{ $t('admin.platform.no_rates') }}</p>
                <table v-else class="w-full max-w-2xl text-sm">
                    <tbody>
                        <tr v-for="r in c.rates" :key="r.id" :class="today(r) ? 'font-semibold' : 'text-surface-500'">
                            <td class="py-0.5">{{ r.taxCategory }}</td><td>{{ $t('admin.percent', { value: Number(r.rate) }) }}</td><td>{{ $t('admin.period', { from: r.validFrom, to: r.validTo ?? '…' }) }}</td><td>{{ today(r) ? $t('admin.platform.current') : '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <form class="mt-3 grid gap-2 border-t border-surface-100 pt-3 sm:grid-cols-6" novalidate @submit.prevent="submit('/api/admin/platform/tax-rates', rate, () => { rate.rate = ''; rate.validFrom = ''; rate.validTo = ''; })">
                <FormField id="rate-country" :label="$t('address.country')" :error="errors.countryCode">
                    <Select v-model="rate.countryCode" input-id="rate-country" :options="overview.countries" option-label="code" option-value="code"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                </FormField>
                <FormField id="rate-category" :label="$t('admin.platform.category')" :error="errors.taxCategory">
                    <Select v-model="rate.taxCategory" input-id="rate-category" :options="overview.taxCategories" option-label="code" option-value="code"><template #dropdownicon><AppIcon name="chevron-down" /></template></Select>
                </FormField>
                <FormField id="rate-rate" v-slot="{ invalid }" :label="$t('admin.platform.rate')" :error="errors.rate"><InputText id="rate-rate" v-model="rate.rate" inputmode="decimal" :invalid="invalid" /></FormField>
                <FormField id="rate-from" v-slot="{ invalid }" :label="$t('admin.coupons.valid_from')" :error="errors.validFrom"><InputText id="rate-from" v-model="rate.validFrom" type="date" :invalid="invalid" /></FormField>
                <FormField id="rate-to" v-slot="{ invalid }" :label="$t('admin.coupons.valid_to')" :error="errors.validTo"><InputText id="rate-to" v-model="rate.validTo" type="date" :invalid="invalid" /></FormField>
                <div class="self-end"><Button type="submit" :loading="busy" :label="$t('admin.platform.add_rate')" /></div>
            </form>
        </section>
        <div class="grid gap-4 md:grid-cols-2">
            <form class="flex flex-col gap-2 rounded-lg border border-surface-200 bg-white p-4" novalidate @submit.prevent="submit('/api/admin/platform/countries', country, () => { country.code = ''; country.name = ''; })">
                <h2 class="font-semibold">{{ $t('admin.platform.add_country') }}</h2>
                <FormField id="country-code" v-slot="{ invalid }" :label="$t('admin.settings.code')" :error="errors.code"><InputText id="country-code" v-model="country.code" class="uppercase" maxlength="2" :invalid="invalid" /></FormField>
                <FormField id="country-name" v-slot="{ invalid }" :label="$t('admin.settings.name')" :error="errors.name"><InputText id="country-name" v-model="country.name" :invalid="invalid" /></FormField>
                <label class="flex items-center gap-2 text-sm"><Checkbox v-model="country.isEu" binary /> {{ $t('admin.platform.eu') }}</label>
                <div><Button type="submit" :loading="busy" :label="$t('admin.catalog.add')" /></div>
            </form>
            <form class="flex flex-col gap-2 rounded-lg border border-surface-200 bg-white p-4" novalidate @submit.prevent="submit('/api/admin/platform/tax-categories', category, () => { category.code = ''; category.name = ''; })">
                <h2 class="font-semibold">{{ $t('admin.platform.tax_categories') }}</h2>
                <p class="text-sm text-surface-600">{{ overview.taxCategories.map((c) => `${c.name} (${c.code})`).join(', ') }}</p>
                <FormField id="category-code" v-slot="{ invalid }" :label="$t('admin.settings.code')" :error="errors.code"><InputText id="category-code" v-model="category.code" :invalid="invalid" /></FormField>
                <FormField id="category-name" :label="$t('admin.settings.name')"><InputText id="category-name" v-model="category.name" /></FormField>
                <div><Button type="submit" :loading="busy" :label="$t('admin.catalog.add')" /></div>
            </form>
        </div>
    </div>
</template>
