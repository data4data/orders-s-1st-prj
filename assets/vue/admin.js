// Entry for the admin single-page app on admin.shop.test.
// Must never import Bootstrap or jQuery (enforced by ESLint).
import './tailwind.css';
import { createApp } from 'vue';
import { installPrimeVue } from './shared/primevue.js';
import { i18n } from './shared/i18n.js';
import AdminApp from './admin/AdminApp.vue';

const root = document.getElementById('admin-app');
if (root) {
    const app = createApp(AdminApp);
    installPrimeVue(app);
    app.use(i18n);
    app.mount(root);
}
