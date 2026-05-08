<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Formations</h1>
            <p class="mt-1 text-slate-600 dark:text-slate-300 text-sm">
                Catalogue complet (publiées et brouillons). La suppression révoque l'accès des apprenants actifs.
            </p>
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

const trainings = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);
const filters = reactive({ q: '', category: '', sort: 'recent' });
const busyId = ref(null);
const toast = ref(null);

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

onMounted(load);
</script>
