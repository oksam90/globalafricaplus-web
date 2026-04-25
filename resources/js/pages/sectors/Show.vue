<template>
    <div v-if="loading" class="max-w-7xl mx-auto p-12 text-slate-500 dark:text-slate-400">Chargement…</div>
    <section v-else-if="sector" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/secteurs" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">← Tous les secteurs</router-link>

        <!-- Header -->
        <header class="mt-4 mb-10">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white font-black text-2xl"
                    :style="{ backgroundColor: sector.color }">
                    {{ sector.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-black tracking-tight">{{ sector.name }}</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">
                        {{ sector.sub_categories?.length || 0 }} sous-secteurs
                    </p>
                </div>
            </div>
        </header>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-center">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ stats.projects_count }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Projets</div>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-center">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ stats.countries_count }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Pays</div>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-center">
                <div class="text-3xl font-black text-emerald-700 dark:text-emerald-400">{{ formatShort(stats.total_raised) }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Levés</div>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-center">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ stats.jobs_target }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-1">Emplois visés</div>
            </div>
        </div>

        <!-- Sub-categories -->
        <div v-if="sector.sub_categories?.length" class="mb-10">
            <h2 class="text-lg font-bold mb-3">Sous-secteurs</h2>
            <div class="flex flex-wrap gap-2">
                <router-link v-for="sub in sector.sub_categories" :key="sub.id"
                    :to="{ name: 'projects.index', query: { sub_category: sub.id } }"
                    class="px-3 py-1.5 rounded-full text-sm font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/40 hover:text-emerald-700 dark:hover:text-emerald-300 transition">
                    {{ sub.name }}
                </router-link>
            </div>
        </div>

        <!-- Top projects -->
        <div>
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-bold">Projets phares</h2>
                <router-link :to="{ name: 'projects.index', query: { category: sector.id } }"
                    class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline font-semibold">
                    Voir tous les projets →
                </router-link>
            </div>

            <div v-if="!topProjects.length" class="text-center py-10 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
                Aucun projet publié dans ce secteur pour le moment.
            </div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <ProjectCard v-for="p in topProjects" :key="p.id" :project="p" />
            </div>
        </div>

        <!-- Progress bar raised vs needed -->
        <div v-if="stats.total_needed > 0" class="mt-10 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
            <h2 class="text-lg font-bold mb-3">Financement global du secteur</h2>
            <div class="flex justify-between text-sm font-medium text-slate-600 dark:text-slate-300 mb-2">
                <span>{{ formatShort(stats.total_raised) }} levés</span>
                <span>{{ globalProgress }}%</span>
            </div>
            <div class="h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500 rounded-full transition-all"
                    :style="{ width: globalProgress + '%' }"></div>
            </div>
            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 text-right">
                sur {{ formatShort(stats.total_needed) }} recherchés
            </div>
        </div>
    </section>
    <div v-else class="max-w-7xl mx-auto p-12 text-slate-500 dark:text-slate-400">Secteur introuvable.</div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import ProjectCard from '../../components/ProjectCard.vue';

const route = useRoute();

const sector = ref(null);
const stats = ref({ projects_count: 0, countries_count: 0, total_raised: 0, total_needed: 0, jobs_target: 0 });
const topProjects = ref([]);
const loading = ref(true);

const globalProgress = computed(() => {
    if (!stats.value.total_needed) return 0;
    return Math.min(100, Math.round((stats.value.total_raised / stats.value.total_needed) * 100));
});

function formatShort(n) {
    const v = parseFloat(n) || 0;
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M\u00a0\u20ac';
    if (v >= 1_000) return Math.round(v / 1_000) + 'k\u00a0\u20ac';
    return v + '\u00a0\u20ac';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/sectors/${route.params.slug}`);
        sector.value = data.data;
        stats.value = data.stats;
        topProjects.value = data.top_projects || [];
    } catch {
        sector.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.slug, load);
</script>
