<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes candidatures</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Suivez l'état de vos candidatures aux projets recrutant.</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/emploi" class="text-sm font-semibold text-amber-600 dark:text-amber-400 hover:underline">Offres d'emploi →</router-link>
                <router-link to="/emploi/mes-competences" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:underline">Mes compétences →</router-link>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 bg-slate-100 dark:bg-slate-800 rounded-lg p-1 w-fit">
            <button v-for="t in tabs" :key="t.value" @click="currentTab = t.value"
                class="px-4 py-2 rounded-md text-sm font-medium transition"
                :class="currentTab === t.value ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100'">
                {{ t.label }} ({{ tabCounts[t.value] || 0 }})
            </button>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="filtered.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
            Aucune candidature {{ currentTab === 'all' ? '' : 'avec ce statut' }}.
            <router-link to="/emploi" class="block mt-4 text-amber-600 dark:text-amber-400 font-semibold hover:underline">
                Explorer les offres →
            </router-link>
        </div>
        <div v-else class="space-y-4">
            <div v-for="app in filtered" :key="app.id"
                class="bg-white dark:bg-slate-800 border rounded-2xl p-5 transition"
                :class="app.status === 'accepted' ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-900/20' : app.status === 'rejected' ? 'border-red-100 dark:border-red-900/40' : 'border-slate-100 dark:border-slate-700'">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="statusClass(app.status)">
                                {{ statusLabel(app.status) }}
                            </span>
                            <span v-if="app.score != null" class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                Score : {{ app.score }}/100
                            </span>
                        </div>
                        <router-link v-if="app.project" :to="`/projets/${app.project.slug}`"
                            class="text-lg font-bold hover:text-amber-700 dark:hover:text-amber-400">
                            {{ app.project.title }}
                        </router-link>
                        <div class="flex flex-wrap gap-3 mt-1 text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="app.project?.country">{{ app.project.country }}</span>
                            <span v-if="app.project?.category" class="font-semibold"
                                :style="{ color: app.project.category.color }">{{ app.project.category.name }}</span>
                            <span>{{ app.project?.jobs_target || 0 }} poste(s)</span>
                            <span v-if="app.project?.user">Par {{ app.project.user.name }}</span>
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Poste : {{ app.role_applied }}</div>
                        <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-3 mt-2">
                            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Motivation</div>
                            <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line line-clamp-3">{{ app.motivation }}</p>
                        </div>
                        <div v-if="app.review_notes" class="bg-amber-50 dark:bg-amber-900/30 rounded-xl p-3 mt-2">
                            <div class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase mb-1">Retour de l'entrepreneur</div>
                            <p class="text-sm text-amber-800 dark:text-amber-200 whitespace-pre-line">{{ app.review_notes }}</p>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                            Envoyée le {{ formatDate(app.created_at) }}
                            <span v-if="app.reviewed_at"> · Évaluée le {{ formatDate(app.reviewed_at) }}</span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <button v-if="app.status === 'submitted'" @click="withdraw(app.id)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-md border border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
                            Retirer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-8 flex justify-center gap-2">
            <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-medium border"
                :class="n === meta.current_page ? 'bg-amber-600 border-amber-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-700'">
                {{ n }}
            </button>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const allApps = ref([]);
const meta = ref({});
const loading = ref(true);
const currentTab = ref('all');
const page = ref(1);

const tabs = [
    { value: 'all', label: 'Toutes' },
    { value: 'submitted', label: 'En attente' },
    { value: 'under_review', label: 'En revue' },
    { value: 'shortlisted', label: 'Présélectionnées' },
    { value: 'accepted', label: 'Acceptées' },
    { value: 'rejected', label: 'Rejetées' },
];

const tabCounts = computed(() => {
    const counts = { all: allApps.value.length };
    for (const a of allApps.value) {
        counts[a.status] = (counts[a.status] || 0) + 1;
    }
    return counts;
});

const filtered = computed(() =>
    currentTab.value === 'all' ? allApps.value : allApps.value.filter(a => a.status === currentTab.value)
);

function statusClass(s) {
    return {
        submitted: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        under_review: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        shortlisted: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
        accepted: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        rejected: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function statusLabel(s) {
    return { submitted: 'En attente', under_review: 'En revue', shortlisted: 'Présélectionnée', accepted: 'Acceptée', rejected: 'Rejetée' }[s] || s;
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
function goToPage(n) { page.value = n; load(); }

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/emploi/mes-candidatures', { params: { page: page.value } });
        allApps.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

async function withdraw(id) {
    if (!confirm('Retirer cette candidature ?')) return;
    try {
        await window.axios.delete(`/api/emploi/candidatures/${id}`);
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur.');
    }
}

onMounted(load);
</script>
