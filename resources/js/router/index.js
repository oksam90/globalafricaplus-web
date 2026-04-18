import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/', name: 'home', component: () => import('../pages/Home.vue') },

    // Projects
    { path: '/projets', name: 'projects.index', component: () => import('../pages/projects/Index.vue') },
    {
        path: '/projets/mes-projets',
        name: 'projects.mine',
        component: () => import('../pages/projects/Mine.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/projets/nouveau',
        name: 'projects.create',
        component: () => import('../pages/projects/CreateEdit.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/projets/:slug/modifier',
        name: 'projects.edit',
        component: () => import('../pages/projects/CreateEdit.vue'),
        meta: { requiresAuth: true },
    },
    { path: '/projets/:slug', name: 'projects.show', component: () => import('../pages/projects/Show.vue'), props: true },

    // Sectors
    { path: '/secteurs', name: 'sectors.index', component: () => import('../pages/sectors/Index.vue') },
    { path: '/secteurs/:slug', name: 'sectors.show', component: () => import('../pages/sectors/Show.vue'), props: true },

    // Diaspora portal
    { path: '/diaspora', name: 'diaspora', component: () => import('../pages/Diaspora.vue') },
    { path: '/diaspora/pays/:code', name: 'diaspora.country', component: () => import('../pages/diaspora/CountryGuide.vue'), props: true },

    // Formalisation
    { path: '/formalisation', name: 'formalisation', component: () => import('../pages/Formalisation.vue') },
    {
        path: '/formalisation/mon-parcours',
        name: 'formalisation.parcours',
        component: () => import('../pages/formalisation/MonParcours.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/formalisation/business-plans',
        name: 'formalisation.templates',
        component: () => import('../pages/formalisation/BusinessPlans.vue'),
    },

    // Emploi
    { path: '/emploi', name: 'emploi', component: () => import('../pages/Emploi.vue') },
    {
        path: '/emploi/mes-candidatures',
        name: 'emploi.candidatures',
        component: () => import('../pages/emploi/MesCandidatures.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/emploi/mes-competences',
        name: 'emploi.competences',
        component: () => import('../pages/emploi/MesCompetences.vue'),
        meta: { requiresAuth: true },
    },

    // Tarifs
    { path: '/tarifs', name: 'tarifs', component: () => import('../pages/Tarifs.vue') },

    // Mentorat
    { path: '/mentorat', name: 'mentorat', component: () => import('../pages/Mentorat.vue') },
    { path: '/mentorat/mentors/:id', name: 'mentorat.mentor', component: () => import('../pages/mentorat/MentorProfile.vue'), props: true },
    {
        path: '/mentorat/mes-mentorats',
        name: 'mentorat.mine',
        component: () => import('../pages/mentorat/MesMentorats.vue'),
        meta: { requiresAuth: true },
    },

    // Gouvernement portal
    { path: '/gouvernement', name: 'gouvernement', component: () => import('../pages/Gouvernement.vue') },
    { path: '/gouvernement/appels/:slug', name: 'gouvernement.call', component: () => import('../pages/gouvernement/CallShow.vue'), props: true },
    {
        path: '/gouvernement/mes-appels',
        name: 'gouvernement.mine',
        component: () => import('../pages/gouvernement/MesAppels.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/gouvernement/appels/:id/candidatures',
        name: 'gouvernement.applications',
        component: () => import('../pages/gouvernement/Applications.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/gouvernement/mes-zones',
        name: 'gouvernement.zones',
        component: () => import('../pages/gouvernement/MesZones.vue'),
        meta: { requiresAuth: true },
    },

    // KYC
    {
        path: '/kyc',
        name: 'kyc',
        component: () => import('../pages/Kyc.vue'),
        meta: { requiresAuth: true },
    },

    // Auth
    { path: '/connexion', name: 'login', component: () => import('../pages/auth/Login.vue') },
    { path: '/inscription', name: 'register', component: () => import('../pages/auth/Register.vue') },

    // Authenticated
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../pages/Dashboard.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/profil',
        name: 'profile.index',
        component: () => import('../pages/profile/Index.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/profil/ajouter-role',
        name: 'profile.add-role',
        component: () => import('../pages/profile/AddRole.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/profil/:slug',
        name: 'profile.edit',
        component: () => import('../pages/profile/Edit.vue'),
        meta: { requiresAuth: true },
    },

    // Admin
    {
        path: '/admin/utilisateurs',
        name: 'admin.users',
        component: () => import('../pages/admin/Users.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/admin/moderation',
        name: 'admin.moderation',
        component: () => import('../pages/admin/Moderation.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/admin/analytics',
        name: 'admin.analytics',
        component: () => import('../pages/admin/Analytics.vue'),
        meta: { requiresAuth: true },
    },

    // Catch-all
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../pages/NotFound.vue') },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Only block navigation to fetch user if we haven't bootstrapped yet
    // AND the target page actually needs auth info (auth-gated pages).
    // Public pages render immediately without waiting.
    if (!auth.bootstrapped) {
        if (to.meta.requiresAuth) {
            // Must wait — we need to know if user is logged in
            await auth.fetchUser();
        } else {
            // Fire & forget — page renders while user check happens in background
            auth.fetchUser();
        }
    }

    if (to.meta.requiresAuth && !auth.user) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
});

export default router;
