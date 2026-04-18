<template>
    <div>
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 via-amber-50 to-violet-50 dark:from-emerald-950/40 dark:via-amber-950/30 dark:to-violet-950/40"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
                <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold mb-4">
                    Packs & Tarifs
                </span>
                <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                    Choisissez le pack
                    <span class="bg-gradient-to-r from-amber-500 via-rose-500 to-emerald-600 bg-clip-text text-transparent">
                        adapté à vos besoins
                    </span>
                </h1>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                    Entrepreneurs, investisseurs, mentors, talents, gouvernements : chaque profil trouve son pack.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <span class="text-sm text-slate-500 dark:text-slate-400" :class="{ 'font-bold text-slate-900 dark:text-slate-100': !yearly }">Mensuel</span>
                    <button @click="yearly = !yearly"
                        class="relative inline-flex h-7 w-14 items-center rounded-full transition-colors"
                        :class="yearly ? 'bg-emerald-600' : 'bg-slate-300'">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white dark:bg-slate-800 shadow transition-transform"
                            :class="yearly ? 'translate-x-8' : 'translate-x-1'"></span>
                    </button>
                    <span class="text-sm text-slate-500 dark:text-slate-400" :class="{ 'font-bold text-slate-900 dark:text-slate-100': yearly }">
                        Annuel <span class="text-emerald-600 font-semibold text-xs">-17%</span>
                    </span>
                </div>
            </div>
        </section>

        <!-- Guarantees bar -->
        <section class="bg-white dark:bg-slate-800 border-y border-slate-100 dark:border-slate-700">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-wrap items-center justify-center gap-8 text-sm text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🛡️</span>
                    <span>Garantie satisfait ou remboursé <strong>30 jours</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-lg">🔓</span>
                    <span>Annulez <strong>à tout instant</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-lg">💬</span>
                    <span>Support disponible <strong>24h/24, 7j/7</strong></span>
                </div>
            </div>
        </section>

        <!-- Plans grid -->
        <section class="py-16 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="loadingPlans" class="text-center text-slate-500 dark:text-slate-400 py-12">Chargement des plans...</div>
                <div v-else class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="plan in plans" :key="plan.slug"
                        class="relative bg-white dark:bg-slate-800 rounded-2xl border-2 p-6 flex flex-col transition-all hover:shadow-lg"
                        :class="plan.is_popular ? 'border-emerald-500 shadow-md' : 'border-slate-100 dark:border-slate-700'">
                        <!-- Popular badge -->
                        <div v-if="plan.is_popular"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-emerald-600 text-white text-xs font-bold rounded-full">
                            Le plus populaire
                        </div>

                        <div class="text-center mb-6">
                            <h3 class="text-xl font-black">{{ plan.name }}</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ plan.subtitle }}</p>
                            <div class="mt-4">
                                <template v-if="plan.slug === 'free'">
                                    <span class="text-4xl font-black text-slate-900 dark:text-slate-100">0 €</span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">/toujours</span>
                                </template>
                                <template v-else>
                                    <span class="text-4xl font-black text-slate-900 dark:text-slate-100">
                                        {{ yearly ? formatPrice(plan.price_yearly) : formatPrice(plan.price_monthly) }}
                                    </span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ yearly ? '/an' : '/mois' }}</span>
                                    <div v-if="yearly" class="text-xs text-emerald-600 font-semibold mt-1">
                                        soit {{ formatPrice(plan.price_yearly / 12) }}/mois
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">{{ plan.description }}</p>

                        <!-- Features list -->
                        <ul class="space-y-2 mb-6 flex-1">
                            <li v-for="(feature, i) in plan.features" :key="i"
                                class="flex items-start gap-2 text-sm">
                                <span class="text-emerald-500 mt-0.5 shrink-0">&#10003;</span>
                                <span>{{ feature }}</span>
                            </li>
                        </ul>

                        <!-- CTA -->
                        <button v-if="plan.slug === 'free'" disabled
                            class="w-full py-3 rounded-lg text-sm font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed">
                            Plan actuel par défaut
                        </button>
                        <button v-else-if="currentPlan === plan.slug"
                            class="w-full py-3 rounded-lg text-sm font-semibold bg-emerald-100 text-emerald-700 cursor-default">
                            &#10003; Votre plan actuel
                        </button>
                        <button v-else @click="subscribeTo(plan)"
                            :disabled="subscribing"
                            class="w-full py-3 rounded-lg text-sm font-semibold transition-colors"
                            :class="plan.is_popular
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                : 'bg-slate-900 hover:bg-slate-800 text-white'">
                            {{ subscribing ? 'En cours...' : plan.slug === 'enterprise' ? 'Nous contacter' : 'Choisir ce plan' }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Role recommendations -->
        <section class="py-16 bg-white dark:bg-slate-800">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-black tracking-tight">Quel pack pour votre profil ?</h2>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">Recommandations par type d'utilisateur</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="rec in recommendations" :key="rec.role"
                        class="bg-slate-50 dark:bg-slate-900 rounded-xl p-5 border border-slate-100 dark:border-slate-700">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl">{{ rec.icon }}</span>
                            <h3 class="font-bold">{{ rec.role }}</h3>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">{{ rec.text }}</p>
                        <span class="inline-block text-xs font-bold px-3 py-1 rounded-full"
                            :class="rec.planClass">{{ rec.plan }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="py-16 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black tracking-tight text-center mb-10">Questions fréquentes</h2>
                <div class="space-y-4">
                    <div v-for="(faq, i) in faqs" :key="i"
                        class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                        <button @click="openFaq = openFaq === i ? -1 : i"
                            class="w-full text-left px-5 py-4 flex items-center justify-between font-semibold text-sm hover:bg-slate-50">
                            {{ faq.q }}
                            <span class="text-slate-400 dark:text-slate-500 transition-transform" :class="{ 'rotate-180': openFaq === i }">&#9660;</span>
                        </button>
                        <div v-if="openFaq === i" class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-300">{{ faq.a }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-16 bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 text-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl md:text-4xl font-black">Investissez dans votre avenir africain</h2>
                <p class="mt-4 text-emerald-50">
                    30 jours de garantie satisfait ou remboursé. Sans engagement. Support 24/7.
                </p>
                <router-link v-if="!auth.isAuthenticated" to="/inscription"
                    class="mt-8 inline-block px-6 py-3 rounded-lg bg-white dark:bg-slate-800 text-emerald-700 font-semibold hover:bg-emerald-50">
                    Commencer gratuitement
                </router-link>
            </div>
        </section>

        <!-- Success/Error toast -->
        <Teleport to="body">
            <div v-if="toast" class="fixed bottom-6 right-6 z-50 max-w-sm px-5 py-3 rounded-lg shadow-lg text-sm font-medium"
                :class="toast.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
                {{ toast.message }}
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

const yearly = ref(false);
const plans = ref([]);
const loadingPlans = ref(true);
const subscribing = ref(false);
const openFaq = ref(-1);
const toast = ref(null);

const currentPlan = computed(() => auth.planSlug);

function formatPrice(price) {
    return parseFloat(price).toFixed(2).replace('.', ',') + ' €';
}

const recommendations = [
    { icon: '🚀', role: 'Entrepreneur', text: 'Publiez vos projets, levez des fonds et accédez au mentorat pour développer votre entreprise.', plan: 'Starter ou Pro', planClass: 'bg-emerald-100 text-emerald-700' },
    { icon: '💰', role: 'Investisseur', text: 'Simulez vos investissements, utilisez le matching IA et accédez à des projets à fort potentiel.', plan: 'Pro recommandé', planClass: 'bg-violet-100 text-violet-700' },
    { icon: '🎓', role: 'Mentor', text: 'Proposez vos services, gérez vos créneaux et accompagnez la prochaine génération.', plan: 'Starter', planClass: 'bg-blue-100 text-blue-700' },
    { icon: '💼', role: 'Chercheur d\'emploi', text: 'Postulez aux offres, gérez vos compétences et faites-vous matcher par l\'IA.', plan: 'Starter', planClass: 'bg-amber-100 text-amber-700' },
    { icon: '🏛️', role: 'Gouvernement', text: 'Publiez des appels à projets, gérez vos ZES et attirez les investissements.', plan: 'Enterprise', planClass: 'bg-sky-100 text-sky-700' },
    { icon: '🌍', role: 'Diaspora', text: 'Investissez dans votre pays d\'origine avec des outils dédiés et un accompagnement personnalisé.', plan: 'Pro', planClass: 'bg-rose-100 text-rose-700' },
];

const faqs = [
    { q: 'Puis-je changer de plan à tout moment ?', a: 'Oui, vous pouvez upgrader ou downgrader votre plan à tout moment. Le changement prend effet immédiatement et le prorata est calculé automatiquement.' },
    { q: 'Comment fonctionne la garantie de 30 jours ?', a: 'Si vous n\'êtes pas satisfait dans les 30 premiers jours de votre abonnement, nous vous remboursons intégralement, sans condition et sans question.' },
    { q: 'Quels moyens de paiement acceptez-vous ?', a: 'Nous acceptons les cartes bancaires (Visa, Mastercard), le mobile money (Orange Money, Wave, M-Pesa), Flutterwave et les virements bancaires pour les plans Enterprise.' },
    { q: 'Le plan gratuit est-il vraiment gratuit ?', a: 'Absolument ! Le plan gratuit vous permet d\'explorer la plateforme, consulter les projets, les offres d\'emploi et les mentors. Aucune carte bancaire requise.' },
    { q: 'Puis-je annuler mon abonnement ?', a: 'Oui, vous pouvez annuler à tout instant depuis votre tableau de bord. Vous conservez l\'accès jusqu\'à la fin de votre période de facturation en cours.' },
    { q: 'Y a-t-il un support dédié ?', a: 'Tous les plans payants incluent un support par email. Le plan Pro offre un support prioritaire sous 24h, et le plan Enterprise bénéficie d\'un support 24/7 avec un account manager dédié.' },
];

async function loadPlans() {
    try {
        const { data } = await window.axios.get('/api/subscription/plans');
        plans.value = data.data || [];
    } catch (e) {
        console.error('Failed to load plans', e);
    } finally {
        loadingPlans.value = false;
    }
}

async function subscribeTo(plan) {
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/tarifs' } });
        return;
    }
    if (plan.slug === 'enterprise') {
        showToast('Contactez-nous à enterprise@africaplus.com pour un devis personnalisé.', 'success');
        return;
    }
    subscribing.value = true;
    try {
        const { data } = await window.axios.post('/api/subscription/subscribe', {
            plan_slug: plan.slug,
            billing_cycle: yearly.value ? 'yearly' : 'monthly',
            payment_method: 'card',
        });
        showToast(data.message || 'Abonnement activé !', 'success');
        // Refresh user data to pick up new subscription
        await auth.fetchUser();
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de la souscription.', 'error');
    } finally {
        subscribing.value = false;
    }
}

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(loadPlans);
</script>
