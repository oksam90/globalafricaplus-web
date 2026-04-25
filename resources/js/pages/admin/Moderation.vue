<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Modération des projets</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Examinez et validez les projets soumis par les entrepreneurs.</p>
            </div>
            <router-link to="/dashboard" class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                ← Dashboard admin
            </router-link>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 bg-slate-100 dark:bg-slate-800 rounded-lg p-1 w-fit">
            <button v-for="t in tabs" :key="t.value" @click="switchTab(t.value)"
                class="px-4 py-2 rounded-md text-sm font-medium transition relative"
                :class="currentTab === t.value ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100'">
                {{ t.label }}
                <span v-if="t.value === 'pending' && pendingCount > 0"
                    class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold rounded-full bg-rose-500 text-white">
                    {{ pendingCount }}
                </span>
            </button>
        </div>

        <!-- Sort -->
        <div class="flex gap-3 mb-6">
            <select v-model="sort" @change="load"
                class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm">
                <option value="oldest">Plus anciens d'abord</option>
                <option value="recent">Plus récents</option>
                <option value="amount">Montant décroissant</option>
            </select>
        </div>

        <!-- List -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="projects.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
            Aucun projet {{ currentTab === 'pending' ? 'en attente de modération' : 'dans cette catégorie' }}.
        </div>
        <div v-else class="space-y-4">
            <div v-for="p in projects" :key="p.id"
                class="bg-white dark:bg-slate-800 border rounded-2xl p-5 transition"
                :class="p.status === 'pending' ? 'border-amber-200 dark:border-amber-800/60' : 'border-slate-100 dark:border-slate-700'">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="statusClass(p.status)">
                                {{ statusLabel(p.status) }}
                            </span>
                            <span v-if="p.category" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                :style="{ backgroundColor: p.category.color + '20', color: p.category.color }">
                                {{ p.category.name }}
                            </span>
                        </div>

                        <router-link :to="`/projets/${p.slug}`" class="text-lg font-bold hover:text-red-700 dark:hover:text-red-400 transition">
                            {{ p.title }}
                        </router-link>

                        <p v-if="p.summary" class="text-sm text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ p.summary }}</p>

                        <div class="flex flex-wrap gap-4 mt-3 text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="p.user">
                                Par <strong class="text-slate-700 dark:text-slate-200">{{ p.user.name }}</strong>
                                <span v-if="p.user.email" class="ml-1">({{ p.user.email }})</span>
                            </span>
                            <span>{{ p.country || '—' }}</span>
                            <span v-if="p.amount_needed">{{ fmtMoney(p.amount_needed) }} demandé</span>
                            <span>{{ p.followers_count || 0 }} followers</span>
                            <span>Créé le {{ formatDate(p.created_at) }}</span>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-2 shrink-0">
                        <template v-if="p.status === 'pending'">
                            <button @click="moderate(p.id, 'approve')"
                                class="px-3 py-1.5 text-sm font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                                Approuver
                            </button>
                            <button @click="moderate(p.id, 'reject')"
                                class="px-3 py-1.5 text-sm font-semibold rounded-md bg-rose-600 hover:bg-rose-700 text-white">
                                Rejeter
                            </button>
                        </template>
                        <template v-else-if="p.status === 'published'">
                            <button @click="moderate(p.id, 'unpublish')"
                                class="px-3 py-1.5 text-sm font-semibold rounded-md border border-amber-300 dark:border-amber-800/60 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                Dé-publier
                            </button>
                        </template>
                        <template v-else-if="p.status === 'rejected' || p.status === 'draft'">
                            <button @click="moderate(p.id, 'approve')"
                                class="px-3 py-1.5 text-sm font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                                Publier
                            </button>
                        </template>
                        <button @click="confirmDelete(p)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md border border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
                            Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-8 flex justify-center gap-2">
            <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-medium border"
                :class="n === meta.current_page ? 'bg-red-600 border-red-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-red-300 dark:hover:border-red-700'">
                {{ n }}
            </button>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const projects = ref([]);
const meta = ref({});
const loading = ref(true);
const currentTab = ref('pending');
const sort = ref('oldest');
const page = ref(1);
const pendingCount = ref(0);

const tabs = [
    { value: 'pending', label: 'En attente' },
    { value: 'published', label: 'Publiés' },
    { value: 'rejected', label: 'Rejetés' },
    { value: 'draft', label: 'Brouillons' },
    { value: 'all', label: 'Tous' },
];

function statusClass(s) {
    return {
        pending: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        published: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        rejected: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
        draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}

function statusLabel(s) {
    return { pending: 'En attente', published: 'Publié', rejected: 'Rejeté', draft: 'Brouillon' }[s] || s;
}

function fmtMoney(amount) {
    const n = parseFloat(amount) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M €';
    if (n >= 1_000) return Math.round(n / 1_000) + 'k €';
    return n + ' €';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function switchTab(tab) {
    currentTab.value = tab;
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
        const params = { status: currentTab.value, sort: sort.value, page: page.value };
        const { data } = await window.axios.get('/api/admin/moderation', { params });
        projects.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };

        // Also get pending count for badge
        if (currentTab.value !== 'pending') {
            const { data: pendingData } = await window.axios.get('/api/admin/moderation', { params: { status: 'pending', per_page: 1 } });
            pendingCount.value = pendingData.total || 0;
        } else {
            pendingCount.value = meta.value.total || 0;
        }
    } finally {
        loading.value = false;
    }
}

async function moderate(projectId, action) {
    const labels = { approve: 'Approuver', reject: 'Rejeter', unpublish: 'Dé-publier' };
    if (!confirm(`${labels[action] || action} ce projet ?`)) return;

    try {
        await window.axios.post(`/api/admin/moderation/${projectId}`, { action });
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur lors de la modération.');
    }
}

async function confirmDelete(project) {
    if (!confirm(`Supprimer définitivement "${project.title}" ? Cette action est irréversible.`)) return;
    try {
        await window.axios.post(`/api/admin/moderation/${project.id}`, { action: 'delete' });
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur lors de la suppression.');
    }
}

onMounted(load);
</script>
