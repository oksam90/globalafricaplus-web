<template>
    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-br from-sky-50 via-blue-50 to-slate-50 dark:from-sky-950/40 dark:via-blue-950/30 dark:to-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-sky-100 text-sky-700 text-xs font-semibold mb-4">
                        Espace Gouvernement
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-slate-100 leading-[1.05]">
                        Appels à projets
                        <span class="block bg-gradient-to-r from-sky-500 via-blue-500 to-indigo-600 bg-clip-text text-transparent">
                            & Zones Economiques.
                        </span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl">
                        Consultez les appels à projets publics des institutions africaines, explorez les zones
                        économiques spéciales et candidatez directement depuis la plateforme.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#appels" class="px-5 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow-sm">
                            Voir les appels ouverts
                        </a>
                        <a href="#zones" class="px-5 py-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-sky-300 font-semibold text-slate-800 dark:text-slate-200">
                            Zones economiques →
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-sky-600">{{ stats.open_calls || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Appels ouverts</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ stats.countries_count || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Pays actifs</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-emerald-600">{{ fmtMoney(stats.total_budget || 0) }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Budget total</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-amber-500">{{ stats.zones_count || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Zones ZES</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black tracking-tight text-center mb-12">Comment candidater ?</h2>
            <div class="grid md:grid-cols-4 gap-6">
                <div v-for="(step, i) in steps" :key="i" class="text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-sky-100 flex items-center justify-center text-sky-700 text-xl font-black mb-4">
                        {{ i + 1 }}
                    </div>
                    <h3 class="font-bold text-lg">{{ step.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">{{ step.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Open Calls -->
    <section id="appels" class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Appels à projets</h2>
                    <p class="text-slate-600 dark:text-slate-300 mt-2">{{ callsMeta.total || 0 }} appels disponibles.</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
                <div class="grid md:grid-cols-4 gap-3">
                    <input v-model="filters.search" @input="debouncedLoadCalls"
                        type="search" placeholder="Rechercher un appel…"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 focus:border-sky-400 focus:outline-none text-sm md:col-span-2" />
                    <select v-model="filters.status" @change="loadCalls">
                        <option value="">Tous statuts</option>
                        <option value="open">Ouverts</option>
                        <option value="closed">Clôturés</option>
                        <option value="awarded">Attribués</option>
                    </select>
                    <select v-model="filters.sort" @change="loadCalls">
                        <option value="recent">Plus récents</option>
                        <option value="deadline">Date limite</option>
                        <option value="budget">Budget décroissant</option>
                    </select>
                </div>
            </div>

            <!-- Grid -->
            <div v-if="callsLoading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
            <div v-else-if="calls.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-2xl">
                Aucun appel ne correspond à vos critères.
            </div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <router-link v-for="c in calls" :key="c.id"
                    :to="{ name: 'gouvernement.call', params: { slug: c.slug } }"
                    class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 hover:border-sky-200 hover:shadow-sm transition group">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="callStatusClass(c.status)">
                            {{ callStatusLabel(c.status) }}
                        </span>
                        <span v-if="c.sector" class="text-xs px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 font-medium">
                            {{ c.sector }}
                        </span>
                    </div>
                    <h3 class="font-bold text-lg group-hover:text-sky-700 transition line-clamp-2">{{ c.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-2 line-clamp-2">{{ c.description }}</p>
                    <div class="flex flex-wrap gap-3 mt-4 text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ c.country }}</span>
                        <span v-if="c.budget" class="font-semibold text-slate-700 dark:text-slate-200">{{ fmtMoney(c.budget) }} {{ c.currency }}</span>
                        <span v-if="c.closes_at">Limite : {{ formatDate(c.closes_at) }}</span>
                    </div>
                    <div v-if="c.author" class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold text-sky-700">{{ c.author.name }}</span>
                        <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-600 text-[10px] font-bold">Officiel</span>
                    </div>
                </router-link>
            </div>

            <!-- Pagination -->
            <div v-if="callsMeta.last_page > 1" class="mt-10 flex justify-center gap-2">
                <button v-for="n in callsMeta.last_page" :key="n" @click="goToPage(n)"
                    class="px-3 py-1.5 rounded-md text-sm font-medium border"
                    :class="n === callsMeta.current_page ? 'bg-sky-600 border-sky-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-sky-300'">
                    {{ n }}
                </button>
            </div>
        </div>
    </section>

    <!-- Economic Zones -->
    <section id="zones" class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black tracking-tight text-center mb-4">Zones Economiques Spéciales</h2>
            <p class="text-center text-slate-600 dark:text-slate-300 mb-10 max-w-2xl mx-auto">
                Découvrez les ZES africaines offrant des avantages fiscaux, une infrastructure moderne et un accompagnement dédié pour les entreprises.
            </p>

            <div v-if="zones.length === 0" class="text-center py-12 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 rounded-2xl">
                Aucune zone économique enregistrée pour le moment.
            </div>
            <div v-else class="grid md:grid-cols-2 gap-6">
                <div v-for="z in zones" :key="z.id"
                    class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                            :class="z.status === 'active' ? 'bg-emerald-100 text-emerald-700' : z.status === 'planned' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                            {{ zoneStatusLabel(z.status) }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ z.country }} — {{ z.region }}</span>
                    </div>
                    <h3 class="text-lg font-bold">{{ z.name }}</h3>
                    <p v-if="z.description" class="text-sm text-slate-600 dark:text-slate-300 mt-2 line-clamp-3">{{ z.description }}</p>
                    <div v-if="z.area_hectares" class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ z.area_hectares }} hectares</div>
                    <div v-if="z.sectors?.length" class="flex flex-wrap gap-1.5 mt-3">
                        <span v-for="s in z.sectors" :key="s"
                            class="text-[10px] px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 font-medium">{{ s }}</span>
                    </div>
                    <div v-if="z.incentives?.length" class="mt-3 space-y-1">
                        <div v-for="inc in z.incentives" :key="inc" class="text-xs text-emerald-700 flex items-center gap-1">
                            <span class="text-emerald-500">✓</span> {{ inc }}
                        </div>
                    </div>
                    <div class="flex gap-3 mt-4">
                        <a v-if="z.website" :href="z.website" target="_blank" class="text-xs font-semibold text-sky-600 hover:underline">Site web →</a>
                        <a v-if="z.contact_email" :href="'mailto:' + z.contact_email" class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:underline">Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA for government users -->
    <section class="py-20 bg-gradient-to-br from-sky-600 via-blue-700 to-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-black">Vous êtes une institution publique ?</h2>
            <p class="mt-4 text-sky-100 max-w-2xl mx-auto">
                Créez votre espace gouvernemental sur Africa+. Publiez vos appels à projets,
                gérez les candidatures et suivez l'impact économique dans votre pays.
            </p>
            <div class="mt-8 flex justify-center gap-4">
                <router-link to="/inscription"
                    class="px-6 py-3 rounded-lg bg-white dark:bg-slate-800 text-sky-700 font-semibold hover:bg-sky-50">
                    Créer un compte Gouvernement
                </router-link>
                <router-link v-if="auth.isAuthenticated" to="/dashboard"
                    class="px-6 py-3 rounded-lg border border-white/30 text-white font-semibold hover:bg-white/10">
                    Mon tableau de bord →
                </router-link>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const stats = ref({});
const calls = ref([]);
const callsMeta = ref({});
const callsLoading = ref(true);
const zones = ref([]);
const page = ref(1);

const filters = reactive({ search: '', status: '', sort: 'recent' });

const steps = [
    { title: 'Explorez les appels', text: 'Parcourez les appels à projets par pays, secteur et budget.' },
    { title: 'Préparez votre dossier', text: 'Consultez les critères et préparez les documents requis.' },
    { title: 'Candidatez en ligne', text: 'Soumettez votre candidature directement depuis Africa+.' },
    { title: 'Suivi & attribution', text: "Suivez l'état de votre candidature en temps réel." },
];

function fmtMoney(amount) {
    const n = parseFloat(amount) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000) return Math.round(n / 1_000) + 'k';
    return n.toString();
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
function callStatusClass(s) {
    return { open: 'bg-emerald-100 text-emerald-700', closed: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', awarded: 'bg-violet-100 text-violet-700', draft: 'bg-amber-100 text-amber-700' }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function callStatusLabel(s) {
    return { open: 'Ouvert', closed: 'Clôturé', awarded: 'Attribué', draft: 'Brouillon' }[s] || s;
}
function zoneStatusLabel(s) {
    return { active: 'Active', planned: 'En projet', closed: 'Fermée' }[s] || s;
}

let debounce;
function debouncedLoadCalls() {
    clearTimeout(debounce);
    debounce = setTimeout(() => { page.value = 1; loadCalls(); }, 300);
}
function goToPage(n) {
    page.value = n;
    loadCalls();
    window.scrollTo({ top: document.getElementById('appels')?.offsetTop - 80, behavior: 'smooth' });
}

async function loadCalls() {
    callsLoading.value = true;
    try {
        const params = { ...filters, page: page.value };
        Object.keys(params).forEach(k => (params[k] === '' || params[k] == null) && delete params[k]);
        const { data } = await window.axios.get('/api/gouvernement/appels', { params });
        calls.value = data.data || [];
        callsMeta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        callsLoading.value = false;
    }
}

async function load() {
    try {
        const [{ data: s }, { data: z }] = await Promise.all([
            window.axios.get('/api/gouvernement/stats'),
            window.axios.get('/api/gouvernement/zones'),
        ]);
        stats.value = s;
        zones.value = z.data || z || [];
    } catch (e) {
        console.error('Gov portal load error:', e);
    }
    await loadCalls();
}

onMounted(load);
</script>
