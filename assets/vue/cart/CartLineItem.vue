<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import AppIcon from '../components/AppIcon.vue';
import { formatMoney } from '../../shared/format.js';

// One cart line (mini-cart drawer and cart page): image, name, pack, quantity, price, stock notice.
const props = defineProps({
    line: { type: Object, required: true },
    currency: { type: String, required: true },
    compact: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['quantity', 'remove']);
const money = (cents) => formatMoney(cents, props.currency);

// The +/- buttons repeat while held: send one update once the value has settled, not one per step.
const quantity = ref(props.line.quantity);
let timer = null;
watch(() => props.line.quantity, (value) => { quantity.value = value; });
function changeQuantity(value) {
    quantity.value = value;
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (value && value !== props.line.quantity) emit('quantity', value);
    }, 400);
}
onBeforeUnmount(() => clearTimeout(timer));
const STOCK_TEXT = { insufficient: 'cart.stock.insufficient', unavailable: 'cart.stock.unavailable', low: 'cart.stock.low' };
</script>

<template>
    <li class="flex gap-3 py-3" :class="{ 'opacity-60': line.stock === 'unavailable' }">
        <a :href="line.slug ? `/p/${line.slug}` : undefined" class="shrink-0 overflow-hidden rounded-md border border-surface-200 bg-surface-100" :class="compact ? 'h-16 w-16' : 'h-24 w-24'">
            <img v-if="line.imageUrl" :src="line.imageUrl" :alt="line.productName" class="h-full w-full object-cover">
        </a>
        <div class="flex min-w-0 grow flex-col gap-1">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <a :href="line.slug ? `/p/${line.slug}` : undefined" class="font-semibold text-surface-900 hover:text-primary-700">{{ line.productName }}</a>
                    <div class="text-sm text-surface-600">{{ line.variantName }} · {{ line.sku }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold">{{ money(line.lineGross) }}</div>
                    <div v-if="line.quantity > 1" class="text-xs text-surface-500">{{ $t('cart.each', { price: money(line.unitGross) }) }}</div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <InputNumber
                    :model-value="quantity" show-buttons button-layout="horizontal" :min="1" :max="line.maxQuantity" :disabled="busy || line.stock === 'unavailable'"
                    :input-class="compact ? 'w-10 text-center text-sm' : 'w-12 text-center'" :aria-label="$t('cart.quantity_for', { name: line.productName })"
                    @update:model-value="changeQuantity"
                >
                    <template #incrementbuttonicon><AppIcon name="add" /></template>
                    <template #decrementbuttonicon><AppIcon name="remove" /></template>
                </InputNumber>
                <Button text severity="danger" size="small" :disabled="busy" :aria-label="$t('cart.remove', { name: line.productName })" @click="emit('remove')"><AppIcon name="delete" /></Button>
            </div>
            <small v-if="STOCK_TEXT[line.stock]" class="flex items-center gap-1" :class="line.stock === 'low' ? 'text-amber-700' : 'text-red-700'">
                <AppIcon :name="line.stock === 'low' ? 'clock' : 'alert'" /> {{ $t(STOCK_TEXT[line.stock], { count: line.maxQuantity }) }}
            </small>
        </div>
    </li>
</template>
