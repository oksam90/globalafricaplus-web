<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Guides pays</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} guide(s) — cadre juridique, fiscalité, opportunités et programmes diaspora.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouveau guide
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-3 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="guide-search" name="guide-search"
                    type="search" placeholder="Rechercher pays ou code ISO…"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm md:col-span-2" />
                <select v-model="filters.sort" @change="load"
                    id="guide-sort" name="guide-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="country">Nom A-Z</option>
                    <option value="recent">Plus récents</option>
                    <option value="gdp">PIB décroissant</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun guide pays. Cliquez sur « Nouveau guide » pour en créer un.
        </div>
        <div v-else class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Devise</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Population</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">PIB (mds USD)</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="g in items" :key="g.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{{ g.flag || '🌍' }}</span>
                                <div>
                                    <div class="font-semibold">{{ g.country }}</div>
                                    <div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ g.country_code }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ g.currency }}</td>
                        <td class="px-4 py-3 text-right">{{ g.population }} M</td>
                        <td class="px-4 py-3 text-right">{{ g.gdp }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <button @click="openEdit(g)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    Modifier
                                </button>
                                <button @click="destroyGuide(g)" :disabled="deletingId === g.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ deletingId === g.id ? '…' : 'Supprimer' }}
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
            :title="form.id ? 'Modifier le guide pays' : 'Nouveau guide pays'">
            <form id="country-guide-form" @submit.prevent="save" class="space-y-5">
                    <!-- Identité pays -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Identité</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label for="g-country" class="block text-xs font-medium mb-1">Pays *</label>
                                <input id="g-country" name="country" v-model="form.country" required type="text" maxlength="100"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-code" class="block text-xs font-medium mb-1">Code ISO 2 *</label>
                                <input id="g-code" name="country_code" v-model="form.country_code" required type="text" minlength="2" maxlength="2"
                                    pattern="[A-Za-z]{2}" style="text-transform:uppercase"
                                    placeholder="SN"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono uppercase" />
                            </div>
                            <div>
                                <label for="g-flag" class="block text-xs font-medium mb-1">Drapeau emoji</label>
                                <input id="g-flag" name="flag" v-model="form.flag" type="text" maxlength="10"
                                    placeholder="🇸🇳"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-currency" class="block text-xs font-medium mb-1">Devise ISO 3</label>
                                <input id="g-currency" name="currency" v-model="form.currency" type="text" minlength="3" maxlength="3"
                                    pattern="[A-Za-z]{3}" style="text-transform:uppercase"
                                    placeholder="XOF"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono uppercase" />
                            </div>
                            <div>
                                <label for="g-lang" class="block text-xs font-medium mb-1">Langue officielle</label>
                                <input id="g-lang" name="official_language" v-model="form.official_language" type="text" maxlength="60"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Macro -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Indicateurs macro</legend>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label for="g-pop" class="block text-xs font-medium mb-1">Population (M)</label>
                                <input id="g-pop" name="population" v-model.number="form.population" type="number" min="0" step="1"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-gdp" class="block text-xs font-medium mb-1">PIB (mds USD)</label>
                                <input id="g-gdp" name="gdp" v-model.number="form.gdp" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-growth" class="block text-xs font-medium mb-1">Croissance PIB (%)</label>
                                <input id="g-growth" name="gdp_growth" v-model.number="form.gdp_growth" type="number" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-rem" class="block text-xs font-medium mb-1">Remises diaspora (% PIB)</label>
                                <input id="g-rem" name="remittances_gdp" v-model.number="form.remittances_gdp" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-eob" class="block text-xs font-medium mb-1">Ease of Business (0-100)</label>
                                <input id="g-eob" name="ease_of_business_score" v-model.number="form.ease_of_business_score" type="number" min="0" max="100" step="0.1"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div class="col-span-2">
                                <label for="g-sectors" class="block text-xs font-medium mb-1">Secteurs clés
                                    <span class="text-slate-400">(séparés par des virgules)</span>
                                </label>
                                <input id="g-sectors" name="key_sectors" v-model="keySectorsInput" type="text" maxlength="300"
                                    placeholder="Agritech, Fintech, Énergie"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Agence -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Agence d'investissement</legend>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="g-agency" class="block text-xs font-medium mb-1">Nom</label>
                                <input id="g-agency" name="investment_agency" v-model="form.investment_agency" type="text" maxlength="120"
                                    placeholder="ex. APIX (Sénégal)"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="g-agency-url" class="block text-xs font-medium mb-1">URL</label>
                                <input id="g-agency-url" name="investment_agency_url" v-model="form.investment_agency_url" type="url" maxlength="255"
                                    placeholder="https://…"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Contenus longs -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Contenu (Markdown/HTML)</legend>
                        <div>
                            <label for="g-legal" class="block text-xs font-medium mb-1">Cadre juridique</label>
                            <textarea id="g-legal" name="legal_framework" v-model="form.legal_framework" rows="4" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                        <div>
                            <label for="g-tax" class="block text-xs font-medium mb-1">Fiscalité</label>
                            <textarea id="g-tax" name="taxation" v-model="form.taxation" rows="4" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                        <div>
                            <label for="g-inc" class="block text-xs font-medium mb-1">Incitations à l'investissement</label>
                            <textarea id="g-inc" name="investment_incentives" v-model="form.investment_incentives" rows="3" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                        <div>
                            <label for="g-opp" class="block text-xs font-medium mb-1">Opportunités</label>
                            <textarea id="g-opp" name="opportunities" v-model="form.opportunities" rows="3" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                        <div>
                            <label for="g-risks" class="block text-xs font-medium mb-1">Risques</label>
                            <textarea id="g-risks" name="risks" v-model="form.risks" rows="3" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                        <div>
                            <label for="g-diaspora" class="block text-xs font-medium mb-1">Programmes diaspora</label>
                            <textarea id="g-diaspora" name="diaspora_programs" v-model="form.diaspora_programs" rows="3" maxlength="20000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                    </fieldset>

                <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
            </form>

            <template #footer="{ close }">
                <button type="button" @click="close"
                    class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    Annuler
                </button>
                <button type="submit" form="country-guide-form" :disabled="saving"
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
import { computed, onMounted, reactive, ref } from 'vue';
import Modal from '../../components/Modal.vue';

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', sort: 'country' });

const showModal = ref(false);
const emptyForm = () => ({
    id: null,
    country: '', country_code: '', flag: '', currency: 'XOF',
    official_language: 'Français',
    population: 0, gdp: 0, gdp_growth: 0, remittances_gdp: 0,
    ease_of_business_score: 0,
    key_sectors: [],
    legal_framework: '', taxation: '', investment_incentives: '',
    risks: '', opportunities: '', diaspora_programs: '',
    investment_agency: '', investment_agency_url: '',
});
const form = reactive(emptyForm());
const keySectorsInput = computed({
    get: () => Array.isArray(form.key_sectors) ? form.key_sectors.join(', ') : '',
    set: (v) => { form.key_sectors = String(v).split(',').map(s => s.trim()).filter(Boolean); },
});
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

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
        const { data } = await window.axios.get('/api/admin/country-guides', { params });
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

async function openEdit(g) {
    formError.value = '';
    try {
        // Fetch full record so long text fields (legal_framework, taxation…)
        // come through.
        const { data } = await window.axios.get(`/api/admin/country-guides/${g.id}`);
        Object.assign(form, emptyForm(), data.data, {
            key_sectors: Array.isArray(data.data?.key_sectors) ? data.data.key_sectors : [],
        });
    } catch {
        Object.assign(form, emptyForm(), g, {
            key_sectors: Array.isArray(g.key_sectors) ? g.key_sectors : [],
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
        payload.country_code = (payload.country_code || '').toUpperCase();
        payload.currency = (payload.currency || '').toUpperCase() || null;
        // Strip empty strings → null so Laravel `nullable` rules behave.
        Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null; });

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/country-guides/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'Guide mis à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/country-guides', payload);
            await load();
            showToast(data.message || 'Guide créé.', 'success');
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

async function destroyGuide(g) {
    if (!confirm(`Supprimer le guide pays « ${g.country} » ?`)) return;
    deletingId.value = g.id;
    try {
        await window.axios.delete(`/api/admin/country-guides/${g.id}`);
        items.value = items.value.filter(x => x.id !== g.id);
        showToast(`Guide « ${g.country} » supprimé.`, 'success');
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
