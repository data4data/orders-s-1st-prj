import { reactive } from 'vue';
import { api } from './api.js';

/**
 * The visitor's cart, shared by every storefront island (header badge, mini-cart drawer, product
 * page, cart page, checkout). All islands run from one bundle, so they share this one object.
 * Every write answers with the whole cart (see CartController), which replaces `state.cart`.
 */
export const cartState = reactive({
    /** @type {null|{id: string|null, currency: string, itemCount: number, lines: any[], coupon: null|{code: string, error: string|null}, totals: Record<string, number|null>, shippingEstimate: null|{code: string, name: string, gross: number}, canCheckout: boolean}} */
    cart: null,
    /** Item count printed by the server before the cart is loaded. */
    initialCount: 0,
    drawerOpen: false,
    loading: false,
});

/** @param {any} cart */
function replace(cart) {
    cartState.cart = cart;
    return cart;
}

export async function loadCart() {
    cartState.loading = true;
    try {
        return replace(await api.get('/api/cart'));
    } finally {
        cartState.loading = false;
    }
}

/**
 * @param {string} variantId
 * @param {number} quantity
 */
export async function addToCart(variantId, quantity) {
    return replace(await api.post('/api/cart/lines', { variantId, quantity }));
}

/**
 * @param {string} variantId
 * @param {number} quantity
 */
export async function updateLine(variantId, quantity) {
    return replace(await api.patch(`/api/cart/lines/${variantId}`, { quantity }));
}

/** @param {string} variantId */
export async function removeLine(variantId) {
    return replace(await api.delete(`/api/cart/lines/${variantId}`));
}

/** @param {string} code */
export async function applyCoupon(code) {
    return replace(await api.post('/api/cart/coupon', { code }));
}

export async function removeCoupon() {
    return replace(await api.delete('/api/cart/coupon'));
}

export function itemCount() {
    return cartState.cart ? cartState.cart.itemCount : cartState.initialCount;
}
