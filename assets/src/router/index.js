import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
    { path: '/', name: 'vegetable-index', component: () => import('../views/VegetableList.vue') },
    { path: '/vegetable/new', name: 'vegetable-new', component: () => import('../views/VegetableForm.vue') },
    { path: '/vegetable/:id', name: 'vegetable-show', component: () => import('../views/VegetableShow.vue'), props: true },
    { path: '/vegetable/:id/edit', name: 'vegetable-edit', component: () => import('../views/VegetableForm.vue'), props: true },
    { path: '/calendrier', name: 'calendar', component: () => import('../views/CalendarView.vue') },
    { path: '/impression', name: 'print', component: () => import('../views/PrintView.vue') },
    { path: '/photos/import', name: 'photo-import', component: () => import('../views/PhotoImport.vue') },
    { path: '/interventions', name: 'action-index', component: () => import('../views/ActionList.vue') },
    { path: '/interventions/new', name: 'action-new', component: () => import('../views/ActionForm.vue') },
    { path: '/interventions/:id/edit', name: 'action-edit', component: () => import('../views/ActionForm.vue'), props: true },
    { path: '/parametrage/:resource', name: 'parametrage-index', component: () => import('../views/ParametrageList.vue') },
    { path: '/parametrage/:resource/new', name: 'parametrage-new', component: () => import('../views/ParametrageForm.vue') },
    { path: '/parametrage/:resource/:id/edit', name: 'parametrage-edit', component: () => import('../views/ParametrageForm.vue') },
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
