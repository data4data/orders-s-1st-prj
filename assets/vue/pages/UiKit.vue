<script setup>
import { computed, reactive, ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Skeleton from 'primevue/skeleton';
import AppIcon from '../components/AppIcon.vue';
import FormField from '../components/FormField.vue';
import ErrorSummary from '../components/ErrorSummary.vue';
import EmptyState from '../components/EmptyState.vue';
import { notify } from '../shared/notify.js';
import { confirmAction } from '../shared/confirm.js';
import { api, handleApiError, ApiError } from '../shared/api.js';
import { useUnsavedChanges } from '../shared/unsaved.js';
import { t } from '../shared/i18n.js';

// UI kit (dev only): the same demos as the Bootstrap page, built with the Vue helpers.
const props = defineProps({
    statuses: { type: Array, default: () => [400, 401, 403, 404, 405, 409, 413, 419, 429, 500, 502, 503, 504] },
    inRouter: { type: Boolean, default: false },
});

// 1. Toasts
const toast = (type) => notify({ type, title: t(`ui_kit.toasts.${type}_title`), text: t(`ui_kit.toasts.${type}`), requestId: type === 'error' ? '7F3A-91C2' : null });

// 2. Form with client checks mirroring the server rules, server errors in the same place
const blank = { name: '', email: '', postcode: '', message: '' };
const form = reactive({ ...blank });
const errors = reactive({ name: '', email: '', postcode: '', message: '' });
const touched = reactive({});
const labels = computed(() => ({ name: t('ui_kit.form.name'), email: t('ui_kit.form.email'), postcode: t('ui_kit.form.postcode'), message: t('ui_kit.form.message') }));
const rules = {
    name: (v) => (v.trim() ? '' : 'Enter your name.'),
    email: (v) => (!v.trim() ? 'Enter your email address.' : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : t('form.email')),
    postcode: (v) => (!v.trim() ? 'Enter a postcode.' : /^\d{4}\s?[A-Za-z]{2}$/.test(v) ? '' : 'Use the format 1234 AB.'),
    message: (v) => (v.trim().length >= 10 ? '' : t('form.minlength', { min: 10 })),
};
const check = (field) => { errors[field] = rules[field](form[field]); return !errors[field]; };
// Moving straight to a form button skips the blur check: the submit checks everything, and a message
// appearing now would shift the button away from under the pointer (the click would miss).
const onBlur = (field, event) => {
    if (event?.relatedTarget?.closest?.('button') && event.currentTarget?.form?.contains(event.relatedTarget)) return;
    if (form[field] !== '' || touched[field]) { touched[field] = true; check(field); }
};
const onInput = (field) => { touched[field] = true; if (errors[field] || touched[`${field}Checked`]) check(field); };
const sending = ref(false);
const submit = async (skipChecks = false) => {
    if (!skipChecks) {
        const invalid = Object.keys(rules).filter((field) => !check(field));
        Object.keys(rules).forEach((field) => { touched[`${field}Checked`] = true; });
        if (invalid.length) {
            document.getElementById(`uikit-${invalid[0]}`)?.focus();
            return;
        }
    }
    sending.value = true;
    try {
        const { message } = await api.post('/api/ui-kit/contact', { ...form });
        notify({ type: 'success', text: message });
        Object.assign(form, blank);
        Object.keys(errors).forEach((field) => { errors[field] = ''; });
    } catch (error) {
        await handleApiError(error, { errors });
        const first = Object.keys(errors).find((field) => errors[field]);
        if (first) document.getElementById(`uikit-${first}`)?.focus();
    } finally {
        sending.value = false;
    }
};

// 3. Confirmation + unsaved changes
const isDirty = () => Object.keys(blank).some((field) => form[field] !== blank[field]);
useUnsavedChanges(isDirty, { router: props.inRouter });
const deleteAddress = async () => {
    const deleted = await confirmAction({ title: t('ui_kit.confirm.title'), body: t('ui_kit.confirm.body'), confirmLabel: t('ui_kit.confirm.accept'), cancelLabel: t('ui_kit.confirm.cancel') });
    notify(deleted ? { type: 'success', text: t('ui_kit.confirm.deleted') } : { type: 'info', text: t('ui_kit.confirm.kept') });
};

// 4. Loading + empty
const items = ref(null);
const loading = ref(false);
const showSkeleton = ref(false);
const load = async (empty) => {
    loading.value = true;
    const skeleton = setTimeout(() => { showSkeleton.value = true; }, 300); // no flicker for fast answers
    try {
        items.value = await api.get(`/api/ui-kit/items${empty ? '?empty=1' : ''}`);
    } catch (error) {
        handleApiError(error);
    } finally {
        clearTimeout(skeleton);
        showSkeleton.value = false;
        loading.value = false;
    }
};

// 5. Error handling
const busy = reactive({});
const waitUntil = reactive({});
const run = async (key, action, onSuccess) => {
    busy[key] = true;
    try {
        const result = await action();
        onSuccess?.(result);
    } catch (error) {
        if (error instanceof ApiError && error.kind === 'rate_limited' && error.retryAfter) {
            waitUntil[key] = error.retryAfter;
            const timer = setInterval(() => { waitUntil[key] -= 1; if (waitUntil[key] <= 0) clearInterval(timer); }, 1000);
        }
        handleApiError(error);
    } finally {
        busy[key] = false;
    }
};
const jsError = () => setTimeout(() => { throw new Error('Simulated JavaScript error (UI kit).'); });
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">{{ $t('ui_kit.title') }} <span class="rounded bg-surface-200 px-2 py-0.5 align-middle text-sm">Vue + PrimeVue + Tailwind</span></h1>
            <a v-if="!inRouter" href="/ui-kit" class="text-primary-700 underline"><AppIcon name="chevron-right" /> Same page in Bootstrap + jQuery</a>
        </div>
        <p class="text-surface-600">{{ $t('ui_kit.intro') }}</p>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-1 text-lg font-semibold">1. Toasts</h2>
            <p class="mb-3 text-sm text-surface-600">Success and info close after 4 s. Warnings and errors stay until closed. At most 3 are visible.</p>
            <div class="flex flex-wrap gap-2">
                <Button label="Success" severity="success" outlined @click="toast('success')" />
                <Button label="Info" severity="info" outlined @click="toast('info')" />
                <Button label="Warning" severity="warn" outlined @click="toast('warning')" />
                <Button label="Error (with reference)" severity="danger" outlined @click="toast('error')" />
            </div>
        </section>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-1 text-lg font-semibold">2. Form fields and validation</h2>
            <p class="mb-3 text-sm text-surface-600">Checked when you leave a field and on submit; after the first error it re-checks while you type. The second button skips the browser checks to show the server's errors in the same place.</p>
            <form novalidate @submit.prevent="submit(false)">
                <ErrorSummary :errors="errors" :labels="labels" id-prefix="uikit-" />
                <div class="grid gap-4 md:grid-cols-2">
                    <FormField id="uikit-name" v-slot="{ invalid, describedBy }" :label="labels.name" required :error="errors.name">
                        <InputText id="uikit-name" v-model="form.name" :invalid="invalid" :aria-describedby="describedBy" autocomplete="name" @blur="onBlur('name', $event)" @input="onInput('name')" />
                    </FormField>
                    <FormField id="uikit-email" v-slot="{ invalid, describedBy }" :label="labels.email" required :error="errors.email">
                        <InputText id="uikit-email" v-model="form.email" type="email" :invalid="invalid" :aria-describedby="describedBy" autocomplete="email" @blur="onBlur('email', $event)" @input="onInput('email')" />
                    </FormField>
                    <FormField id="uikit-postcode" v-slot="{ invalid, describedBy }" :label="labels.postcode" required :error="errors.postcode" :help="$t('ui_kit.form.postcode_help')">
                        <InputText id="uikit-postcode" v-model="form.postcode" :invalid="invalid" :aria-describedby="describedBy" @blur="onBlur('postcode', $event)" @input="onInput('postcode')" />
                    </FormField>
                    <FormField id="uikit-message" v-slot="{ invalid, describedBy }" class="md:col-span-2" :label="labels.message" required :error="errors.message">
                        <Textarea id="uikit-message" v-model="form.message" rows="3" :invalid="invalid" :aria-describedby="describedBy" @blur="onBlur('message', $event)" @input="onInput('message')" />
                    </FormField>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button type="submit" :label="$t('ui_kit.form.send')" :loading="sending" />
                    <Button type="button" :label="$t('ui_kit.form.send_without_checks')" severity="secondary" outlined :disabled="sending" @click="submit(true)" />
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-1 text-lg font-semibold">3. Confirmation dialog and unsaved changes</h2>
            <p class="mb-3 text-sm text-surface-600">Every destructive action asks first. Type something in the form above, then click the link: the unsaved-changes dialog appears.</p>
            <div class="flex flex-wrap items-center gap-3">
                <Button severity="danger" outlined @click="deleteAddress"><AppIcon name="delete" /> Delete address "Workshop"</Button>
                <router-link v-if="inRouter" to="/" class="text-primary-700 underline" data-router-link>Go to the dashboard</router-link>
                <a v-else href="/ui-kit" class="text-primary-700 underline">Go to the Bootstrap UI kit</a>
            </div>
        </section>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-3 text-lg font-semibold">4. Loading and empty states</h2>
            <div class="mb-3 flex flex-wrap gap-2">
                <Button label="Load products" outlined :loading="loading" @click="load(false)" />
                <Button label="Load an empty list" outlined :disabled="loading" @click="load(true)" />
            </div>
            <div class="rounded-md border border-surface-200 bg-surface-50 p-3 text-sm">
                <div v-if="showSkeleton" class="flex flex-col gap-2"><Skeleton width="60%" /><Skeleton /><Skeleton width="40%" /></div>
                <EmptyState v-else-if="items && items.length === 0" :title="$t('empty.no_items_title')" :text="$t('empty.no_items_text')">
                    <Button size="small" :label="$t('empty.clear')" @click="load(false)" />
                </EmptyState>
                <ul v-else-if="items">
                    <li v-for="item in items" :key="item.name" class="flex justify-between border-b border-surface-200 py-1"><span>{{ item.name }}</span><span class="text-surface-500">{{ item.detail }}</span></li>
                </ul>
                <span v-else class="text-surface-500">Click a button to load.</span>
            </div>
        </section>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-1 text-lg font-semibold">5. Error handling</h2>
            <p class="mb-3 text-sm text-surface-600">Each button calls a demo endpoint that answers with that status; the reaction follows the matrix in pages.html. To try offline, switch the browser to offline in the developer tools.</p>
            <div class="flex flex-wrap gap-2">
                <Button v-for="code in statuses" :key="code" :label="String(code)" size="small" severity="secondary" outlined :loading="busy[code]" @click="run(code, () => api.post(`/api/ui-kit/status/${code}`))" />
                <Button label="422 (validation)" size="small" severity="secondary" outlined @click="submit(true)" />
                <Button :label="waitUntil.limited > 0 ? $t('form.wait', { seconds: waitUntil.limited }) : 'Real rate limit (3/min)'" size="small" severity="warn" outlined :disabled="waitUntil.limited > 0" :loading="busy.limited" @click="run('limited', () => api.post('/api/ui-kit/limited'), ({ message }) => notify({ type: 'success', text: message }))" />
                <Button label="Timeout (15 s)" size="small" severity="warn" outlined :loading="busy.timeout" @click="run('timeout', () => api.get('/api/ui-kit/slow'))" />
                <Button label="JavaScript error" size="small" severity="danger" outlined @click="jsError" />
            </div>
        </section>

        <section class="rounded-lg border border-surface-200 bg-white p-5">
            <h2 class="mb-3 text-lg font-semibold">6. Icons (Lucide)</h2>
            <div class="flex flex-wrap gap-4 text-sm">
                <span v-for="name in ['success', 'info', 'warning', 'error', 'delete', 'edit', 'add', 'search', 'cart', 'account', 'document', 'shipping', 'loading', 'store']" :key="name" class="inline-flex items-center gap-1">
                    <AppIcon :name="name" class="text-lg" /> <span class="text-surface-500">{{ name }}</span>
                </span>
            </div>
        </section>
    </div>
</template>
