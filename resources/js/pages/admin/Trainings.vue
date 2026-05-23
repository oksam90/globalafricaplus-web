<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6 flex items-end justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Formations</h1>
                <p class="mt-1 text-slate-600 dark:text-slate-300 text-sm">
                    Catalogue complet (publiées et brouillons). La suppression révoque l'accès des apprenants actifs.
                </p>
            </div>
            <button @click="openCreate"
                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                + Nouvelle formation
            </button>
        </header>

        <!-- Filters -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-4 mb-6 flex flex-wrap gap-3">
            <input v-model="filters.q" @input="debouncedLoad" type="search" placeholder="Rechercher une formation…"
                class="flex-1 min-w-[220px] px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm" />
            <select v-model="filters.category" @change="load"
                class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm">
                <option value="">Toutes catégories</option>
                <option value="entrepreneurship">Entrepreneuriat</option>
                <option value="finance">Finance</option>
                <option value="tech">Tech</option>
                <option value="leadership">Leadership</option>
                <option value="marketing">Marketing</option>
            </select>
            <select v-model="filters.sort" @change="load"
                class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm">
                <option value="recent">Récents</option>
                <option value="popular">Populaires</option>
                <option value="price">Prix décroissant</option>
            </select>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500 dark:text-slate-400">Chargement…</div>
        <div v-else-if="!trainings.length" class="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700">
            <div class="text-5xl mb-3">📚</div>
            <p class="text-slate-600 dark:text-slate-300">Aucune formation trouvée.</p>
        </div>

        <div v-else class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Formation</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Catégorie</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Formateur</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Prix</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Apprenants actifs</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Total</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Publié</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="t in trainings" :key="t.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-slate-100 line-clamp-1">{{ t.title }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.duration_minutes }} min · niveau {{ t.level }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                {{ t.category || '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 text-xs">
                            {{ t.instructor?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ formatPrice(t.price, t.currency) }}</td>
                        <td class="px-4 py-3 text-center font-semibold"
                            :class="t.active_purchases_count > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400'">
                            {{ t.active_purchases_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ t.purchases_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="t.is_published ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                                {{ t.is_published ? 'Oui' : 'Brouillon' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <router-link :to="`/formations/${t.slug}`" target="_blank"
                                    class="text-xs font-semibold text-violet-700 dark:text-violet-400 hover:underline">
                                    Voir
                                </router-link>
                                <button @click="openEdit(t)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    Modifier
                                </button>
                                <button @click="deleteTraining(t)" :disabled="busyId === t.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ busyId === t.id ? '…' : 'Supprimer' }}
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
                :class="n === meta.current_page
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : 'border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700'">
                {{ n }}
            </button>
        </div>

        <!-- Create / edit modal -->
        <Modal v-model="showModal" size="3xl"
            :title="form.id ? 'Modifier la formation' : 'Nouvelle formation'">
            <form id="training-form" @submit.prevent="save" class="space-y-5">
                    <!-- Identité -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Identité</legend>
                        <div>
                            <label for="t-title" class="block text-xs font-medium mb-1">Titre *</label>
                            <input id="t-title" name="title" v-model="form.title" required type="text" maxlength="200"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div>
                            <label for="t-slug" class="block text-xs font-medium mb-1">Slug
                                <span class="text-xs text-slate-400">(laissé vide = généré depuis le titre)</span>
                            </label>
                            <input id="t-slug" name="slug" v-model="form.slug" type="text" maxlength="220"
                                pattern="[a-zA-Z0-9_-]+"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono" />
                        </div>
                        <div>
                            <label for="t-summary" class="block text-xs font-medium mb-1">Résumé</label>
                            <textarea id="t-summary" name="summary" v-model="form.summary" rows="2" maxlength="1000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                        <div>
                            <label for="t-description" class="block text-xs font-medium mb-1">Description complète</label>
                            <textarea id="t-description" name="description" v-model="form.description" rows="5" maxlength="30000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
                        </div>
                    </fieldset>

                    <!-- Catégorisation -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Catégorisation</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="t-category" class="block text-xs font-medium mb-1">Catégorie</label>
                                <select id="t-category" name="category" v-model="form.category"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                    <option value="">— Aucune —</option>
                                    <option value="entrepreneurship">Entrepreneuriat</option>
                                    <option value="finance">Finance</option>
                                    <option value="tech">Tech</option>
                                    <option value="leadership">Leadership</option>
                                    <option value="marketing">Marketing</option>
                                </select>
                            </div>
                            <div>
                                <label for="t-level" class="block text-xs font-medium mb-1">Niveau</label>
                                <select id="t-level" name="level" v-model="form.level"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                                    <option value="">—</option>
                                    <option value="beginner">Débutant</option>
                                    <option value="intermediate">Intermédiaire</option>
                                    <option value="advanced">Avancé</option>
                                </select>
                            </div>
                            <div>
                                <label for="t-duration" class="block text-xs font-medium mb-1">Durée (minutes)</label>
                                <input id="t-duration" name="duration_minutes" v-model.number="form.duration_minutes" type="number" min="1" max="65535"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label for="t-curriculum" class="block text-xs font-medium mb-1">Programme / plan
                                <span class="text-slate-400">(une ligne par module)</span>
                            </label>
                            <textarea id="t-curriculum" name="curriculum" v-model="curriculumInput" rows="4"
                                placeholder="Module 1 — Introduction&#10;Module 2 — Études de cas"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
                        </div>
                    </fieldset>

                    <!-- Médias -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Médias et accès</legend>
                        <div>
                            <label for="t-cover" class="block text-xs font-medium mb-1">URL image de couverture</label>
                            <input id="t-cover" name="cover_image" v-model="form.cover_image" type="text" maxlength="500"
                                placeholder="https://… ou /storage/…"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div>
                            <label for="t-preview" class="block text-xs font-medium mb-1">URL vidéo d'aperçu</label>
                            <input id="t-preview" name="video_preview_url" v-model="form.video_preview_url" type="url" maxlength="500"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                        <div>
                            <label for="t-content" class="block text-xs font-medium mb-1">URL contenu (débloquée après paiement)</label>
                            <input id="t-content" name="content_url" v-model="form.content_url" type="url" maxlength="500"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                        </div>
                    </fieldset>

                    <!-- Tarification -->
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Tarification & publication</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="t-price" class="block text-xs font-medium mb-1">Prix *</label>
                                <input id="t-price" name="price" v-model.number="form.price" required type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm" />
                            </div>
                            <div>
                                <label for="t-currency" class="block text-xs font-medium mb-1">Devise</label>
                                <input id="t-currency" name="currency" v-model="form.currency" type="text" minlength="3" maxlength="3"
                                    pattern="[A-Za-z]{3}" style="text-transform:uppercase"
                                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-mono uppercase" />
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="form.is_published" type="checkbox" class="accent-emerald-600" />
                                    Publier
                                </label>
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
                <button type="submit" form="training-form" :disabled="saving"
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
import { computed, onMounted, reactive, ref } from 'vue';
import Modal from '../../components/Modal.vue';

const trainings = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);
const filters = reactive({ q: '', category: '', sort: 'recent' });
const busyId = ref(null);
const toast = ref(null);

// Create / edit modal state.
const showModal = ref(false);
const emptyForm = () => ({
    id: null,
    title: '', slug: '', summary: '', description: '',
    cover_image: '', video_preview_url: '', content_url: '',
    category: '', level: '', duration_minutes: null,
    curriculum: [],
    price: 0, currency: 'EUR',
    is_published: false,
});
const form = reactive(emptyForm());
const curriculumInput = computed({
    get: () => Array.isArray(form.curriculum) ? form.curriculum.join('\n') : '',
    set: (v) => { form.curriculum = String(v).split('\n').map(s => s.trim()).filter(Boolean); },
});
const saving = ref(false);
const formError = ref('');

function formatPrice(amount, currency) {
    const cur = (currency || 'EUR').toUpperCase();
    try { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: cur === 'XOF' ? 0 : 2 }).format(parseFloat(amount) || 0); }
    catch { return `${amount} ${cur}`; }
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
        const { data } = await window.axios.get('/api/admin/trainings', { params });
        trainings.value = data.data || [];
        meta.value = { last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

async function deleteTraining(t) {
    const active = t.active_purchases_count || 0;
    const warn = active > 0
        ? `\n\n⚠ ${active} apprenant(s) actif(s) perdront l'accès. Confirmer ?`
        : '';
    if (!confirm(`SUPPRIMER « ${t.title} » ?${warn}`)) return;

    busyId.value = t.id;
    try {
        await window.axios.delete(`/api/admin/trainings/${t.id}`, {
            params: active > 0 ? { force: true } : {},
        });
        showToast(`Formation « ${t.title} » supprimée.`, 'success');
        trainings.value = trainings.value.filter(x => x.id !== t.id);
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de la suppression.', 'error');
    } finally {
        busyId.value = null;
    }
}

function showToast(message, tone = 'success') {
    toast.value = { message, tone };
    setTimeout(() => { toast.value = null; }, 4000);
}

function openCreate() {
    Object.assign(form, emptyForm());
    formError.value = '';
    showModal.value = true;
}

async function openEdit(t) {
    formError.value = '';
    try {
        const { data } = await window.axios.get(`/api/admin/trainings/${t.id}`);
        Object.assign(form, emptyForm(), data.data, {
            curriculum: Array.isArray(data.data?.curriculum) ? data.data.curriculum : [],
            is_published: !!data.data?.is_published,
        });
    } catch {
        Object.assign(form, emptyForm(), t, {
            curriculum: Array.isArray(t.curriculum) ? t.curriculum : [],
            is_published: !!t.is_published,
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
        // is_published can't be null — coerce back to bool.
        payload.is_published = !!form.is_published;

        if (form.id) {
            const { data } = await window.axios.patch(`/api/admin/trainings/${form.id}`, payload);
            const idx = trainings.value.findIndex(x => x.id === form.id);
            if (idx !== -1) Object.assign(trainings.value[idx], data.data);
            showToast(data.message || 'Formation mise à jour.', 'success');
        } else {
            const { data } = await window.axios.post('/api/admin/trainings', payload);
            await load();
            showToast(data.message || 'Formation créée.', 'success');
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

onMounted(load);
</script>
