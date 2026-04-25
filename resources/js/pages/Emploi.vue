<template>
    <div>
        <!-- Hero -->
        <section class="bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-950/40 dark:via-orange-950/30 dark:to-yellow-950/40 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Trouvez votre prochain emploi en <span class="text-amber-600">Afrique</span>
                    </h1>
                    <p class="mt-4 text-lg text-slate-600 dark:text-slate-300">
                        Découvrez les projets innovants qui recrutent sur le continent. Postulez directement et contribuez au développement de l'Afrique.
                    </p>
                </div>
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-6 mt-10 max-w-xl">
                    <div class="text-center">
                        <div class="text-3xl font-black text-amber-700">{{ stats.total_jobs || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Postes visés</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-black text-amber-700">{{ stats.projects_count || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Projets recrutant</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-black text-amber-700">{{ stats.countries || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Pays</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="bg-white dark:bg-slate-800 border-y border-slate-100 dark:border-slate-700 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-bold text-center mb-8">Comment ça marche</h2>
                <div class="grid md:grid-cols-4 gap-6 text-center">
                    <div v-for="(step, i) in steps" :key="i">
                        <div class="w-10 h-10 mx-auto rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold mb-3">{{ i + 1 }}</div>
                        <h3 class="font-semibold text-sm">{{ step.title }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters + Listings -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <h2 class="text-2xl font-bold">Opportunités d'emploi</h2>
                <div class="flex flex-wrap gap-3">
                    <input v-model="filters.search" @input="debouncedLoad" type="text" placeholder="Rechercher…"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm w-48" />
                    <input v-model="filters.country" @input="debouncedLoad" type="text" placeholder="Pays…"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm w-32" />
                    <select v-model="filters.sort" @change="load()" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm">
                        <option value="jobs">Plus de postes</option>
                        <option value="recent">Plus récents</option>
                        <option value="funding">Mieux financés</option>
                    </select>
                </div>
            </div>

            <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-12 text-center">Chargement…</div>
            <div v-else-if="listings.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 rounded-2xl">
                Aucun projet recrutant pour le moment.
            </div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <router-link v-for="p in listings" :key="p.id" :to="`/projets/${p.slug}`"
                    class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 hover:border-amber-200 transition group">
                    <div class="flex items-center gap-2 mb-3">
                        <span v-if="p.category" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                            :style="{ backgroundColor: p.category?.color + '20', color: p.category?.color }">
                            {{ p.category?.name }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ p.country }}</span>
                    </div>
                    <h3 class="font-bold group-hover:text-amber-700 transition">{{ p.title }}</h3>
                    <p v-if="p.summary" class="text-sm text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ p.summary }}</p>
                    <div class="flex items-center gap-4 mt-3 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold text-amber-700">{{ p.jobs_target }} poste(s)</span>
                        <span v-if="p.stage" class="capitalize">{{ p.stage }}</span>
                        <span v-if="p.user">Par {{ p.user.name }}</span>
                    </div>
                    <div v-if="p.amount_needed > 0" class="mt-3">
                        <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-400 rounded-full"
                                :style="{ width: Math.min(100, (p.amount_raised / p.amount_needed) * 100) + '%' }"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                            <span>{{ fmtMoney(p.amount_raised) }} levés</span>
                            <span>{{ fmtMoney(p.amount_needed) }} visés</span>
                        </div>
                    </div>
                </router-link>
            </div>

            <!-- Pagination -->
            <div v-if="meta.last_page > 1" class="mt-8 flex justify-center gap-2">
                <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                    class="px-3 py-1.5 rounded-md text-sm font-medium border"
                    :class="n === meta.current_page ? 'bg-amber-600 border-amber-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                    {{ n }}
                </button>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-amber-600 py-12">
            <div class="max-w-3xl mx-auto px-4 text-center text-white">
                <h2 class="text-2xl font-bold">Prêt à décrocher votre prochain emploi ?</h2>
                <p class="mt-2 text-amber-100">Créez votre profil chercheur d'emploi, ajoutez vos compétences et postulez directement.</p>
                <div class="flex justify-center gap-3 mt-6">
                    <router-link to="/inscription" class="px-6 py-3 rounded-md bg-white dark:bg-slate-800 text-amber-700 font-semibold hover:bg-amber-50">
                        Créer mon profil
                    </router-link>
                    <router-link to="/emploi/mes-competences" class="px-6 py-3 rounded-md border border-white/40 text-white font-semibold hover:bg-white/10">
                        Gérer mes compétences
                    </router-link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';

const stats = ref({});
const listings = ref([]);
const meta = ref({});
const loading = ref(true);
const filters = reactive({ search: '', country: '', sort: 'jobs' });
const page = ref(1);

const steps = [
    { title: 'Créez votre profil', desc: 'Ajoutez vos compétences, expériences et CV.' },
    { title: 'Explorez les projets', desc: 'Découvrez les projets africains qui recrutent.' },
    { title: 'Postulez', desc: 'Envoyez votre candidature avec motivation.' },
    { title: 'Décrochez le poste', desc: 'L\'entrepreneur vous contacte et vous recrute.' },
];

let debounceTimer;
function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { page.value = 1; load(); }, 400);
}
function goToPage(n) { page.value = n; load(); }

function fmtMoney(a) {
    const n = parseFloat(a) || 0;
    if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M €';
    if (n >= 1e3) return Math.round(n / 1e3) + 'k €';
    return n.toLocaleString('fr-FR') + ' €';
}

async function loadStats() {
    try {
        const { data } = await window.axios.get('/api/emploi/stats');
        stats.value = data;
    } catch (e) { /* silent */ }
}

async function load() {
    loading.value = true;
    try {
        const params = { page: page.value };
        if (filters.search) params.search = filters.search;
        if (filters.country) params.country = filters.country;
        if (filters.sort) params.sort = filters.sort;
        const { data } = await window.axios.get('/api/emploi/listings', { params });
        listings.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

onMounted(() => { loadStats(); load(); });
</script>
