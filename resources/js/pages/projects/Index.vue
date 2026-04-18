<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-black tracking-tight">Projets</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-2">Découvrez les initiatives entrepreneuriales qui transforment l'Afrique.</p>
            </div>
            <div class="flex gap-2">
                <router-link v-if="auth.hasRole('entrepreneur') || auth.hasRole('admin')" to="/projets/nouveau"
                    class="text-sm font-semibold px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                    + Nouveau projet
                </router-link>
                <router-link to="/secteurs" class="text-sm font-semibold px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 hover:border-emerald-300">
                    Par secteur
                </router-link>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.search" @input="debouncedLoad"
                    type="search" placeholder="Rechercher un projet…"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 focus:border-emerald-400 focus:outline-none text-sm md:col-span-2" />

                <select v-model="filters.category" @change="onCategoryChange" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                    <option value="">Tous secteurs</option>
                    <option v-for="c in categories" :key="c.id" :value="c.slug">{{ c.name }}</option>
                </select>

                <select v-model="filters.sub_category" @change="load" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm" :disabled="!subCategories.length">
                    <option value="">Sous-secteur</option>
                    <option v-for="s in subCategories" :key="s.id" :value="s.slug">{{ s.name }}</option>
                </select>

                <input v-model="filters.country" @input="debouncedLoad" type="text" placeholder="Pays"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 focus:border-emerald-400 focus:outline-none text-sm" />

                <select v-model="filters.stage" @change="load" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                    <option value="">Tous stades</option>
                    <option value="idea">Idée</option>
                    <option value="mvp">MVP</option>
                    <option value="launch">Lancement</option>
                    <option value="scaling">Croissance</option>
                </select>

                <select v-model="filters.sdg" @change="load" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                    <option value="">Tous ODD</option>
                    <option v-for="s in sdgs" :key="s.id" :value="s.number">
                        ODD {{ s.number }} — {{ s.name }}
                    </option>
                </select>

                <select v-model="filters.sort" @change="load" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                    <option value="">Plus récents</option>
                    <option value="popular">Plus populaires</option>
                    <option value="trending">Tendance</option>
                    <option value="ending">Bientôt clos</option>
                    <option value="progress">Les plus avancés</option>
                    <option value="jobs">Plus d'emplois créés</option>
                </select>
            </div>

            <div class="grid md:grid-cols-4 gap-3 mt-3">
                <input v-model.number="filters.amount_min" @input="debouncedLoad" type="number" min="0" placeholder="Montant min (€)"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm" />
                <input v-model.number="filters.amount_max" @input="debouncedLoad" type="number" min="0" placeholder="Montant max (€)"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm" />
                <button @click="reset" class="text-sm text-slate-600 dark:text-slate-300 hover:text-emerald-700 md:col-span-2 text-right">
                    Réinitialiser les filtres
                </button>
            </div>
        </div>

        <div class="text-sm text-slate-500 dark:text-slate-400 mb-4" v-if="meta.total !== undefined">
            {{ meta.total }} projet{{ meta.total > 1 ? 's' : '' }} trouvé{{ meta.total > 1 ? 's' : '' }}
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400">Chargement…</div>
        <div v-else-if="projects.length === 0" class="text-center py-20 text-slate-500 dark:text-slate-400">
            Aucun projet ne correspond à vos critères.
        </div>
        <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <ProjectCard v-for="p in projects" :key="p.id" :project="p" />
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-10 flex justify-center gap-2">
            <button v-for="n in meta.last_page" :key="n"
                @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-medium border"
                :class="n === meta.current_page ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-emerald-300'">
                {{ n }}
            </button>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProjectCard from '../../components/ProjectCard.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const projects = ref([]);
const categories = ref([]);
const subCategories = ref([]);
const sdgs = ref([]);
const loading = ref(true);
const meta = ref({});
const page = ref(1);

const filters = reactive({
    search: '',
    category: '',
    sub_category: '',
    country: '',
    stage: '',
    sdg: '',
    sort: '',
    amount_min: '',
    amount_max: '',
});

/**
 * Sync filters from URL query params on mount (e.g. ?country=Sénégal&category=agritech).
 */
function syncFromQuery() {
    const q = route.query;
    if (q.search) filters.search = q.search;
    if (q.category) filters.category = q.category;
    if (q.sub_category) filters.sub_category = q.sub_category;
    if (q.country) filters.country = q.country;
    if (q.stage) filters.stage = q.stage;
    if (q.sdg) filters.sdg = q.sdg;
    if (q.sort) filters.sort = q.sort;
    if (q.amount_min) filters.amount_min = Number(q.amount_min);
    if (q.amount_max) filters.amount_max = Number(q.amount_max);
    if (q.page) page.value = Number(q.page);
}

let debounce;
function debouncedLoad() {
    clearTimeout(debounce);
    debounce = setTimeout(() => { page.value = 1; load(); }, 300);
}

function onCategoryChange() {
    filters.sub_category = '';
    const cat = categories.value.find((c) => c.slug === filters.category);
    subCategories.value = cat?.sub_categories || [];
    page.value = 1;
    load();
}

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ''));
    subCategories.value = [];
    page.value = 1;
    load();
}

function goToPage(n) {
    page.value = n;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function load() {
    loading.value = true;
    try {
        const params = { ...filters, page: page.value };
        Object.keys(params).forEach((k) => (params[k] === '' || params[k] == null) && delete params[k]);

        // Keep URL in sync with filters (replace, not push, to avoid history spam)
        router.replace({ query: { ...params } }).catch(() => {});

        const { data } = await window.axios.get('/api/projects', { params });
        projects.value = data.data || [];
        meta.value = {
            total: data.total,
            last_page: data.last_page,
            current_page: data.current_page,
        };
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    // Read filters from URL query params first (e.g. ?country=Sénégal)
    syncFromQuery();

    const [sectorsRes, sdgsRes] = await Promise.all([
        window.axios.get('/api/sectors'),
        window.axios.get('/api/sdgs'),
    ]);
    categories.value = sectorsRes.data.data || [];
    sdgs.value = sdgsRes.data.data || [];

    // If a category was set from URL, populate sub-categories
    if (filters.category) {
        const cat = categories.value.find((c) => c.slug === filters.category);
        subCategories.value = cat?.sub_categories || [];
    }

    await load();
});

// Re-sync if user navigates to same page with different query params
watch(() => route.query, (newQ, oldQ) => {
    if (JSON.stringify(newQ) !== JSON.stringify(oldQ)) {
        syncFromQuery();
        if (filters.category) {
            const cat = categories.value.find((c) => c.slug === filters.category);
            subCategories.value = cat?.sub_categories || [];
        }
        load();
    }
});
</script>
