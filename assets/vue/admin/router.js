import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import ProductListPage from './pages/catalog/ProductListPage.vue';
import ProductEditPage from './pages/catalog/ProductEditPage.vue';
import CategoryPage from './pages/catalog/CategoryPage.vue';
import AttributePage from './pages/catalog/AttributePage.vue';
import OrderListPage from './pages/orders/OrderListPage.vue';
import OrderDetailPage from './pages/orders/OrderDetailPage.vue';
import CustomerListPage from './pages/customers/CustomerListPage.vue';
import CustomerDetailPage from './pages/customers/CustomerDetailPage.vue';
import CouponPage from './pages/CouponPage.vue';
import SettingsPage from './pages/settings/SettingsPage.vue';
import PlatformPage from './pages/platform/PlatformPage.vue';

/** Admin navigation (docs/diagrams/pages.html → Admin map). */

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
        { path: '/customers', component: CustomerListPage, meta: { title: 'admin.nav.customers' } },
        { path: '/customers/:id', component: CustomerDetailPage, props: true, meta: { title: 'admin.nav.customers' } },
        { path: '/coupons', component: CouponPage, meta: { title: 'admin.nav.coupons' } },
        { path: '/settings/:section?', component: SettingsPage, props: (route) => ({ section: route.params.section || 'profile' }), meta: { title: 'admin.nav.settings' } },
        { path: '/platform/:section?', component: PlatformPage, props: (route) => ({ section: route.params.section || 'stores' }), meta: { title: 'admin.nav.platform', superAdmin: true } },
        ...(devTools ? [{ path: '/ui-kit', component: () => import('../pages/UiKit.vue'), props: { inRouter: true }, meta: { title: 'admin.nav.ui_kit' } }] : []),
        { path: '/:pathMatch(.*)*', component: NotFoundPage, meta: { title: 'admin.not_found.title' } },
    ];
    return createRouter({ history: createWebHistory('/'), routes });
}
