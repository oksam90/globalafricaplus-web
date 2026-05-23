<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Gestion des secteurs</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} secteur(s) enregistré(s).
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouveau secteur
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-3 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="sector-search" name="sector-search"
                    type="search" placeholder="Rechercher nom ou slug…"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm md:col-span-2" />
                <select v-model="filters.sort" @change="load"
                    id="sector-sort" name="sector-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="name">Nom A-Z</option>
                    <option value="recent">Plus récents</option>
                    <option value="projects">Plus de projets</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun secteur. Cliquez sur « Nouveau secteur » pour en créer un.
        </div>
        <div v-else class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Secteur</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Slug</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Projets</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Sous-catégories</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="s in items" :key="s.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-base font-bold"
                                    :style="{ backgroundColor: s.color || '#94a3b8' }">
                                    {{ s.icon || s.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="font-semibold">{{ s.name }}</div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500 dark:text-slate-400">{{ s.slug }}</td>
                        <td class="px-4 py-3 text-right">{{ s.projects_count }}</td>
                        <td class="px-4 py-3 text-right">{{ s.sub_categories_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <button @click="openEdit(s)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    Modifier
                                </button>
                                <button @click="destroySector(s)" :disabled="deletingId === s.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ deletingId === s.id ? '…' : 'Supprimer' }}
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
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold">
                        {{ form.id ? 'Modifier le secteur' : 'Nouveau secteur' }}
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 dark:text-slate-500 text-xl">&times;</button>
                </div>

                <form @submit.prevent="save" class="space-y-4">
                    <div>
                        <label for="sector-name" class="block text-sm font-medium mb-1">Nom *</label>
                        <input id="sector-name" name="name" v-model="form.name" required type="text" maxlength="100"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="sector-slug" class="block text-sm font-medium mb-1">Slug
                            <span class="text-xs text-slate-400">(laissé vide = généré depuis le nom)</span>
                        </label>
                        <input id="sector-slug" name="slug" v-model="form.slug" type="text" maxlength="120"
                            pattern="[a-zA-Z0-9_-]+"
                            placeholder="ex. agro-tech"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="sector-icon" class="block text-sm font-medium mb-1">Icône / emoji</label>
                            <input id="sector-icon" name="icon" v-model="form.icon" type="text" maxlength="50"
                                placeholder="🌾"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div>
                            <label for="sector-color" class="block text-sm font-medium mb-1">Couleur</label>
                            <div class="flex items-center gap-2">
                                <input id="sector-color" name="color" v-model="form.color" type="color"
                                    class="h-9 w-12 rounded border border-slate-200 dark:border-slate-700 bg-transparent" />
                                <input v-model="form.color" type="text" maxlength="20" placeholder="#10b981"
                                    aria-label="Code couleur hex"
                                    class="flex-1 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono" />
                            </div>
                        </div>
                    </div>

                    <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>

                    <div class="flex gap-3 pt-2">
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

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', sort: 'name' });

const showModal = ref(false);
const form = reactive({ id: null, name: '', slug: '', icon: '', color: '#10b981' });
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
        const { data } = await window.axios.get('/api/admin/sectors', { params });
        items.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, { id: null, name: '', slug: '', icon: '', color: '#10b981' });
    formError.value = '';
    showModal.value = true;
}

function openEdit(s) {
    Object.assign(form, {
        id: s.id,
        name: s.name,
        slug: s.slug,
        icon: s.icon || '',
        color: s.color || '#10b981',
    });
    formError.value = '';
    showModal.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { name: form.name, icon: form.icon || null, color: form.color || null };
        if (form.slug) payload.slug = form.slug;

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/sectors/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'Secteur mis à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/sectors', payload);
            await load();
            showToast(data.message || 'Secteur créé.', 'success');
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

async function destroySector(s) {
    const msg = s.projects_count > 0
        ? `Supprimer « ${s.name} » ? Ce secteur contient ${s.projects_count} projet(s) qui seront détachés.`
        : `Supprimer « ${s.name} » ?`;
    if (!confirm(msg)) return;

    deletingId.value = s.id;
    try {
        const params = s.projects_count > 0 ? { force: true } : {};
        await window.axios.delete(`/api/admin/sectors/${s.id}`, { params });
        items.value = items.value.filter(x => x.id !== s.id);
        showToast(`Secteur « ${s.name} » supprimé.`, 'success');
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
