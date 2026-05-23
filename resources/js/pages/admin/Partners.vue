<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Partenaires</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} partenaire(s) — section « Nos Partenaires » du site public.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouveau partenaire
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="p-search" name="p-search"
                    type="search" placeholder="Rechercher un partenaire…"
                    class="md:col-span-2 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                <select v-model="filters.type" @change="load"
                    id="p-type" name="p-type"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous types</option>
                    <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <select v-model="filters.sort" @change="load"
                    id="p-sort" name="p-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="sort_order">Ordre d'affichage</option>
                    <option value="name">Nom A-Z</option>
                    <option value="recent">Plus récents</option>
                </select>
            </div>
        </div>

        <!-- Grid of partner cards -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun partenaire. Cliquez sur « Nouveau partenaire » pour en créer un.
        </div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="p in items" :key="p.id"
                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 flex flex-col gap-3"
                :class="{ 'opacity-60': !p.is_active }">
                <div class="aspect-[4/3] bg-slate-50 dark:bg-slate-900/50 rounded-lg flex items-center justify-center overflow-hidden">
                    <img v-if="p.logo_url" :src="p.logo_url" :alt="p.name"
                        class="max-w-full max-h-full object-contain p-3" />
                    <span v-else class="text-slate-400 text-xs">Pas de logo</span>
                </div>
                <div class="flex-1">
                    <div class="font-semibold text-sm line-clamp-1">{{ p.name }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                            {{ typeLabel(p.type) }}
                        </span>
                        <span class="text-[10px] text-slate-400">Ordre #{{ p.sort_order ?? 0 }}</span>
                        <span v-if="!p.is_active" class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                            Inactif
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <button @click="openEdit(p)"
                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                        Modifier
                    </button>
                    <button @click="destroyPartner(p)" :disabled="deletingId === p.id"
                        class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                        {{ deletingId === p.id ? '…' : 'Supprimer' }}
                    </button>
                    <a v-if="p.website" :href="p.website" target="_blank" rel="noopener"
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:underline ml-auto">
                        Visiter ↗
                    </a>
                </div>
            </div>
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
        <Modal v-model="showModal" size="xl"
            :title="form.id ? 'Modifier le partenaire' : 'Nouveau partenaire'">
            <form id="partner-form" @submit.prevent="save" class="space-y-4">
                    <div>
                        <label for="p-name" class="block text-xs font-medium mb-1">Nom *</label>
                        <input id="p-name" name="name" v-model="form.name" required type="text" maxlength="150"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="p-slug" class="block text-xs font-medium mb-1">Slug
                            <span class="text-slate-400">(laissé vide = généré)</span>
                        </label>
                        <input id="p-slug" name="slug" v-model="form.slug" type="text" maxlength="170"
                            pattern="[a-zA-Z0-9_-]+"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Logo *</label>

                        <!-- File picker — uploads to the server, fills logo_url on success. -->
                        <div class="flex items-center gap-2 mb-2">
                            <input id="p-logo-file" name="logo_file" type="file"
                                accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif"
                                @change="onLogoFileSelected"
                                class="flex-1 text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 dark:file:bg-emerald-900/40 file:text-emerald-700 dark:file:text-emerald-300 hover:file:bg-emerald-100 dark:hover:file:bg-emerald-900/60" />
                            <span v-if="uploading" class="text-xs text-slate-500">Téléversement…</span>
                        </div>
                        <p v-if="uploadError" class="text-xs text-rose-600 dark:text-rose-400 mb-2">{{ uploadError }}</p>

                        <!-- URL field — auto-filled by the picker, but the admin can also paste an external URL. -->
                        <label for="p-logo" class="block text-xs font-medium mb-1">URL du logo
                            <span class="text-slate-400">(rempli automatiquement après téléversement)</span>
                        </label>
                        <input id="p-logo" name="logo_url" v-model="form.logo_url" required type="text" maxlength="500"
                            placeholder="https://… ou /storage/partners/…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        <div v-if="form.logo_url" class="mt-2 h-20 bg-slate-50 dark:bg-slate-900/50 rounded-md flex items-center justify-center overflow-hidden">
                            <img :src="form.logo_url" alt="Aperçu" class="max-h-full max-w-full object-contain p-2" />
                        </div>
                    </div>
                    <div>
                        <label for="p-website" class="block text-xs font-medium mb-1">Site web</label>
                        <input id="p-website" name="website" v-model="form.website" type="url" maxlength="300"
                            placeholder="https://…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="p-desc" class="block text-xs font-medium mb-1">Description</label>
                        <textarea id="p-desc" name="description" v-model="form.description" rows="3" maxlength="2000"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label for="p-type-select" class="block text-xs font-medium mb-1">Type</label>
                            <select id="p-type-select" name="type" v-model="form.type"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="p-order" class="block text-xs font-medium mb-1">Ordre d'affichage</label>
                            <input id="p-order" name="sort_order" v-model.number="form.sort_order" type="number" min="0" max="65535"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm pt-1">
                        <input v-model="form.is_active" type="checkbox" class="accent-emerald-600" />
                        Actif (visible sur le site)
                    </label>

                <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
            </form>

            <template #footer="{ close }">
                <button type="button" @click="close"
                    class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    Annuler
                </button>
                <button type="submit" form="partner-form" :disabled="saving"
                    class="px-5 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm disabled:opacity-50">
                    {{ saving ? 'Enregistrement…' : (form.id ? 'Mettre à jour' : 'Créer') }}
                </button>
            </template>
        </Modal>

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

const TYPES = [
    { value: 'institutional', label: 'Institutionnel' },
    { value: 'financial',     label: 'Financier' },
    { value: 'tech',          label: 'Tech' },
    { value: 'ngo',           label: 'ONG' },
    { value: 'government',    label: 'Gouvernement' },
    { value: 'media',         label: 'Média' },
];
function typeLabel(v) { return TYPES.find(t => t.value === v)?.label || v || '—'; }

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', type: '', sort: 'sort_order' });

const showModal = ref(false);
const emptyForm = () => ({
    id: null, name: '', slug: '', logo_url: '', website: '', description: '',
    type: 'institutional', sort_order: 0, is_active: true,
});
const form = reactive(emptyForm());
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

const uploading = ref(false);
const uploadError = ref('');

async function onLogoFileSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadError.value = '';
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('folder', 'partners');
        const { data } = await window.axios.post('/api/admin/uploads/image', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.logo_url = data.url;
    } catch (e) {
        uploadError.value = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})[0]?.[0]
            || 'Téléversement impossible.';
    } finally {
        uploading.value = false;
        // Reset so re-selecting the same file fires @change again.
        event.target.value = '';
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
        const { data } = await window.axios.get('/api/admin/partners', { params });
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

async function openEdit(p) {
    formError.value = '';
    try {
        const { data } = await window.axios.get(`/api/admin/partners/${p.id}`);
        Object.assign(form, emptyForm(), data.data, { is_active: !!data.data.is_active });
    } catch {
        Object.assign(form, emptyForm(), p, { is_active: !!p.is_active });
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
        payload.is_active = !!form.is_active;

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/partners/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'Partenaire mis à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/partners', payload);
            await load();
            showToast(data.message || 'Partenaire créé.', 'success');
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

async function destroyPartner(p) {
    if (!confirm(`Supprimer le partenaire « ${p.name} » ?`)) return;
    deletingId.value = p.id;
    try {
        await window.axios.delete(`/api/admin/partners/${p.id}`);
        items.value = items.value.filter(x => x.id !== p.id);
        showToast(`Partenaire « ${p.name} » supprimé.`, 'success');
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
