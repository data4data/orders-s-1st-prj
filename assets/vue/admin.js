// Entry for the admin single-page app on admin.shop.test.
// Must never import Bootstrap or jQuery (enforced by ESLint); shared logic lives in assets/shared.
import './tailwind.css';
import { createApp } from 'vue';
import { installPrimeVue } from './shared/primevue.js';
import { i18n } from './shared/i18n.js';
import { installGlobalErrorHandler } from './shared/global-errors.js';
import AdminApp from './admin/AdminApp.vue';
import { createAdminRouter } from './admin/router.js';

const root = document.getElementById('admin-app');
if (root) {
    const props = JSON.parse(root.dataset.props ?? '{}');
    const app = createApp(AdminApp, props);
    installPrimeVue(app);
    app.use(i18n);
    app.use(createAdminRouter(props));
    installGlobalErrorHandler(app);
    app.mount(root);
}
