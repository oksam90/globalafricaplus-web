<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Témoignages</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    {{ meta.total || 0 }} témoignage(s) — visibles dans la section témoignages du site public.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                    ← Dashboard admin
                </router-link>
                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + Nouveau témoignage
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.q" @input="debouncedLoad"
                    id="t-search" name="t-search"
                    type="search" placeholder="Rechercher auteur ou contenu…"
                    class="md:col-span-2 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                <select v-model="filters.featured" @change="load"
                    id="t-featured" name="t-featured"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous</option>
                    <option value="1">Mis en avant</option>
                    <option value="0">Standards</option>
                </select>
                <select v-model="filters.sort" @change="load"
                    id="t-sort" name="t-sort"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="sort_order">Ordre d'affichage</option>
                    <option value="recent">Plus récents</option>
                    <option value="rating">Note décroissante</option>
                </select>
            </div>
        </div>

        <!-- Grid of testimonial cards -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun témoignage. Cliquez sur « Nouveau témoignage » pour en créer un.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <article v-for="t in items" :key="t.id"
                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5"
                :class="{ 'opacity-60': !t.is_active, 'ring-2 ring-amber-300': t.is_featured }">
                <header class="flex items-center gap-3 mb-3">
                    <img v-if="t.author_avatar" :src="t.author_avatar" :alt="t.author_name"
                        class="w-12 h-12 rounded-full object-cover bg-slate-200" />
                    <div v-else class="w-12 h-12 rounded-full bg-slate-300 dark:bg-slate-600 flex items-center justify-center text-white font-bold">
                        {{ t.author_name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold truncate">{{ t.author_name }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                            {{ t.author_role }}
                            <span v-if="t.author_country"> · {{ t.author_country }}</span>
                        </div>
                    </div>
                    <div class="text-amber-500 text-sm whitespace-nowrap">
                        {{ '★'.repeat(t.rating || 0) + '☆'.repeat(5 - (t.rating || 0)) }}
                    </div>
                </header>
                <p class="text-sm text-slate-700 dark:text-slate-300 italic line-clamp-4">"{{ t.content }}"</p>
                <footer class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center gap-3 text-xs">
                    <span v-if="t.is_featured" class="font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                        ★ Mis en avant
                    </span>
                    <span v-if="!t.is_active" class="font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500">
                        Inactif
                    </span>
                    <span class="text-slate-400">Ordre #{{ t.sort_order ?? 0 }}</span>
                    <div class="ml-auto flex items-center gap-3">
                        <button @click="openEdit(t)"
                            class="font-semibold text-red-600 dark:text-red-400 hover:underline">
                            Modifier
                        </button>
                        <button @click="destroyTestimonial(t)" :disabled="deletingId === t.id"
                            class="font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                            {{ deletingId === t.id ? '…' : 'Supprimer' }}
                        </button>
                    </div>
                </footer>
            </article>
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
        <Modal v-model="showModal" size="2xl"
            :title="form.id ? 'Modifier le témoignage' : 'Nouveau témoignage'">
            <form id="testimonial-form" @submit.prevent="save" class="space-y-4">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Auteur</legend>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="ta-name" class="block text-xs font-medium mb-1">Nom complet *</label>
                                <input id="ta-name" name="author_name" v-model="form.author_name" required type="text" maxlength="150"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="ta-role" class="block text-xs font-medium mb-1">Fonction *</label>
                                <input id="ta-role" name="author_role" v-model="form.author_role" required type="text" maxlength="150"
                                    placeholder="ex. Entrepreneur, Sénégal"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="ta-country" class="block text-xs font-medium mb-1">Pays</label>
                                <input id="ta-country" name="author_country" v-model="form.author_country" type="text" maxlength="80"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="ta-project" class="block text-xs font-medium mb-1">Projet associé (libre)</label>
                                <input id="ta-project" name="project_title" v-model="form.project_title" type="text" maxlength="200"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium mb-1">Photo / avatar</label>
                            <div class="flex items-center gap-3 mb-2">
                                <input id="ta-avatar-file" name="avatar_file" type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    @change="onAvatarSelected"
                                    class="flex-1 text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 dark:file:bg-emerald-900/40 file:text-emerald-700 dark:file:text-emerald-300 hover:file:bg-emerald-100 dark:hover:file:bg-emerald-900/60" />
                                <span v-if="uploading" class="text-xs text-slate-500">Téléversement…</span>
                                <img v-if="form.author_avatar" :src="form.author_avatar" alt=""
                                    class="w-12 h-12 rounded-full object-cover bg-slate-200" />
                            </div>
                            <p v-if="uploadError" class="text-xs text-rose-600 dark:text-rose-400 mb-2">{{ uploadError }}</p>
                            <input id="ta-avatar-url" name="author_avatar" v-model="form.author_avatar" type="text" maxlength="500"
                                placeholder="URL — rempli automatiquement après téléversement"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Témoignage</legend>
                        <div>
                            <label for="ta-content" class="block text-xs font-medium mb-1">Contenu *</label>
                            <textarea id="ta-content" name="content" v-model="form.content" required rows="5" maxlength="3000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                            <p class="text-xs text-slate-400 mt-1">{{ form.content?.length || 0 }}/3000</p>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label for="ta-rating" class="block text-xs font-medium mb-1">Note (1-5)</label>
                                <select id="ta-rating" name="rating" v-model.number="form.rating"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                    <option v-for="n in 5" :key="n" :value="n">{{ '★'.repeat(n) }} ({{ n }})</option>
                                </select>
                            </div>
                            <div>
                                <label for="ta-order" class="block text-xs font-medium mb-1">Ordre d'affichage</label>
                                <input id="ta-order" name="sort_order" v-model.number="form.sort_order" type="number" min="0" max="65535"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                        <div class="flex items-center gap-6 pt-1">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.is_featured" type="checkbox" class="accent-amber-500" />
                                ★ Mettre en avant
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.is_active" type="checkbox" class="accent-emerald-600" />
                                Actif (visible sur le site)
                            </label>
                        </div>
                    </fieldset>

                <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
            </form>

            <template #footer="{ close }">
                <button type="button" @click="close"
                    class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    Annuler
                </button>
                <button type="submit" form="testimonial-form" :disabled="saving"
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

const items = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ q: '', featured: '', sort: 'sort_order' });

const showModal = ref(false);
const emptyForm = () => ({
    id: null,
    author_name: '', author_role: '', author_avatar: '', author_country: '',
    content: '', rating: 5, project_title: '',
    is_featured: false, is_active: true, sort_order: 0,
});
const form = reactive(emptyForm());
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

const uploading = ref(false);
const uploadError = ref('');

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
        const { data } = await window.axios.get('/api/admin/testimonials', { params });
        items.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, emptyForm());
    formError.value = '';
    uploadError.value = '';
    showModal.value = true;
}

async function openEdit(t) {
    formError.value = '';
    uploadError.value = '';
    try {
        const { data } = await window.axios.get(`/api/admin/testimonials/${t.id}`);
        Object.assign(form, emptyForm(), data.data, {
            is_featured: !!data.data.is_featured,
            is_active:   !!data.data.is_active,
        });
    } catch {
        Object.assign(form, emptyForm(), t, {
            is_featured: !!t.is_featured,
            is_active:   !!t.is_active,
        });
    }
    showModal.value = true;
}

async function onAvatarSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadError.value = '';
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('folder', 'testimonials');
        const { data } = await window.axios.post('/api/admin/uploads/image', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.author_avatar = data.url;
    } catch (e) {
        uploadError.value = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})[0]?.[0]
            || 'Téléversement impossible.';
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form };
        delete payload.id;
        Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null; });
        payload.is_featured = !!form.is_featured;
        payload.is_active   = !!form.is_active;

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/testimonials/${form.id}`, payload);
            const idx = items.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(items.value[idx], data.data);
            showToast(data.message || 'Témoignage mis à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/testimonials', payload);
            await load();
            showToast(data.message || 'Témoignage créé.', 'success');
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

async function destroyTestimonial(t) {
    if (!confirm(`Supprimer le témoignage de « ${t.author_name} » ?`)) return;
    deletingId.value = t.id;
    try {
        await window.axios.delete(`/api/admin/testimonials/${t.id}`);
        items.value = items.value.filter(x => x.id !== t.id);
        showToast(`Témoignage de « ${t.author_name} » supprimé.`, 'success');
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
