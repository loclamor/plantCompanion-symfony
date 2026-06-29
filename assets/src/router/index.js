import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
    { path: '/', redirect: { name: 'vegetable-index' } },
    { path: '/jardin/plantes', name: 'vegetable-index', component: () => import('../views/VegetableList.vue') },
    { path: '/jardin/plantes/new', name: 'vegetable-new', component: () => import('../views/VegetableForm.vue') },
    { path: '/jardin/plantes/:id', name: 'vegetable-show', component: () => import('../views/VegetableShow.vue'), props: true },
    { path: '/jardin/plantes/:id/edit', name: 'vegetable-edit', component: () => import('../views/VegetableForm.vue'), props: true },
    { path: '/jardin/calendrier', name: 'calendar', component: () => import('../views/CalendarView.vue') },
    { path: '/jardin/impression', name: 'print', component: () => import('../views/PrintView.vue') },
    { path: '/profil', name: 'profile', component: () => import('../views/ProfileView.vue') },
    { path: '/jardin/photos/import', name: 'photo-import', component: () => import('../views/PhotoImport.vue') },
    { path: '/jardin/interventions', name: 'action-index', component: () => import('../views/ActionList.vue') },
    { path: '/jardin/interventions/new', name: 'action-new', component: () => import('../views/ActionForm.vue') },
    { path: '/jardin/interventions/:id/edit', name: 'action-edit', component: () => import('../views/ActionForm.vue'), props: true },
    { path: '/potager/grainotheque', name: 'graine-index', component: () => import('../views/graine/GraineList.vue') },
    { path: '/potager/grainotheque/new', name: 'graine-new', component: () => import('../views/graine/GraineForm.vue') },
    { path: '/potager/grainotheque/:id', name: 'graine-detail', component: () => import('../views/graine/GraineDetail.vue'), props: true },
    { path: '/potager/grainotheque/:id/edit', name: 'graine-edit', component: () => import('../views/graine/GraineForm.vue'), props: true },
    { path: '/potager/types-graines', name: 'graine-type-index', component: () => import('../views/graine/GraineTypeList.vue') },
    { path: '/potager/types-graines/new', name: 'graine-type-new', component: () => import('../views/graine/GraineTypeForm.vue') },
    { path: '/potager/types-graines/:id/edit', name: 'graine-type-edit', component: () => import('../views/graine/GraineTypeForm.vue'), props: true },
    { path: '/potager/saisons', name: 'saison-index', component: () => import('../views/saison/SaisonList.vue') },
    { path: '/potager/saisons/new', name: 'saison-new', component: () => import('../views/saison/SaisonForm.vue') },
    { path: '/potager/saisons/:id/edit', name: 'saison-edit', component: () => import('../views/saison/SaisonForm.vue'), props: true },
    { path: '/potager/bacs', name: 'bac-index', component: () => import('../views/bac/BacList.vue') },
    { path: '/potager/bacs/new', name: 'bac-new', component: () => import('../views/bac/BacForm.vue') },
    { path: '/potager/bacs/:id/edit', name: 'bac-edit', component: () => import('../views/bac/BacForm.vue'), props: true },
    { path: '/potager/semis', name: 'semis-index', component: () => import('../views/semis/SemisList.vue') },
    { path: '/potager/semis/new', name: 'semis-new', component: () => import('../views/semis/SemisForm.vue') },
    { path: '/potager/semis/batch', name: 'semis-batch', component: () => import('../views/semis/SemisBatchForm.vue') },
    { path: '/potager/semis/:id/edit', name: 'semis-edit', component: () => import('../views/semis/SemisForm.vue'), props: true },
    { path: '/potager/cultures', name: 'culture-index', component: () => import('../views/culture/CultureList.vue') },
    { path: '/potager/cultures/new', name: 'culture-new', component: () => import('../views/culture/CultureForm.vue') },
    { path: '/potager/cultures/:id/edit', name: 'culture-edit', component: () => import('../views/culture/CultureForm.vue'), props: true },
    { path: '/potager/plan', name: 'plan-index', component: () => import('../views/potager/PlanReadView.vue') },
    { path: '/potager/plan-edition', name: 'plan-edit', component: () => import('../views/potager/PlanView.vue') },
    { path: '/potager/impression', name: 'potager-print', component: () => import('../views/potager/PotagerPrintView.vue') },
    { path: '/jardin/parametrage/:resource', name: 'parametrage-index', component: () => import('../views/ParametrageList.vue') },
    { path: '/jardin/parametrage/:resource/new', name: 'parametrage-new', component: () => import('../views/ParametrageForm.vue') },
    { path: '/jardin/parametrage/:resource/:id/edit', name: 'parametrage-edit', component: () => import('../views/ParametrageForm.vue') },
];

const router = createRouter({
    // Le SPA est servi à la racine (les pages Twig CRUD sont décommissionnées).
    history: createWebHistory('/'),
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
