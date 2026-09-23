<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import Skeleton from 'primevue/skeleton';
import Timeline from 'primevue/timeline';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import StoreRequired from '../../StoreRequired.vue';
import OrderStateTag from '../../orders/OrderStateTag.vue';
import OrderDetails from '../../../customer/OrderDetails.vue';
import { api, handleApiError, ApiError } from '../../../shared/api.js';
import { notify } from '../../../shared/notify.js';
import { t } from '../../../shared/i18n.js';
import { formatMoney } from '../../../../shared/format.js';

// Admin order detail: lines, addresses, payments, timeline, and a button for every transition the
// workflow allows now. Cancel and refund are confirmed first and take an optional note.
const props = defineProps({ id: { type: String, required: true } });
const tenant = inject('tenant');
const detail = ref(null);
const missing = ref(false);
const busy = ref(false);
const pending = ref(null);
const comment = ref('');

async function load() {
    if (tenant.mode !== 'store') return;
    try {
        detail.value = await api.get(`/api/admin/orders/${props.id}`);
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            error.handled = true;
            missing.value = true;
            return;
        }
        handleApiError(error);
    }
}

function ask(transition) {
    comment.value = '';
    pending.value = transition;
}

async function apply() {
    const transition = pending.value;
    busy.value = true;
    try {
        detail.value = await api.post(`/api/admin/orders/${props.id}/transitions`, { transition: transition.name, comment: comment.value, version: detail.value.version });
        pending.value = null;
        notify({ type: 'success', text: t('admin.orders.done', { action: t(`admin.orders.transition.${transition.name}`) }) });
    } catch (error) {
        pending.value = null;
        await handleApiError(error);
        load();
    } finally {
        busy.value = false;
    }
}

watch(() => tenant.store?.publicId, load);
onMounted(load);
</script>

<template>
    <StoreRequired>
        <EmptyState v-if="missing" icon="orders" :title="$t('order.not_found')">
            <router-link to="/orders" class="text-primary-700 underline" data-router-link>{{ $t('admin.orders.back') }}</router-link>
        </EmptyState>
        <Skeleton v-else-if="!detail" height="24rem" />
        <div v-else class="flex flex-col gap-4">
            <router-link to="/orders" class="flex items-center gap-1 text-sm text-surface-600 hover:underline" data-router-link><AppIcon name="back" /> {{ $t('admin.orders.back') }}</router-link>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-semibold">{{ detail.number }}</h1>
                    <OrderStateTag :badge="detail.badge" :label="detail.stateLabel" />
                </div>
                <div class="flex flex-wrap gap-2" data-testid="order-actions">
                    <Button
                        v-for="transition in detail.transitions" :key="transition.name" :severity="transition.destructive ? 'danger' : undefined" :outlined="transition.destructive"
                        :loading="busy && pending?.name === transition.name" :label="$t(`admin.orders.transition.${transition.name}`)" @click="ask(transition)"
                    />
                    <span v-if="!detail.transitions.length" class="text-sm text-surface-500">{{ $t('admin.orders.no_actions') }}</span>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-lg border border-surface-200 bg-white p-4 lg:col-span-2"><OrderDetails :order="detail.order" /></section>
                <div class="flex flex-col gap-4">
                    <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm">
                        <h2 class="mb-2 font-semibold">{{ $t('admin.orders.customer') }}</h2>
                        <div>{{ detail.customer }}<span v-if="detail.company">{{ `, ${detail.company}` }}</span></div>
                        <a :href="`mailto:${detail.email}`" class="text-primary-700 underline">{{ detail.email }}</a>
                        <div class="text-surface-500">{{ detail.guest ? $t('admin.orders.guest') : $t('admin.orders.registered') }}</div>
                    </section>
                    <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm">
                        <h2 class="mb-2 font-semibold">{{ $t('admin.orders.payments') }}</h2>
                        <ul class="flex flex-col gap-2">
                            <li v-for="payment in detail.payments" :key="payment.id" class="flex flex-wrap items-center justify-between gap-2">
                                <span><AppIcon name="payment" /> {{ payment.gateway }} · {{ $t(`admin.orders.payment_state.${payment.state}`) }}</span>
                                <span>{{ formatMoney(payment.amount, detail.currency) }}<span v-if="payment.refunded" class="text-red-700">{{ ` (−${formatMoney(payment.refunded, detail.currency)})` }}</span></span>
                            </li>
                        </ul>
                    </section>
                    <section class="rounded-lg border border-surface-200 bg-white p-4 text-sm">
                        <h2 class="mb-2 font-semibold">{{ $t('admin.orders.timeline') }}</h2>
                        <Timeline :value="[...detail.history].reverse()" :pt="{ eventOpposite: { class: '!hidden' } }">
                            <template #content="{ item }">
                                <div class="pb-3">
                                    <div class="font-semibold">{{ $t(item.toLabel) }}</div>
                                    <div class="text-xs text-surface-500">{{ new Date(item.at).toLocaleString() }} · {{ item.actorName ?? $t(`admin.orders.actor.${item.actorType}`) }}</div>
                                    <div v-if="item.comment" class="mt-1 rounded bg-surface-50 px-2 py-1">{{ item.comment }}</div>
                                </div>
                            </template>
                        </Timeline>
                    </section>
                </div>
            </div>
        </div>

        <Dialog :visible="!!pending" modal :header="pending ? $t(`admin.orders.confirm.${pending.name}.title`, { number: detail?.number }) : ''" class="w-full max-w-md" @update:visible="pending = null">
            <template #closeicon><AppIcon name="close" /></template>
            <p v-if="pending" class="mb-3 text-sm">{{ $t(`admin.orders.confirm.${pending.name}.body`, { amount: formatMoney(detail.totalGross, detail.currency) }) }}</p>
            <label for="transition-comment" class="text-sm font-semibold">{{ $t('admin.orders.comment') }}</label>
            <Textarea id="transition-comment" v-model="comment" rows="3" class="mt-1 w-full" maxlength="500" />
            <div class="mt-4 flex justify-end gap-2">
                <Button severity="secondary" outlined :label="$t('admin.catalog.cancel')" @click="pending = null" />
                <Button :severity="pending?.destructive ? 'danger' : undefined" :loading="busy" :label="pending ? $t(`admin.orders.transition.${pending.name}`) : ''" data-testid="confirm-transition" @click="apply" />
            </div>
        </Dialog>
    </StoreRequired>
</template>
