<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes appels à projets</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Créez, gérez et suivez vos appels à projets publics.</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/gouvernement" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                    Portail public →
                </router-link>
                <button @click="showCreateModal = true"
                    class="px-4 py-2 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">
                    + Nouvel appel
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 bg-slate-100 dark:bg-slate-800 rounded-lg p-1 w-fit">
            <button v-for="t in tabs" :key="t.value" @click="switchTab(t.value)"
                class="px-4 py-2 rounded-md text-sm font-medium transition"
                :class="currentTab === t.value ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100'">
                {{ t.label }} ({{ tabCounts[t.value] || 0 }})
            </button>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="filteredCalls.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
            Aucun appel {{ currentTab === 'all' ? '' : 'avec ce statut' }}.
            <button @click="showCreateModal = true" class="block mx-auto mt-4 text-sky-600 dark:text-sky-400 font-semibold hover:underline">
                Créer mon premier appel →
            </button>
        </div>
        <div v-else class="space-y-4">
            <div v-for="c in filteredCalls" :key="c.id"
                class="bg-white dark:bg-slate-800 border rounded-2xl p-5 transition"
                :class="c.status === 'draft' ? 'border-amber-200 dark:border-amber-800/60' : 'border-slate-100 dark:border-slate-700'">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="statusClass(c.status)">
                                {{ statusLabel(c.status) }}
                            </span>
                            <span v-if="c.sector" class="text-xs text-slate-500 dark:text-slate-400">{{ c.sector }}</span>
                        </div>
                        <router-link :to="{ name: 'gouvernement.call', params: { slug: c.slug } }"
                            class="text-lg font-bold hover:text-sky-700 dark:hover:text-sky-400">{{ c.title }}</router-link>
                        <div class="flex flex-wrap gap-4 mt-2 text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="c.budget">{{ fmtMoney(c.budget) }} {{ c.currency }}</span>
                            <span v-if="c.closes_at">Limite : {{ formatDate(c.closes_at) }}</span>
                            <span>{{ c.applications_count || 0 }} candidature(s)</span>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0 flex-wrap">
                        <button v-if="c.status === 'draft'" @click="publishCall(c.id)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                            Publier
                        </button>
                        <button v-if="c.status === 'open'" @click="closeCall(c.id)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200">
                            Clôturer
                        </button>
                        <button v-if="c.status === 'closed'" @click="awardCall(c.id)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md bg-violet-600 hover:bg-violet-700 text-white">
                            Attribuer
                        </button>
                        <router-link v-if="c.applications_count > 0"
                            :to="{ name: 'gouvernement.applications', params: { id: c.id } }"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md border border-sky-200 dark:border-sky-800/60 text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-900/30">
                            Candidatures
                        </router-link>
                        <button @click="editCall(c)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200">
                            Modifier
                        </button>
                        <button v-if="c.status === 'draft'" @click="deleteCall(c.id)"
                            class="px-3 py-1.5 text-sm font-semibold rounded-md border border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
                            Suppr.
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold">{{ editingId ? 'Modifier l\'appel' : 'Nouvel appel à projets' }}</h3>
                    <button @click="closeModal" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-200 text-xl">&times;</button>
                </div>
                <form @submit.prevent="saveCall" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Titre *</label>
                        <input v-model="form.title" type="text" required maxlength="200"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Description *</label>
                        <textarea v-model="form.description" rows="4" required maxlength="10000"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"></textarea>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Pays *</label>
                            <input v-model="form.country" type="text" required class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Zone géographique</label>
                            <input v-model="form.geographic_zone" type="text" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Secteur</label>
                            <input v-model="form.sector" type="text" placeholder="Agritech, Fintech…" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Budget</label>
                            <input v-model.number="form.budget" type="number" min="0" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Devise</label>
                            <select v-model="form.currency" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="XOF">XOF</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Date d'ouverture</label>
                            <input v-model="form.opens_at" type="date" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Date limite</label>
                            <input v-model="form.closes_at" type="date" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Critères d'éligibilité</label>
                        <textarea v-model="form.eligibility_criteria" rows="3" maxlength="5000"
                            placeholder="- Critère 1&#10;- Critère 2"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Documents requis</label>
                        <textarea v-model="form.required_documents" rows="2" maxlength="3000"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Critères d'évaluation</label>
                        <textarea v-model="form.evaluation_criteria" rows="2" maxlength="3000"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"></textarea>
                    </div>

                    <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>

                    <div class="flex gap-3">
                        <button type="submit" :disabled="formSaving"
                            class="px-5 py-2.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm disabled:opacity-50">
                            {{ formSaving ? 'Enregistrement…' : (editingId ? 'Mettre à jour' : 'Créer (brouillon)') }}
                        </button>
                        <button type="button" @click="closeModal"
                            class="px-5 py-2.5 rounded-md border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-semibold">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const allCalls = ref([]);
const loading = ref(true);
const currentTab = ref('all');

const showCreateModal = ref(false);
const editingId = ref(null);
const form = reactive({
    title: '', description: '', country: '', geographic_zone: '', sector: '',
    budget: null, currency: 'EUR', opens_at: '', closes_at: '',
    eligibility_criteria: '', required_documents: '', evaluation_criteria: '',
});
const formSaving = ref(false);
const formError = ref('');

const tabs = [
    { value: 'all', label: 'Tous' },
    { value: 'draft', label: 'Brouillons' },
    { value: 'open', label: 'Ouverts' },
    { value: 'closed', label: 'Clôturés' },
    { value: 'awarded', label: 'Attribués' },
];

const tabCounts = computed(() => {
    const counts = { all: allCalls.value.length };
    for (const c of allCalls.value) {
        counts[c.status] = (counts[c.status] || 0) + 1;
    }
    return counts;
});

const filteredCalls = computed(() =>
    currentTab.value === 'all' ? allCalls.value : allCalls.value.filter(c => c.status === currentTab.value)
);

function switchTab(t) { currentTab.value = t; }

function statusClass(s) {
    return {
        draft: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        open: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        closed: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
        awarded: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function statusLabel(s) {
    return { draft: 'Brouillon', open: 'Ouvert', closed: 'Clôturé', awarded: 'Attribué' }[s] || s;
}
function fmtMoney(a) {
    const n = parseFloat(a) || 0;
    if (n >= 1e6) return (n/1e6).toFixed(1) + 'M';
    if (n >= 1e3) return Math.round(n/1e3) + 'k';
    return n.toLocaleString('fr-FR');
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function closeModal() {
    showCreateModal.value = false;
    editingId.value = null;
    Object.keys(form).forEach(k => form[k] = typeof form[k] === 'number' ? null : '');
    form.currency = 'EUR';
    formError.value = '';
}

function editCall(c) {
    editingId.value = c.id;
    Object.assign(form, {
        title: c.title, description: c.description, country: c.country,
        geographic_zone: c.geographic_zone || '', sector: c.sector || '',
        budget: c.budget, currency: c.currency || 'EUR',
        opens_at: c.opens_at?.slice(0, 10) || '', closes_at: c.closes_at?.slice(0, 10) || '',
        eligibility_criteria: c.eligibility_criteria || '',
        required_documents: c.required_documents || '',
        evaluation_criteria: c.evaluation_criteria || '',
    });
    showCreateModal.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/gouvernement/mes-appels');
        allCalls.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

async function saveCall() {
    formSaving.value = true;
    formError.value = '';
    try {
        if (editingId.value) {
            await window.axios.patch(`/api/gouvernement/appels/${editingId.value}`, form);
        } else {
            await window.axios.post('/api/gouvernement/appels', form);
        }
        closeModal();
        await load();
    } catch (e) {
        formError.value = e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})[0]?.[0] || 'Erreur.';
    } finally {
        formSaving.value = false;
    }
}

async function publishCall(id) {
    if (!confirm('Publier cet appel ? Il sera visible par tous.')) return;
    try { await window.axios.post(`/api/gouvernement/appels/${id}/publish`); await load(); }
    catch (e) { alert(e?.response?.data?.message || 'Erreur.'); }
}
async function closeCall(id) {
    if (!confirm('Clôturer cet appel ?')) return;
    try { await window.axios.post(`/api/gouvernement/appels/${id}/close`); await load(); }
    catch (e) { alert(e?.response?.data?.message || 'Erreur.'); }
}
async function awardCall(id) {
    if (!confirm('Marquer cet appel comme attribué ?')) return;
    try { await window.axios.post(`/api/gouvernement/appels/${id}/award`); await load(); }
    catch (e) { alert(e?.response?.data?.message || 'Erreur.'); }
}
async function deleteCall(id) {
    if (!confirm('Supprimer ce brouillon ?')) return;
    try { await window.axios.delete(`/api/gouvernement/appels/${id}`); await load(); }
    catch (e) { alert(e?.response?.data?.message || 'Erreur.'); }
}

onMounted(load);
</script>
