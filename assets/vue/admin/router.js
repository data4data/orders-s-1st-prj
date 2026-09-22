import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import ComingSoonPage from './pages/ComingSoonPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';

/** Admin navigation (docs/diagrams/pages.html → Admin map). Screens arrive in the phases noted. */
const soon = (path, page, phase, extra = {}) => ({ path, component: ComingSoonPage, props: { page, phase }, meta: { title: page, ...extra } });

export function createAdminRouter({ devTools }) {
    const routes = [
        { path: '/', component: DashboardPage, meta: { title: 'admin.nav.dashboard' } },
        soon('/orders', 'admin.nav.orders', 6),
        soon('/catalog/products', 'admin.nav.products', 4),
        soon('/catalog/categories', 'admin.nav.categories', 4),
        soon('/catalog/attributes', 'admin.nav.attributes', 4),
        soon('/customers', 'admin.nav.customers', 5),
        soon('/coupons', 'admin.nav.coupons', 5),
        soon('/settings', 'admin.nav.settings', 7),
        soon('/platform', 'admin.nav.platform', 7, { superAdmin: true }),
        ...(devTools ? [{ path: '/ui-kit', component: () => import('../pages/UiKit.vue'), props: { inRouter: true }, meta: { title: 'admin.nav.ui_kit' } }] : []),
        { path: '/:pathMatch(.*)*', component: NotFoundPage, meta: { title: 'admin.not_found.title' } },
    ];
    return createRouter({ history: createWebHistory('/'), routes });
}
