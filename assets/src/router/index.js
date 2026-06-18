import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
    { path: '/', name: 'vegetable-index', component: () => import('../views/VegetableList.vue') },
    { path: '/vegetable/new', name: 'vegetable-new', component: () => import('../views/VegetableForm.vue') },
    { path: '/vegetable/:id', name: 'vegetable-show', component: () => import('../views/VegetableShow.vue'), props: true },
    { path: '/vegetable/:id/edit', name: 'vegetable-edit', component: () => import('../views/VegetableForm.vue'), props: true },
];

const router = createRouter({
    // Le SPA est monté sous /app pendant la transition (les pages Twig restent à
    // la racine, ex. /vegetable). À la fin de la migration on pourra repasser à '/'.
    history: createWebHistory('/app/'),
    routes,
});

// Garde de navigation : exige l'authentification sauf routes publiques.
router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.ready) {
        await auth.fetchMe();
    }
    if (!to.meta.public && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.name === 'login' && auth.isAuthenticated) {
        return { name: 'vegetable-index' };
    }
    return true;
});

export default router;
