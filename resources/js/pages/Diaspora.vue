<template>
    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-br from-rose-50 via-amber-50 to-emerald-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-rose-100 text-rose-700 text-xs font-semibold mb-4">
                        Portail Diaspora
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-slate-100 leading-[1.05]">
                        Investissez depuis
                        <span class="block bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-600 bg-clip-text text-transparent">
                            l'étranger.
                        </span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl">
                        Chaque année, <strong>100 milliards de dollars</strong> de remittances arrivent en Afrique.
                        Africa+ vous aide à transformer ces flux en investissements stratégiques qui créent
                        des emplois et de l'impact durable.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#simulator" class="px-5 py-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold shadow-sm">
                            Simuler un investissement
                        </a>
                        <a href="#countries" class="px-5 py-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-rose-300 font-semibold text-slate-800 dark:text-slate-200">
                            Explorer les pays →
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ stats.diaspora_members || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Membres diaspora</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-emerald-600">{{ fmtShort(stats.diaspora_invested) }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Investis via Africa+</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ stats.residence_countries || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Pays de résidence</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-amber-600">100 Md$</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Remittances / an</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black tracking-tight text-center mb-12">Comment investir depuis la diaspora ?</h2>
            <div class="grid md:grid-cols-4 gap-6">
                <div v-for="(step, i) in steps" :key="i" class="text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-rose-100 flex items-center justify-center text-rose-700 text-xl font-black mb-4">
                        {{ i + 1 }}
                    </div>
                    <h3 class="font-bold text-lg">{{ step.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">{{ step.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive map section -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-3xl font-black tracking-tight">Carte des opportunités</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-300">Projets publiés par pays. Cliquez sur un pays pour voir le détail.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <router-link v-for="c in projectsByCountry" :key="c.country"
                    :to="{ name: 'projects.index', query: { country: c.country } }"
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-100 dark:border-slate-700 hover:border-emerald-200 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-lg group-hover:text-emerald-700 transition">{{ c.country }}</h3>
                        <span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                            {{ c.count }} projet{{ c.count > 1 ? 's' : '' }}
                        </span>
                    </div>
                    <div class="flex gap-4 text-sm text-slate-500 dark:text-slate-400">
                        <span>{{ fmtShort(c.raised) }} levés</span>
                        <span>{{ c.jobs }} emplois</span>
                    </div>
                    <div class="mt-3 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500 rounded-full"
                            :style="{ width: ((c.needed > 0 ? (c.raised / c.needed) * 100 : 0)).toFixed(0) + '%' }"></div>
                    </div>
                </router-link>
            </div>
        </div>
    </section>

    <!-- Country Guides -->
    <section id="countries" class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Guides pays</h2>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">Cadre juridique, fiscalité, opportunités et programmes diaspora.</p>
                </div>
            </div>

            <div v-if="countriesLoading" class="text-slate-500 dark:text-slate-400">Chargement des guides…</div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <router-link v-for="g in countryGuides" :key="g.id"
                    :to="{ name: 'diaspora.country', params: { code: g.country_code.toLowerCase() } }"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 hover:border-rose-200 hover:shadow-lg transition overflow-hidden group">
                    <div class="p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-3xl">{{ g.flag }}</span>
                            <div>
                                <h3 class="font-bold text-lg group-hover:text-rose-700 transition">{{ g.country }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ g.currency }} · {{ g.population }}M hab.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-2 text-center">
                                <div class="text-sm font-bold">{{ g.gdp_growth }}%</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase">Croissance</div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-2 text-center">
                                <div class="text-sm font-bold">{{ g.ease_of_business_score }}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase">Doing Biz</div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-2 text-center">
                                <div class="text-sm font-bold">{{ g.remittances_gdp }}%</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase">Remit/PIB</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <span v-for="s in (g.key_sectors || []).slice(0, 3)" :key="s"
                                class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                {{ s }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>{{ g.projects_count }} projets</span>
                            <span class="font-medium text-rose-600 group-hover:underline">Voir le guide →</span>
                        </div>
                    </div>
                </router-link>
            </div>
        </div>
    </section>

    <!-- Simulator -->
    <section id="simulator" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-black tracking-tight">Simulateur d'impact</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">Estimez les emplois créés, le rendement et l'impact social de votre investissement.</p>
            </div>
            <InvestmentSimulator />
        </div>
    </section>

    <!-- Featured diaspora projects -->
    <section class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Projets à fort impact</h2>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">Sélectionnés par notre algorithme pour leur potentiel d'impact et d'emploi.</p>
                </div>
                <router-link to="/projets" class="hidden sm:inline text-sm font-semibold text-rose-600 hover:text-rose-700">
                    Tous les projets →
                </router-link>
            </div>
            <div v-if="projectsLoading" class="text-slate-500 dark:text-slate-400">Chargement…</div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <ProjectCard v-for="p in diasporaProjects" :key="p.id" :project="p" />
            </div>
        </div>
    </section>

    <!-- Advantages -->
    <section class="py-16 bg-gradient-to-br from-rose-600 via-rose-700 to-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black text-center mb-12">Pourquoi investir via Africa+ ?</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div v-for="a in advantages" :key="a.title" class="bg-white/10 backdrop-blur rounded-2xl p-6">
                    <div class="text-3xl mb-3">{{ a.icon }}</div>
                    <h3 class="font-bold text-lg mb-2">{{ a.title }}</h3>
                    <p class="text-rose-100 text-sm">{{ a.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-white dark:bg-slate-800 text-center">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-black">Prêt à investir dans l'Afrique de demain ?</h2>
            <p class="mt-4 text-slate-600 dark:text-slate-300">
                Rejoignez la communauté des investisseurs diaspora et commencez à créer de l'impact.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <router-link to="/inscription" class="px-6 py-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold">
                    Créer mon compte investisseur
                </router-link>
                <router-link to="/projets" class="px-6 py-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-rose-300 font-semibold text-slate-800 dark:text-slate-200">
                    Explorer les projets
                </router-link>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import ProjectCard from '../components/ProjectCard.vue';
import InvestmentSimulator from '../components/InvestmentSimulator.vue';

const stats = ref({});
const projectsByCountry = ref([]);
const countryGuides = ref([]);
const diasporaProjects = ref([]);
const countriesLoading = ref(true);
const projectsLoading = ref(true);

const steps = [
    { title: 'Créez votre compte', text: 'Inscrivez-vous en tant qu\'investisseur diaspora. Complétez votre profil et passez la vérification KYC.' },
    { title: 'Explorez les projets', text: 'Parcourez des centaines de projets par pays, secteur et ODD. Utilisez le simulateur pour estimer l\'impact.' },
    { title: 'Investissez en toute sécurité', text: 'Paiement sécurisé via Stripe, Flutterwave ou mobile money. Fonds en escrow jusqu\'à la validation.' },
    { title: 'Suivez votre impact', text: 'Tableau de bord en temps réel : rendement, emplois créés, mises à jour des entrepreneurs.' },
];

const advantages = [
    { icon: '🛡️', title: 'eKYC & Escrow', text: 'Vérification d\'identité multi-niveaux et fonds sécurisés jusqu\'à la confirmation du projet.' },
    { icon: '📊', title: 'Impact mesurable', text: 'Chaque euro est tracé : emplois créés, familles impactées, objectifs ODD atteints.' },
    { icon: '💸', title: 'Frais réduits', text: 'Coûts de transfert inférieurs aux corridors traditionnels grâce à nos partenaires fintech.' },
    { icon: '🤖', title: 'Matching IA', text: 'Notre algorithme vous suggère les projets les plus alignés avec vos préférences et votre profil.' },
    { icon: '📜', title: 'Guide pays complet', text: 'Cadre juridique, fiscalité, incitations et programmes diaspora pour chaque pays.' },
    { icon: '🌍', title: 'Réseau panafricain', text: 'Accédez à des opportunités dans 54 pays africains depuis votre pays de résidence.' },
];

function fmtShort(n) {
    const v = parseFloat(n) || 0;
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M €';
    if (v >= 1_000) return Math.round(v / 1_000) + 'k €';
    return v + ' €';
}

async function load() {
    try {
        const [{ data: s }, { data: c }, { data: p }] = await Promise.all([
            window.axios.get('/api/diaspora/stats'),
            window.axios.get('/api/diaspora/countries'),
            window.axios.get('/api/diaspora/projects', { params: { per_page: 6 } }),
        ]);

        stats.value = s;
        projectsByCountry.value = s.projects_by_country || [];
        countryGuides.value = c.data || [];
        diasporaProjects.value = (p.data || []).slice(0, 6);
    } catch (e) {
        console.error('Diaspora load error:', e);
    } finally {
        countriesLoading.value = false;
        projectsLoading.value = false;
    }
}

onMounted(load);
</script>
