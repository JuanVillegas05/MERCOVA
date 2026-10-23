import { renderNav, bindLogout } from './lib/auth.js';
import { refreshCartBadge } from './lib/cart.js';
import { initNotificationBell } from './lib/notifications.js';
import { initHome } from './pages/home.js';
import { initLogin } from './pages/login.js';
import { initRegister } from './pages/register.js';
import { initMarketplace } from './pages/marketplace.js';
import { initEmprendimientos } from './pages/emprendimientos.js';
import { initDashboard } from './pages/dashboard.js';
import { initForgotPassword } from './pages/forgot-password.js';
import { initResetPassword } from './pages/reset-password.js';
import { initAdminDashboard } from './pages/admin-dashboard.js';
import { initAdminOrders } from './pages/admin-orders.js';
import { initAdminProducts } from './pages/admin-products.js';
import { initAdminUsers } from './pages/admin-users.js';
import { initAdminCategories } from './pages/admin-categories.js';
import { initAdminPlans } from './pages/admin-plans.js';
import { initAdminBusinesses } from './pages/admin-businesses.js';
import { initCart } from './pages/cart.js';
import { initFavorites } from './pages/favorites.js';
import { initProduct } from './pages/product.js';
import { initSocialCallback } from './pages/social-callback.js';

const pageInitializers = {
    home: initHome,
    login: initLogin,
    register: initRegister,
    marketplace: initMarketplace,
    emprendimientos: initEmprendimientos,
    dashboard: initDashboard,
    'forgot-password': initForgotPassword,
    'reset-password': initResetPassword,
    'admin-dashboard': initAdminDashboard,
    'admin-orders': initAdminOrders,
    'admin-products': initAdminProducts,
    'admin-users': initAdminUsers,
    'admin-categories': initAdminCategories,
    'admin-plans': initAdminPlans,
    'admin-businesses': initAdminBusinesses,
    cart: initCart,
    favorites: initFavorites,
    product: initProduct,
    'social-callback': initSocialCallback,
};

document.addEventListener('DOMContentLoaded', async () => {
    const user = await renderNav();
    bindLogout();
    await refreshCartBadge();

    const page = document.body.dataset.page;

    // Las páginas de /admin ya inicializan su propia campana apuntando a
    // /admin/notifications; inicializarla también aquí duplicaría los
    // listeners (el dropdown se abriría y cerraría en el mismo clic).
    if (user && !page?.startsWith('admin-')) {
        initNotificationBell('/notifications');
    }

    const init = pageInitializers[page];
    if (init) {
        init();
    }
});
