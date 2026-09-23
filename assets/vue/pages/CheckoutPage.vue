<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Checkbox from 'primevue/checkbox';
import RadioButton from 'primevue/radiobutton';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import AppIcon from '../components/AppIcon.vue';
import FormField from '../components/FormField.vue';
import CartTotals from '../cart/CartTotals.vue';
import AddressFields from '../customer/AddressFields.vue';
import { addressLine, emptyAddress } from '../customer/address.js';
import { api, handleApiError, ApiError } from '../shared/api.js';
import { notify } from '../shared/notify.js';
import { useUnsavedChanges } from '../shared/unsaved.js';
import { t } from '../shared/i18n.js';
import { formatMoney } from '../../shared/format.js';

// Checkout wizard (docs/diagrams/pages.html → Checkout): account → addresses → shipping → review
// → pay, with the order summary always visible. The server re-checks everything on submit.
const props = defineProps({ loginUrl: { type: String, required: true } });

const STEPS = ['account', 'addresses', 'shipping', 'review'];
const step = ref(0);
const data = ref(null);
const placing = ref(false);
const touched = ref(false);
const errors = reactive({});
const form = reactive({
    email: '',
    billingAddressId: null,
    billing: emptyAddress(),
    shippingSameAsBilling: true,
    shippingAddressId: null,
    shipping: emptyAddress(),
    shippingMethod: '',
    acceptTerms: false,
});

const customer = computed(() => data.value?.customer ?? null);
const cart = computed(() => data.value?.cart ?? null);
const billingBook = computed(() => customer.value?.addresses.filter((a) => a.usableForBilling) ?? []);
const shippingBook = computed(() => customer.value?.addresses.filter((a) => a.usableForShipping) ?? []);
const shippingCountry = computed(() => {
    if (form.shippingSameAsBilling) {
        return form.billingAddressId ? customer.value?.addresses.find((a) => a.id === form.billingAddressId)?.countryCode : form.billing.countryCode;
    }
    return form.shippingAddressId ? customer.value?.addresses.find((a) => a.id === form.shippingAddressId)?.countryCode : form.shipping.countryCode;
});
const selectedShipping = computed(() => data.value?.shippingOptions.find((o) => o.code === form.shippingMethod) ?? null);
const totals = computed(() => {
    if (!cart.value) return null;
    const base = cart.value.totals;
    const option = selectedShipping.value;
    if (!option || option.gross === null) return { ...base, shippingGross: null };
    // Totals per shipping option come from the server (same pricer as PlaceOrder, checked via expectedTotal).
    return { ...base, shippingGross: option.gross, totalGross: option.totalGross, totalTax: option.totalTax };
});
const money = (cents) => formatMoney(cents, cart.value.currency);

useUnsavedChanges(() => touched.value && !placing.value);
watch(form, () => { touched.value = true; }, { deep: true });

async function load(country = null) {
    try {
        data.value = await api.get(`/api/checkout${country ? `?country=${country}` : ''}`);
        if (!data.value.cart.lines.length) {
            window.location.href = '/cart';
            return;
        }
        if (!form.shippingMethod || !data.value.shippingOptions.some((o) => o.code === form.shippingMethod && o.gross !== null)) {
            form.shippingMethod = data.value.shippingOptions.find((o) => o.gross !== null)?.code ?? '';
        }
    } catch (error) {
        handleApiError(error);
    }
}

onMounted(async () => {
    await load();
    if (customer.value) {
        const defaults = customer.value.addresses;
        form.billingAddressId = defaults.find((a) => a.isDefaultBilling)?.id ?? billingBook.value[0]?.id ?? null;
        const shipping = defaults.find((a) => a.isDefaultShipping)?.id ?? null;
        form.shippingSameAsBilling = !shipping || shipping === form.billingAddressId;
        form.shippingAddressId = form.shippingSameAsBilling ? null : shipping;
        step.value = 1;
    }
    touched.value = false;
});

watch(shippingCountry, (country, previous) => {
    if (data.value && country && country !== previous) load(country);
});

/** Client checks per step (the server has the final word). */
function validateStep(index) {
    Object.keys(errors).forEach((key) => delete errors[key]);
    const need = (path, value, message) => { if (!String(value ?? '').trim()) errors[path] = message; };
    if (index === 0 && !customer.value) {
        need('email', form.email, t('checkout.email_required'));
        if (form.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) errors.email = t('form.email');
    }
    if (index === 1) {
        const check = (prefix, address) => {
            ['firstName', 'lastName', 'street', 'houseNumber', 'postcode', 'city'].forEach((field) => need(`${prefix}.${field}`, address[field], t('form.required')));
        };
        if (!form.billingAddressId) check('billing', form.billing);
        if (!form.shippingSameAsBilling && !form.shippingAddressId) check('shipping', form.shipping);
    }
    if (index === 2) need('shippingMethod', form.shippingMethod, t('checkout.shipping_required'));
    if (index === 3 && !form.acceptTerms) errors.acceptTerms = t('checkout.terms_required');
    const invalid = Object.keys(errors);
    if (invalid.length) {
        document.getElementById(invalid[0].replace('.', '-'))?.focus();
    }
    return invalid.length === 0;
}

function next() {
    if (validateStep(step.value)) {
        step.value = Math.min(step.value + 1, STEPS.length - 1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function goTo(index) {
    if (index < step.value) step.value = index;
}

/** Which step owns a server violation, so the wizard can jump back to it. */
function stepOf(path) {
    if (path === 'email') return 0;
    if (/^(billing|shipping)(\.|AddressId|$)/.test(path)) return 1;
    if (path === 'shippingMethod') return 2;
    return 3;
}

async function placeOrder() {
    if (!validateStep(3)) return;
    placing.value = true;
    const payload = {
        email: customer.value ? null : form.email,
        billingAddressId: form.billingAddressId,
        billing: form.billingAddressId ? null : form.billing,
        shippingSameAsBilling: form.shippingSameAsBilling,
        shippingAddressId: form.shippingSameAsBilling ? null : form.shippingAddressId,
        shipping: form.shippingSameAsBilling || form.shippingAddressId ? null : form.shipping,
        shippingMethod: form.shippingMethod,
        acceptTerms: form.acceptTerms,
        expectedTotal: totals.value?.totalGross ?? null,
    };
    try {
        const placed = await api.post('/api/checkout', payload);
        window.location.href = placed.redirectUrl;
    } catch (error) {
        placing.value = false;
        if (error instanceof ApiError && error.kind === 'validation' && error.violations.length) {
            error.handled = true;
            error.violations.forEach(({ propertyPath, message }) => { errors[propertyPath] = message; });
            const first = error.violations[0];
            step.value = Math.min(...error.violations.map((v) => stepOf(v.propertyPath)));
            notify({ type: 'warning', title: t('errors.validation_title'), text: first.message });
            if (['cart', 'expectedTotal', 'coupon'].includes(first.propertyPath)) load(shippingCountry.value);
            return;
        }
        handleApiError(error);
    }
}
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-6">
        <h1 class="mb-4 text-2xl font-bold">{{ $t('checkout.title') }}</h1>
        <div v-if="!data" class="grid gap-6 md:grid-cols-3"><Skeleton class="md:col-span-2" height="20rem" /><Skeleton height="14rem" /></div>
        <div v-else class="grid items-start gap-6 md:grid-cols-3">
            <div class="md:col-span-2">
                <ol class="mb-4 flex flex-wrap gap-2 text-sm" :aria-label="$t('checkout.steps')">
                    <li v-for="(name, index) in STEPS" :key="name">
                        <button type="button" class="flex items-center gap-2 rounded-full border px-3 py-1" :class="index === step ? 'border-primary bg-primary text-primary-contrast' : index < step ? 'border-primary-300 text-primary-700' : 'border-surface-300 text-surface-500'" :disabled="index > step" :aria-current="index === step ? 'step' : undefined" @click="goTo(index)">
                            <AppIcon v-if="index < step" name="check" /><span v-else>{{ index + 1 }}</span> {{ $t(`checkout.step.${name}`) }}
                        </button>
                    </li>
                </ol>

                <section class="rounded-lg border border-surface-200 bg-white p-5">
                    <template v-if="step === 0">
                        <h2 class="mb-3 text-lg font-bold">{{ $t('checkout.step.account') }}</h2>
                        <div v-if="customer" class="flex items-center gap-2"><AppIcon name="account" /> {{ $t('checkout.logged_in_as', { email: customer.email }) }}</div>
                        <div v-else class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <h3 class="mb-2 font-semibold">{{ $t('checkout.guest') }}</h3>
                                <FormField id="email" v-slot="{ invalid }" :label="$t('checkout.email')" required :help="$t('checkout.email_help')" :error="errors.email">
                                    <InputText id="email" v-model="form.email" type="email" autocomplete="email" :invalid="invalid" @input="delete errors.email" />
                                </FormField>
                            </div>
                            <div class="rounded-md bg-surface-50 p-4">
                                <h3 class="mb-2 font-semibold">{{ $t('checkout.have_account') }}</h3>
                                <p class="mb-3 text-sm text-surface-600">{{ $t('checkout.login_benefit') }}</p>
                                <Button as="a" :href="props.loginUrl" severity="secondary" outlined :label="$t('layout.log_in')" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="step === 1">
                        <h2 class="mb-3 text-lg font-bold">{{ $t('checkout.billing') }}</h2>
                        <div v-if="billingBook.length" class="mb-4 flex flex-col gap-2">
                            <label v-for="address in billingBook" :key="address.id" class="flex cursor-pointer items-start gap-3 rounded-md border p-3" :class="form.billingAddressId === address.id ? 'border-primary bg-primary-50' : 'border-surface-200'">
                                <RadioButton v-model="form.billingAddressId" :value="address.id" name="billingAddressId" />
                                <span><strong>{{ address.firstName }} {{ address.lastName }}</strong><span v-if="address.company">, {{ address.company }}</span><br><span class="text-sm text-surface-600">{{ addressLine(address) }}</span></span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-md border p-3" :class="form.billingAddressId === null ? 'border-primary bg-primary-50' : 'border-surface-200'">
                                <RadioButton v-model="form.billingAddressId" :value="null" name="billingAddressId" /> {{ $t('checkout.new_address') }}
                            </label>
                        </div>
                        <AddressFields v-if="!form.billingAddressId" :address="form.billing" :errors="errors" prefix="billing" :countries="data.countries" autocomplete="section-billing billing" />

                        <label class="mt-5 flex items-center gap-2 font-semibold">
                            <Checkbox v-model="form.shippingSameAsBilling" binary input-id="shippingSameAsBilling" /> {{ $t('checkout.same_address') }}
                        </label>
                        <div v-if="!form.shippingSameAsBilling" class="mt-4">
                            <h2 class="mb-3 text-lg font-bold">{{ $t('checkout.delivery') }}</h2>
                            <div v-if="shippingBook.length" class="mb-4 flex flex-col gap-2">
                                <label v-for="address in shippingBook" :key="address.id" class="flex cursor-pointer items-start gap-3 rounded-md border p-3" :class="form.shippingAddressId === address.id ? 'border-primary bg-primary-50' : 'border-surface-200'">
                                    <RadioButton v-model="form.shippingAddressId" :value="address.id" name="shippingAddressId" />
                                    <span><strong>{{ address.firstName }} {{ address.lastName }}</strong><br><span class="text-sm text-surface-600">{{ addressLine(address) }}</span></span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-md border p-3" :class="form.shippingAddressId === null ? 'border-primary bg-primary-50' : 'border-surface-200'">
                                    <RadioButton v-model="form.shippingAddressId" :value="null" name="shippingAddressId" /> {{ $t('checkout.new_address') }}
                                </label>
                            </div>
                            <AddressFields v-if="!form.shippingAddressId" :address="form.shipping" :errors="errors" prefix="shipping" :countries="data.countries" autocomplete="section-shipping shipping" />
                        </div>
                        <small v-if="errors.billingAddressId || errors.shippingAddressId" class="text-red-600">{{ errors.billingAddressId || errors.shippingAddressId }}</small>
                    </template>

                    <template v-else-if="step === 2">
                        <h2 class="mb-3 text-lg font-bold">{{ $t('checkout.step.shipping') }}</h2>
                        <div class="flex flex-col gap-2" role="radiogroup" :aria-label="$t('checkout.step.shipping')">
                            <label v-for="option in data.shippingOptions" :key="option.code" class="flex items-center gap-3 rounded-md border p-3" :class="[form.shippingMethod === option.code ? 'border-primary bg-primary-50' : 'border-surface-200', option.gross === null ? 'opacity-50' : 'cursor-pointer']">
                                <RadioButton v-model="form.shippingMethod" :value="option.code" name="shippingMethod" :disabled="option.gross === null" :input-id="`shipping-${option.code}`" />
                                <span class="grow"><strong>{{ option.name }}</strong><br><span class="text-sm text-surface-600">{{ option.gross === null ? $t('checkout.shipping_unavailable') : option.description }}</span></span>
                                <strong v-if="option.gross !== null">{{ option.gross === 0 ? $t('cart.free') : money(option.gross) }}</strong>
                            </label>
                        </div>
                        <small v-if="errors.shippingMethod" id="shippingMethod" class="mt-2 block text-red-600">{{ errors.shippingMethod }}</small>
                    </template>

                    <template v-else>
                        <h2 class="mb-3 text-lg font-bold">{{ $t('checkout.step.review') }}</h2>
                        <ul class="mb-4 divide-y divide-surface-200">
                            <li v-for="line in cart.lines" :key="line.variantId" class="flex justify-between gap-2 py-2 text-sm">
                                <span>{{ $t('checkout.line', { quantity: line.quantity, name: line.productName }) }} <span class="text-surface-500">{{ line.variantName }}</span></span>
                                <strong>{{ money(line.lineGross) }}</strong>
                            </li>
                        </ul>
                        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="font-semibold">{{ $t('checkout.billing') }}</dt><dd>{{ form.billingAddressId ? addressLine(customer.addresses.find((a) => a.id === form.billingAddressId)) : addressLine(form.billing) }}</dd></div>
                            <div><dt class="font-semibold">{{ $t('checkout.delivery') }}</dt><dd>{{ form.shippingSameAsBilling ? $t('checkout.same_as_billing') : form.shippingAddressId ? addressLine(customer.addresses.find((a) => a.id === form.shippingAddressId)) : addressLine(form.shipping) }}</dd></div>
                            <div><dt class="font-semibold">{{ $t('checkout.step.shipping') }}</dt><dd>{{ selectedShipping?.name }}</dd></div>
                        </dl>
                        <label class="flex items-center gap-2">
                            <Checkbox v-model="form.acceptTerms" binary input-id="acceptTerms" :invalid="!!errors.acceptTerms" /> {{ $t('checkout.accept_terms') }}
                        </label>
                        <small v-if="errors.acceptTerms" class="mt-1 flex items-center gap-1 text-red-600"><AppIcon name="alert" /> {{ errors.acceptTerms }}</small>
                        <Message v-if="errors.cart || errors.coupon || errors.expectedTotal" severity="error" class="mt-3" :closable="false">
                            {{ errors.cart || errors.coupon || errors.expectedTotal }} <a href="/cart" class="underline">{{ $t('checkout.back_to_cart') }}</a>
                        </Message>
                    </template>

                    <div class="mt-6 flex justify-between gap-2">
                        <Button v-if="step > 0 && !(step === 1 && customer)" severity="secondary" text @click="step--"><AppIcon name="back" /> {{ $t('checkout.back') }}</Button>
                        <span v-else />
                        <Button v-if="step < 3" @click="next">{{ $t('checkout.continue') }} <AppIcon name="chevron-right" /></Button>
                        <Button v-else size="large" :loading="placing" data-testid="place-order" @click="placeOrder"><AppIcon name="payment" /> {{ $t('checkout.pay', { amount: money(totals.totalGross) }) }}</Button>
                    </div>
                </section>
            </div>

            <aside class="rounded-lg border border-surface-200 bg-white p-4 md:sticky md:top-4">
                <h2 class="mb-3 font-bold">{{ $t('checkout.summary') }}</h2>
                <ul class="mb-3 flex flex-col gap-1 text-sm">
                    <li v-for="line in cart.lines" :key="line.variantId" class="flex justify-between gap-2"><span>{{ $t('checkout.line', { quantity: line.quantity, name: `${line.productName} ${line.variantName}` }) }}</span><span>{{ money(line.lineGross) }}</span></li>
                </ul>
                <CartTotals :totals="totals" :currency="cart.currency" :coupon-code="cart.coupon?.code" :shipping-label="selectedShipping?.name" :shipping-pending="!selectedShipping" />
            </aside>
        </div>
    </div>
</template>
