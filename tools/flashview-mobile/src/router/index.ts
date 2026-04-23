import { createRouter, createWebHashHistory } from 'vue-router';
import { useAuth } from '@/composables/useAuth';

const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/views/LoginView.vue'),
            meta: { requiresAuth: false },
        },
        {
            path: '/',
            redirect: '/create',
            meta: { requiresAuth: true },
        },
        {
            path: '/create',
            name: 'create',
            component: () => import('@/views/CreateSecretView.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/created',
            name: 'secret-created',
            component: () => import('@/views/SecretCreatedView.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/secrets',
            name: 'secrets',
            component: () => import('@/views/SecretsListView.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/secrets/:id/retrieve',
            name: 'retrieve',
            component: () => import('@/views/RetrieveSecretView.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/settings',
            name: 'settings',
            component: () => import('@/views/SettingsView.vue'),
            meta: { requiresAuth: true },
        },
    ],
});

router.beforeEach(async (to) => {
    const { isAuthenticated } = useAuth();

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return { name: 'login' };
    }

    if (to.name === 'login' && isAuthenticated.value) {
        return { name: 'create' };
    }
});

export default router;
