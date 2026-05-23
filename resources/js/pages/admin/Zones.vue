<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Zones Économiques Spéciales</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} zone(s) — toutes statuts confondus.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouvelle ZES
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="zes-search" name="zes-search"
                    type="search" placeholder="Rechercher nom, pays ou région…"
                    class="md:col-span-2 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                <select v-model="filters.status" @change="load"
                    id="zes-status" name="zes-status"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="active">Active</option>
                    <option value="planned">Planifiée</option>
                    <option value="closed">Fermée</option>
                </select>
                <select v-model="filters.sort" @change="load"
                    id="zes-sort" name="zes-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="name">Nom A-Z</option>
                    <option value="recent">Plus récentes</option>
                    <option value="area">Plus grande surface</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucune ZES. Cliquez sur « Nouvelle ZES » pour en créer une.
        </div>
        <div v-else class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Zone</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays / Région</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Statut</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Surface (ha)</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Secteurs</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="z in items" :key="z.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ z.name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ z.author?.name || 'Plateforme' }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ z.country }}<span v-if="z.region"> · {{ z.region }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="statusClass(z.status)">{{ statusLabel(z.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ z.area_hectares ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                            <span v-if="Array.isArray(z.sectors) && z.sectors.length">{{ z.sectors.slice(0, 3).join(', ') }}<span v-if="z.sectors.length > 3"> …</span></span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <button @click="openEdit(z)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    Modifier
                                </button>
                                <button @click="destroyZone(z)" :disabled="deletingId === z.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ deletingId === z.id ? '…' : 'Supprimer' }}
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

        <!-- Create / edit modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-5 sticky top-0 bg-white dark:bg-slate-800 -mt-2 pt-2 pb-3 border-b border-slate-100 dark:border-slate-700 z-10">
                    <h3 class="text-lg font-bold">
                        {{ form.id ? 'Modifier la ZES' : 'Nouvelle ZES' }}
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 dark:text-slate-500 text-xl">&times;</button>
                </div>

                <form @submit.prevent="save" class="space-y-5">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Identité</legend>
                        <div>
                            <label for="z-name" class="block text-xs font-medium mb-1">Nom *</label>
                            <input id="z-name" name="name" v-model="form.name" required type="text" maxlength="150"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="z-country" class="block text-xs font-medium mb-1">Pays *</label>
                                <input id="z-country" name="country" v-model="form.country" required type="text" maxlength="80"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="z-region" class="block text-xs font-medium mb-1">Région</label>
                                <input id="z-region" name="region" v-model="form.region" type="text" maxlength="100"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="z-area" class="block text-xs font-medium mb-1">Surface (hectares)</label>
                                <input id="z-area" name="area_hectares" v-model.number="form.area_hectares" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label for="z-desc" class="block text-xs font-medium mb-1">Description</label>
                            <textarea id="z-desc" name="description" v-model="form.description" rows="4" maxlength="5000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Incitations & secteurs</legend>
                        <div>
                            <label for="z-incentives" class="block text-xs font-medium mb-1">Incitations
                                <span class="text-slate-400">(une par ligne — ex. exonération IS 5 ans)</span>
                            </label>
                            <textarea id="z-incentives" name="incentives" v-model="incentivesInput" rows="4"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                        <div>
                            <label for="z-sectors" class="block text-xs font-medium mb-1">Secteurs ciblés
                                <span class="text-slate-400">(séparés par des virgules)</span>
                            </label>
                            <input id="z-sectors" name="sectors" v-model="sectorsInput" type="text" maxlength="500"
                                placeholder="Agritech, Logistique, Énergie"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Statut & contact</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="z-status" class="block text-xs font-medium mb-1">Statut</label>
                                <select id="z-status" name="status" v-model="form.status"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                    <option value="active">Active</option>
                                    <option value="planned">Planifiée</option>
                                    <option value="closed">Fermée</option>
                                </select>
                            </div>
                            <div>
                                <label for="z-website" class="block text-xs font-medium mb-1">Site web</label>
                                <input id="z-website" name="website" v-model="form.website" type="url" maxlength="300"
                                    placeholder="https://…"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="z-email" class="block text-xs font-medium mb-1">Email contact</label>
                                <input id="z-email" name="contact_email" v-model="form.contact_email" type="email" maxlength="150"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                    <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>

                    <div class="flex gap-3 pt-2 sticky bottom-0 bg-white dark:bg-slate-800 pb-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <button type="submit" :disabled="saving"
                            class="px-5 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm disabled:opacity-50">
                            {{ saving ? 'Enregistrement…' : (form.id ? 'Mettre à jour' : 'Créer') }}
                        </button>
                        <button type="button" @click="showModal = false"
                            class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>

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
import { computed, onMounted, reactive, ref } from 'vue';

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', status: '', sort: 'name' });

const showModal = ref(false);
const emptyForm = () => ({
    id: null,
    name: '', country: '', region: '', description: '',
    incentives: [], sectors: [],
    area_hectares: null,
    status: 'active', website: '', contact_email: '',
});
const form = reactive(emptyForm());
const incentivesInput = computed({
    get: () => Array.isArray(form.incentives) ? form.incentives.join('\n') : '',
    set: (v) => { form.incentives = String(v).split('\n').map(s => s.trim()).filter(Boolean); },
});
const sectorsInput = computed({
    get: () => Array.isArray(form.sectors) ? form.sectors.join(', ') : '',
    set: (v) => { form.sectors = String(v).split(',').map(s => s.trim()).filter(Boolean); },
});
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

const STATUS_LABELS = { active: 'Active', planned: 'Planifiée', closed: 'Fermée' };
function statusLabel(s) { return STATUS_LABELS[s] || s; }
function statusClass(s) {
    return {
        active:  'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        planned: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        closed:  'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    }[s] || 'bg-slate-100';
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
        const { data } = await window.axios.get('/api/admin/zones', { params });
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

async function openEdit(z) {
    formError.value = '';
    try {
        const { data } = await window.axios.get(`/api/admin/zones/${z.id}`);
        Object.assign(form, emptyForm(), data.data, {
            incentives: Array.isArray(data.data?.incentives) ? data.data.incentives : [],
            sectors: Array.isArray(data.data?.sectors) ? data.data.sectors : [],
        });
    } catch {
        Object.assign(form, emptyForm(), z, {
            incentives: Array.isArray(z.incentives) ? z.incentives : [],
            sectors: Array.isArray(z.sectors) ? z.sectors : [],
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
        Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null; });

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/zones/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'ZES mise à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/zones', payload);
            await load();
            showToast(data.message || 'ZES créée.', 'success');
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

async function destroyZone(z) {
    if (!confirm(`Supprimer la ZES « ${z.name} » ?`)) return;
    deletingId.value = z.id;
    try {
        await window.axios.delete(`/api/admin/zones/${z.id}`);
        items.value = items.value.filter(x => x.id !== z.id);
        showToast(`ZES « ${z.name} » supprimée.`, 'success');
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
