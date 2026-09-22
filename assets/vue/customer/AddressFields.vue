<script setup>
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import AppIcon from '../components/AppIcon.vue';
import FormField from '../components/FormField.vue';

// Address fields shared by checkout and the address book. Server violations arrive as
// "<prefix>.<field>" (e.g. "billing.postcode") in the `errors` object.
const address = defineModel('address', { type: Object, required: true });
const errors = defineModel('errors', { type: Object, required: true });
const props = defineProps({
    prefix: { type: String, required: true },
    countries: { type: Array, required: true },
    showRoles: { type: Boolean, default: false },
    autocomplete: { type: String, default: 'billing' },
});
const id = (field) => `${props.prefix}-${field}`;
const error = (field) => errors.value[`${props.prefix}.${field}`] ?? '';
const clear = (field) => { delete errors.value[`${props.prefix}.${field}`]; };
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-6">
        <FormField :id="id('firstName')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.first_name')" required :error="error('firstName')">
            <InputText :id="id('firstName')" v-model="address.firstName" :invalid="invalid" :autocomplete="`${autocomplete} given-name`" @input="clear('firstName')" />
        </FormField>
        <FormField :id="id('lastName')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.last_name')" required :error="error('lastName')">
            <InputText :id="id('lastName')" v-model="address.lastName" :invalid="invalid" :autocomplete="`${autocomplete} family-name`" @input="clear('lastName')" />
        </FormField>
        <FormField :id="id('street')" v-slot="{ invalid }" class="sm:col-span-4" :label="$t('address.street')" required :error="error('street')">
            <InputText :id="id('street')" v-model="address.street" :invalid="invalid" :autocomplete="`${autocomplete} address-line1`" @input="clear('street')" />
        </FormField>
        <FormField :id="id('houseNumber')" v-slot="{ invalid }" class="sm:col-span-2" :label="$t('address.house_number')" required :error="error('houseNumber')">
            <InputText :id="id('houseNumber')" v-model="address.houseNumber" :invalid="invalid" @input="clear('houseNumber')" />
        </FormField>
        <FormField :id="id('postcode')" v-slot="{ invalid }" class="sm:col-span-2" :label="$t('address.postcode')" required :error="error('postcode')">
            <InputText :id="id('postcode')" v-model="address.postcode" :invalid="invalid" :autocomplete="`${autocomplete} postal-code`" @input="clear('postcode')" />
        </FormField>
        <FormField :id="id('city')" v-slot="{ invalid }" class="sm:col-span-4" :label="$t('address.city')" required :error="error('city')">
            <InputText :id="id('city')" v-model="address.city" :invalid="invalid" :autocomplete="`${autocomplete} address-level2`" @input="clear('city')" />
        </FormField>
        <FormField :id="id('countryCode')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.country')" required :error="error('countryCode')">
            <Select v-model="address.countryCode" :input-id="id('countryCode')" :options="countries" option-label="name" option-value="code" :invalid="invalid" @change="clear('countryCode')">
                <template #dropdownicon><AppIcon name="chevron-down" /></template>
            </Select>
        </FormField>
        <FormField :id="id('phone')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.phone')" :error="error('phone')">
            <InputText :id="id('phone')" v-model="address.phone" :invalid="invalid" type="tel" :autocomplete="`${autocomplete} tel`" @input="clear('phone')" />
        </FormField>
        <FormField :id="id('company')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.company')" :error="error('company')">
            <InputText :id="id('company')" v-model="address.company" :invalid="invalid" :autocomplete="`${autocomplete} organization`" @input="clear('company')" />
        </FormField>
        <FormField :id="id('vatId')" v-slot="{ invalid }" class="sm:col-span-3" :label="$t('address.vat_id')" :help="$t('address.vat_id_help')" :error="error('vatId')">
            <InputText :id="id('vatId')" v-model="address.vatId" :invalid="invalid" @input="clear('vatId')" />
        </FormField>
        <div v-if="showRoles" class="flex flex-wrap gap-4 sm:col-span-6">
            <label class="flex items-center gap-2"><Checkbox v-model="address.usableForBilling" binary :input-id="id('billing')" /> {{ $t('address.use_billing') }}</label>
            <label class="flex items-center gap-2"><Checkbox v-model="address.usableForShipping" binary :input-id="id('shipping')" /> {{ $t('address.use_shipping') }}</label>
        </div>
        <small v-if="errors[`${prefix}.usableForSomething`]" class="text-red-600 sm:col-span-6">{{ errors[`${prefix}.usableForSomething`] }}</small>
    </div>
</template>
