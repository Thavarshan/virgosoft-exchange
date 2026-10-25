import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from '@/App.vue';
import { initializeTheme } from '@/composables/useTheme';
import { api } from '@/services/api';
import Login from '@/pages/Login.vue';
import LimitOrder from '@/pages/LimitOrder.vue';
import WalletOrders from '@/pages/WalletOrders.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: Login, meta: { guest: true } },
        { path: '/', redirect: '/trade' },
        { path: '/trade', component: LimitOrder, meta: { requiresAuth: true } },
        {
            path: '/wallet',
            component: WalletOrders,
            meta: { requiresAuth: true },
        },
    ],
});

router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth) {
        return true;
    }

    try {
        await api.session();
        return true;
    } catch {
        return { path: '/login' };
    }
});

initializeTheme();
createApp(App).use(router).mount('#app');
