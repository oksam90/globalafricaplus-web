<template>
    <div v-if="loading" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Chargement du guide…</div>
    <div v-else-if="!guide" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Guide pays introuvable.</div>
    <div v-else>
        <!-- Header -->
        <section class="bg-gradient-to-br from-rose-50 via-amber-50 to-slate-50 dark:from-rose-950/40 dark:via-amber-950/30 dark:to-slate-900 border-b border-slate-100 dark:border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <router-link to="/diaspora" class="text-sm text-rose-600 hover:underline mb-4 inline-block">
                    ← Portail Diaspora
                </router-link>
                <div class="flex items-center gap-4 mb-6">
                    <span class="text-5xl">{{ guide.flag }}</span>
                    <div>
                        <h1 class="text-3xl md:text-4xl font-black tracking-tight">{{ guide.country }}</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">
                            {{ guide.official_language }} · {{ guide.currency }} · {{ guide.population }}M habitants
                        </p>
                    </div>
                </div>

                <!-- Key metrics -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-700">
                        <div class="text-xl font-black text-slate-900 dark:text-slate-100">{{ guide.gdp }} Md$</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">PIB</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-700">
                        <div class="text-xl font-black text-emerald-700">{{ guide.gdp_growth }}%</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Croissance</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-700">
                        <div class="text-xl font-black text-blue-700">{{ guide.ease_of_business_score }}/100</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Doing Business</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-700">
                        <div class="text-xl font-black text-amber-600">{{ guide.remittances_gdp }}%</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Remittances / PIB</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-700">
                        <div class="text-xl font-black text-rose-600">{{ countryStats.projects_count }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Projets Africa+</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Tabs -->
            <div class="flex gap-1 mb-8 bg-slate-100 dark:bg-slate-700 rounded-lg p-1 overflow-x-auto">
                <button v-for="tab in tabs" :key="tab.id"
                    @click="activeTab = tab.id"
                    class="px-4 py-2 rounded-md text-sm font-medium whitespace-nowrap transition"
                    :class="activeTab === tab.id ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab: Overview -->
            <div v-show="activeTab === 'overview'">
                <div class="grid lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Key sectors -->
                        <div>
                            <h2 class="text-xl font-bold mb-4">Secteurs clés</h2>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="s in (guide.key_sectors || [])" :key="s"
                                    class="px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-50 text-emerald-700">
                                    {{ s }}
                                </span>
                            </div>
                        </div>

                        <!-- Sector breakdown on Africa+ -->
                        <div v-if="sectorBreakdown.length">
                            <h2 class="text-xl font-bold mb-4">Répartition sectorielle des projets</h2>
                            <div class="space-y-3">
                                <div v-for="sec in sectorBreakdown" :key="sec.slug" class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: sec.color }"></div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-baseline">
                                            <span class="font-medium text-sm">{{ sec.name }}</span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ sec.count }} projet{{ sec.count > 1 ? 's' : '' }} · {{ fmtShort(sec.raised) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full"
                                                :style="{ width: barWidth(sec.count) + '%', backgroundColor: sec.color }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Opportunities -->
                        <div v-if="guide.opportunities">
                            <h2 class="text-xl font-bold mb-3">Opportunités</h2>
                            <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line">{{ guide.opportunities }}</div>
                        </div>

                        <!-- Risks -->
                        <div v-if="guide.risks">
                            <h2 class="text-xl font-bold mb-3">Risques à considérer</h2>
                            <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line bg-red-50 rounded-xl p-5 border border-red-100">{{ guide.risks }}</div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-5">
                        <div class="bg-slate-50 dark:bg-slate-900 rounded-2xl p-5">
                            <h3 class="font-bold text-sm mb-3">Agence d'investissement</h3>
                            <p class="text-sm text-slate-700 dark:text-slate-200 font-medium">{{ guide.investment_agency || 'Non renseignée' }}</p>
                            <a v-if="guide.investment_agency_url" :href="guide.investment_agency_url" target="_blank" rel="noopener"
                                class="text-sm text-rose-600 hover:underline mt-1 inline-block">
                                Visiter le site →
                            </a>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-900 rounded-2xl p-5">
                            <h3 class="font-bold text-sm mb-3">Statistiques Africa+</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">Projets publiés</span>
                                    <span class="font-semibold">{{ countryStats.projects_count }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">Montant levé</span>
                                    <span class="font-semibold text-emerald-700">{{ fmtShort(countryStats.total_raised) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">Emplois visés</span>
                                    <span class="font-semibold">{{ countryStats.jobs_target }}</span>
                                </div>
                            </div>
                        </div>

                        <router-link :to="{ name: 'projects.index', query: { country: guide.country } }"
                            class="block text-center py-3 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg transition">
                            Voir les projets {{ guide.country }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Tab: Legal -->
            <div v-show="activeTab === 'legal'">
                <div class="max-w-3xl space-y-8">
                    <div v-if="guide.legal_framework">
                        <h2 class="text-xl font-bold mb-3">Cadre juridique</h2>
                        <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-100 dark:border-slate-700">{{ guide.legal_framework }}</div>
                    </div>
                    <div v-if="guide.taxation">
                        <h2 class="text-xl font-bold mb-3">Fiscalité</h2>
                        <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-100 dark:border-slate-700">{{ guide.taxation }}</div>
                    </div>
                    <div v-if="guide.investment_incentives">
                        <h2 class="text-xl font-bold mb-3">Incitations à l'investissement</h2>
                        <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line bg-emerald-50 rounded-xl p-6 border border-emerald-100">{{ guide.investment_incentives }}</div>
                    </div>
                </div>
            </div>

            <!-- Tab: Diaspora -->
            <div v-show="activeTab === 'diaspora'">
                <div class="max-w-3xl">
                    <div v-if="guide.diaspora_programs">
                        <h2 class="text-xl font-bold mb-3">Programmes diaspora</h2>
                        <div class="prose prose-sm prose-slate max-w-none whitespace-pre-line bg-rose-50 rounded-xl p-6 border border-rose-100">{{ guide.diaspora_programs }}</div>
                    </div>
                    <div v-else class="text-slate-500 dark:text-slate-400 py-10 text-center">
                        Aucun programme diaspora documenté pour ce pays.
                    </div>
                </div>
            </div>

            <!-- Tab: Projects -->
            <div v-show="activeTab === 'projects'">
                <div v-if="!topProjects.length" class="text-center py-12 text-slate-500 dark:text-slate-400">
                    Aucun projet publié dans ce pays pour le moment.
                </div>
                <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <ProjectCard v-for="p in topProjects" :key="p.id" :project="p" />
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import ProjectCard from '../../components/ProjectCard.vue';

const route = useRoute();

const guide = ref(null);
const countryStats = ref({ projects_count: 0, total_raised: 0, jobs_target: 0 });
const topProjects = ref([]);
const sectorBreakdown = ref([]);
const loading = ref(true);
const activeTab = ref('overview');

const tabs = [
    { id: 'overview', label: 'Vue d\'ensemble' },
    { id: 'legal', label: 'Juridique & Fiscalité' },
    { id: 'diaspora', label: 'Programmes diaspora' },
    { id: 'projects', label: 'Projets' },
];

function fmtShort(n) {
    const v = parseFloat(n) || 0;
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M €';
    if (v >= 1_000) return Math.round(v / 1_000) + 'k €';
    return v + ' €';
}

const maxSectorCount = computed(() => Math.max(1, ...sectorBreakdown.value.map(s => s.count)));
function barWidth(count) {
    return Math.max(5, (count / maxSectorCount.value) * 100);
}

async function load() {
    loading.value = true;
    activeTab.value = 'overview';
    try {
        const { data } = await window.axios.get(`/api/diaspora/countries/${route.params.code}`);
        guide.value = data.data;
        countryStats.value = data.stats || {};
        topProjects.value = data.top_projects || [];
        sectorBreakdown.value = data.sector_breakdown || [];
    } catch {
        guide.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.code, load);
</script>
