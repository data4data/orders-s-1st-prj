// Entry for storefront Vue "islands" (catalog, product, cart, checkout, account).
// Must never import Bootstrap or jQuery (enforced by ESLint).
import './tailwind.css';
import { createApp } from 'vue';
import { installPrimeVue } from './shared/primevue.js';
import { i18n } from './shared/i18n.js';

/** Page components are registered here as they are built, e.g. { Catalog: CatalogPage }. */
const pages = {};

document.querySelectorAll('[data-vue-page]').forEach((element) => {
    const page = pages[element.dataset.vuePage];
    if (!page) {
        return;
    }
    const props = element.dataset.props ? JSON.parse(element.dataset.props) : {};
    const app = createApp(page, props);
    installPrimeVue(app);
    app.use(i18n);
    app.mount(element);
});
