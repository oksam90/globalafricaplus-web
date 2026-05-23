<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Appels à projets</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} appel(s) — tous statuts confondus.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouvel appel
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="call-search" name="call-search"
                    type="search" placeholder="Rechercher titre ou pays…"
                    class="md:col-span-2 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                <select v-model="filters.status" @change="load"
                    id="call-status" name="call-status"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="open">Ouvert</option>
                    <option value="closed">Fermé</option>
                    <option value="awarded">Attribué</option>
                </select>
                <select v-model="filters.sort" @change="load"
                    id="call-sort" name="call-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="recent">Plus récents</option>
                    <option value="closes">Date de clôture</option>
                    <option value="budget">Budget décroissant</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun appel à projets. Cliquez sur « Nouvel appel » pour en créer un.
        </div>
        <div v-else class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Appel</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Statut</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Budget</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Candidatures</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Clôture</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="c in items" :key="c.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="font-semibold line-clamp-1">{{ c.title }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ c.sector || '—' }} · {{ c.author?.name || 'Plateforme' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ c.country }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">
                            {{ c.budget ? formatPrice(c.budget, c.currency) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ c.applications_count }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(c.closes_at) || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <button @click="openEdit(c)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    Modifier
                                </button>
                                <button @click="destroyCall(c)" :disabled="deletingId === c.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ deletingId === c.id ? '…' : 'Supprimer' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-6 flex justify-center gap-2">
            <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-medium border"
                :class="n === meta.current_page ? 'bg-red-600 border-red-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-red-300 dark:hover:border-red-700'">
                {{ n }}
            </button>
        </div>

        <!-- Edit / create modal -->
        <Modal v-model="showModal" size="3xl"
            :title="form.id ? 'Modifier l\'appel à projets' : 'Nouvel appel à projets'">
            <form id="call-form" @submit.prevent="save" class="space-y-5">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Identité de l'appel</legend>
                        <div>
                            <label for="c-title" class="block text-xs font-medium mb-1">Titre *</label>
                            <input id="c-title" name="title" v-model="form.title" required type="text" maxlength="200"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div>
                            <label for="c-desc" class="block text-xs font-medium mb-1">Description *</label>
                            <textarea id="c-desc" name="description" v-model="form.description" required rows="5" maxlength="10000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="c-country" class="block text-xs font-medium mb-1">Pays *</label>
                                <input id="c-country" name="country" v-model="form.country" required type="text" maxlength="80"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="c-zone" class="block text-xs font-medium mb-1">Zone géographique</label>
                                <input id="c-zone" name="geographic_zone" v-model="form.geographic_zone" type="text" maxlength="150"
                                    placeholder="ex. Région de Dakar"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="c-sector" class="block text-xs font-medium mb-1">Secteur</label>
                                <input id="c-sector" name="sector" v-model="form.sector" type="text" maxlength="100"
                                    placeholder="ex. Agritech"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Critères</legend>
                        <div>
                            <label for="c-elig" class="block text-xs font-medium mb-1">Critères d'éligibilité</label>
                            <textarea id="c-elig" name="eligibility_criteria" v-model="form.eligibility_criteria" rows="3" maxlength="5000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                        <div>
                            <label for="c-docs" class="block text-xs font-medium mb-1">Documents requis</label>
                            <textarea id="c-docs" name="required_documents" v-model="form.required_documents" rows="3" maxlength="3000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                        <div>
                            <label for="c-eval" class="block text-xs font-medium mb-1">Critères d'évaluation</label>
                            <textarea id="c-eval" name="evaluation_criteria" v-model="form.evaluation_criteria" rows="3" maxlength="3000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Budget, dates & statut</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="c-budget" class="block text-xs font-medium mb-1">Budget</label>
                                <input id="c-budget" name="budget" v-model.number="form.budget" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="c-currency" class="block text-xs font-medium mb-1">Devise</label>
                                <input id="c-currency" name="currency" v-model="form.currency" type="text" minlength="3" maxlength="3"
                                    pattern="[A-Za-z]{3}" style="text-transform:uppercase"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono uppercase" />
                            </div>
                            <div>
                                <label for="c-status" class="block text-xs font-medium mb-1">Statut</label>
                                <select id="c-status" name="status" v-model="form.status"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                    <option value="draft">Brouillon</option>
                                    <option value="open">Ouvert</option>
                                    <option value="closed">Fermé</option>
                                    <option value="awarded">Attribué</option>
                                </select>
                            </div>
                            <div>
                                <label for="c-opens" class="block text-xs font-medium mb-1">Date d'ouverture</label>
                                <input id="c-opens" name="opens_at" v-model="form.opens_at" type="date"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="c-closes" class="block text-xs font-medium mb-1">Date de clôture</label>
                                <input id="c-closes" name="closes_at" v-model="form.closes_at" type="date"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
            </form>

            <template #footer="{ close }">
                <button type="button" @click="close"
                    class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    Annuler
                </button>
                <button type="submit" form="call-form" :disabled="saving"
                    class="px-5 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm disabled:opacity-50">
                    {{ saving ? 'Enregistrement…' : (form.id ? 'Mettre à jour' : 'Créer') }}
                </button>
            </template>
        </Modal>

        <!-- Toast -->
        <Teleport to="body">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-50 max-w-sm px-5 py-3 rounded-lg shadow-lg text-sm font-medium"
                :class="toast.tone === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
                {{ toast.message }}
            </div>
        </Teleport>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import Modal from '../../components/Modal.vue';

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', status: '', sort: 'recent' });

const showModal = ref(false);
const emptyForm = () => ({
    id: null,
    title: '', description: '', country: '', geographic_zone: '',
    sector: '', eligibility_criteria: '', required_documents: '',
    evaluation_criteria: '',
    budget: null, currency: 'EUR',
    opens_at: '', closes_at: '',
    status: 'draft',
});
const form = reactive(emptyForm());
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

const STATUS_LABELS = { draft: 'Brouillon', open: 'Ouvert', closed: 'Fermé', awarded: 'Attribué' };
function statusLabel(s) { return STATUS_LABELS[s] || s; }
function statusClass(s) {
    return {
        draft:   'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
        open:    'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        closed:  'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        awarded: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    }[s] || 'bg-slate-100';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatPrice(amount, currency) {
    const cur = (currency || 'EUR').toUpperCase();
    try {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: cur === 'XOF' ? 0 : 0 }).format(parseFloat(amount) || 0);
    } catch {
        return `${amount} ${cur}`;
    }
}

let debounce;
function debouncedLoad() {
    clearTimeout(debounce);
    debounce = setTimeout(() => { page.value = 1; load(); }, 300);
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
        Object.keys(params).forEach(k => (params[k] === '' || params[k] == null) && delete params[k]);
        const { data } = await window.axios.get('/api/admin/calls', { params });
        items.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, emptyForm());
    formError.value = '';
    showModal.value = true;
}

async function openEdit(c) {
    formError.value = '';
    try {
        const { data } = await window.axios.get(`/api/admin/calls/${c.id}`);
        Object.assign(form, emptyForm(), data.data, {
            opens_at: data.data.opens_at ? String(data.data.opens_at).slice(0, 10) : '',
            closes_at: data.data.closes_at ? String(data.data.closes_at).slice(0, 10) : '',
        });
    } catch {
        Object.assign(form, emptyForm(), c, {
            opens_at: c.opens_at ? String(c.opens_at).slice(0, 10) : '',
            closes_at: c.closes_at ? String(c.closes_at).slice(0, 10) : '',
        });
    }
    showModal.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form };
        delete payload.id;
        payload.currency = (payload.currency || '').toUpperCase() || null;
        Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null; });

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/calls/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'Appel mis à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/calls', payload);
            await load();
            showToast(data.message || 'Appel créé.', 'success');
        }
        showModal.value = false;
    } catch (e) {
        formError.value = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})[0]?.[0]
            || 'Erreur lors de l\'enregistrement.';
    } finally {
        saving.value = false;
    }
}

async function destroyCall(c) {
    const msg = c.applications_count > 0
        ? `Supprimer « ${c.title} » ? ${c.applications_count} candidature(s) seront perdues.`
        : `Supprimer « ${c.title} » ?`;
    if (!confirm(msg)) return;

    deletingId.value = c.id;
    try {
        const params = c.applications_count > 0 ? { force: true } : {};
        await window.axios.delete(`/api/admin/calls/${c.id}`, { params });
        items.value = items.value.filter(x => x.id !== c.id);
        showToast(`Appel « ${c.title} » supprimé.`, 'success');
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de la suppression.', 'error');
    } finally {
        deletingId.value = null;
    }
}

function showToast(message, tone = 'success') {
    toast.value = { message, tone };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(load);
</script>
