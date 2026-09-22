// Entry for storefront Vue "islands" (catalog, product, cart, checkout, account).
// Must never import Bootstrap or jQuery (enforced by ESLint); shared logic lives in assets/shared.
import './tailwind.css';
import { createApp } from 'vue';
import { installPrimeVue } from './shared/primevue.js';
import { i18n } from './shared/i18n.js';
import { installGlobalErrorHandler } from './shared/global-errors.js';
import SystemLayer from './components/SystemLayer.vue';
import StorefrontHeader from './storefront/StorefrontHeader.vue';
import StorefrontFooter from './storefront/StorefrontFooter.vue';

/** Page components by name, loaded on demand: <div data-vue-page="UiKit" data-props='{…}'>. */
const pages = {
    Catalog: () => import('./pages/CatalogPage.vue'),
    Product: () => import('./pages/ProductPage.vue'),
    Cart: () => import('./pages/CartPage.vue'),
    Checkout: () => import('./pages/CheckoutPage.vue'),
    OrderConfirmation: () => import('./pages/OrderConfirmationPage.vue'),
    Account: () => import('./pages/AccountPage.vue'),
    UiKit: () => import('./pages/UiKit.vue'),
};
const layouts = { StorefrontHeader, StorefrontFooter };

function mount(element, component) {
    const props = element.dataset.props ? JSON.parse(element.dataset.props) : {};
    const app = createApp(component, props);
    installPrimeVue(app);
    app.use(i18n);
    installGlobalErrorHandler(app);
    app.mount(element);
}

document.querySelectorAll('[data-vue-system]').forEach((element) => mount(element, SystemLayer));
document.querySelectorAll('[data-vue-layout]').forEach((element) => {
    const component = layouts[element.dataset.vueLayout];
    if (component) mount(element, component);
});
document.querySelectorAll('[data-vue-page]').forEach(async (element) => {
    const loader = pages[element.dataset.vuePage];
    if (loader) mount(element, (await loader()).default);
});
