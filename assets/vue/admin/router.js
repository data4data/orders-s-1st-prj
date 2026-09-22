import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import ComingSoonPage from './pages/ComingSoonPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import ProductListPage from './pages/catalog/ProductListPage.vue';
import ProductEditPage from './pages/catalog/ProductEditPage.vue';
import CategoryPage from './pages/catalog/CategoryPage.vue';
import AttributePage from './pages/catalog/AttributePage.vue';
import OrderListPage from './pages/orders/OrderListPage.vue';
import OrderDetailPage from './pages/orders/OrderDetailPage.vue';

/** Admin navigation (docs/diagrams/pages.html → Admin map). Screens arrive in the phases noted. */
const soon = (path, page, phase, extra = {}) => ({ path, component: ComingSoonPage, props: { page, phase }, meta: { title: page, ...extra } });

export function createAdminRouter({ devTools }) {
    const routes = [
        { path: '/', component: DashboardPage, meta: { title: 'admin.nav.dashboard' } },
        { path: '/orders', component: OrderListPage, meta: { title: 'admin.nav.orders' } },
        { path: '/orders/:id', component: OrderDetailPage, props: true, meta: { title: 'admin.nav.orders' } },
        { path: '/catalog/products', component: ProductListPage, meta: { title: 'admin.nav.products' } },
        { path: '/catalog/products/new', component: ProductEditPage, meta: { title: 'admin.catalog.product.new_title' } },
        { path: '/catalog/products/:publicId', component: ProductEditPage, props: true, meta: { title: 'admin.nav.products' } },
        { path: '/catalog/categories', component: CategoryPage, meta: { title: 'admin.nav.categories' } },
        { path: '/catalog/attributes', component: AttributePage, meta: { title: 'admin.nav.attributes' } },
        soon('/customers', 'admin.nav.customers', 5),
        soon('/coupons', 'admin.nav.coupons', 5),
        soon('/settings', 'admin.nav.settings', 7),
        soon('/platform', 'admin.nav.platform', 7, { superAdmin: true }),
        ...(devTools ? [{ path: '/ui-kit', component: () => import('../pages/UiKit.vue'), props: { inRouter: true }, meta: { title: 'admin.nav.ui_kit' } }] : []),
        { path: '/:pathMatch(.*)*', component: NotFoundPage, meta: { title: 'admin.not_found.title' } },
    ];
    return createRouter({ history: createWebHistory('/'), routes });
}
