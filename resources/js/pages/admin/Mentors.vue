<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-6">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Mentors</h1>
            <p class="mt-1 text-slate-600 dark:text-slate-300 text-sm">
                Gérer les utilisateurs ayant le rôle <code class="px-1 rounded bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">mentor</code> — retirer le statut, supprimer le compte, voir les mentorats.
            </p>
        </header>

        <!-- Filters -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-4 mb-6 flex flex-wrap gap-3">
            <input v-model="filters.search" @input="debouncedLoad" type="search" placeholder="Rechercher un mentor…"
                class="flex-1 min-w-[220px] px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm" />
            <select v-model="filters.kyc_level" @change="load"
                class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm">
                <option value="">Tous niveaux KYC</option>
                <option value="basic">Basique</option>
                <option value="verified">Vérifié</option>
                <option value="certified">Certifié</option>
            </select>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500 dark:text-slate-400">Chargement…</div>
        <div v-else-if="!mentors.length" class="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700">
            <div class="text-5xl mb-3">🎓</div>
            <p class="text-slate-600 dark:text-slate-300">Aucun mentor trouvé.</p>
        </div>

        <div v-else class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Mentor</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">KYC</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Mentorats actifs</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Terminés</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                    <tr v-for="m in mentors" :key="m.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-violet-500 flex items-center justify-center text-white text-xs font-bold">
                                    {{ m.name?.charAt(0)?.toUpperCase() || '?' }}
                                </div>
                                <div>
                                    <div class="font-semibold">{{ m.name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ m.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ m.country || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="kycClass(m.kyc_level)">
                                {{ KYC_LABELS[m.kyc_level] || m.kyc_level }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold">{{ m.mentorships_active_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ m.mentorships_completed_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                <router-link :to="`/mentorat/mentors/${m.id}`"
                                    class="text-xs font-semibold text-violet-700 dark:text-violet-400 hover:underline">
                                    Profil
                                </router-link>
                                <button @click="demote(m)" :disabled="busyId === m.id"
                                    class="text-xs font-semibold text-amber-700 dark:text-amber-400 hover:underline disabled:opacity-50"
                                    title="Retirer uniquement le rôle mentor (compte conservé)">
                                    {{ busyId === m.id && lastAction === 'demote' ? '…' : 'Retirer rôle' }}
                                </button>
                                <button @click="hardDelete(m)" :disabled="busyId === m.id"
                                    class="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline disabled:opacity-50">
                                    {{ busyId === m.id && lastAction === 'delete' ? '…' : 'Supprimer' }}
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
                    ? 'bg-violet-600 border-violet-600 text-white'
                    : 'border-slate-200 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700'">
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

const mentors = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);
const filters = reactive({ search: '', kyc_level: '' });

const busyId = ref(null);
const lastAction = ref(null);
const toast = ref(null);

const KYC_LABELS = { none: 'Aucun', basic: 'Basique', verified: 'Vérifié', certified: 'Certifié' };
function kycClass(level) {
    return ({
        none: 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
        basic: 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300',
        verified: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        certified: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    })[level] || 'bg-slate-100 dark:bg-slate-700';
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
        const params = { ...filters, role: 'mentor', page: page.value };
        Object.keys(params).forEach(k => (params[k] === '' || params[k] == null) && delete params[k]);
        const { data } = await window.axios.get('/api/admin/users', { params });
        mentors.value = data.data || [];
        meta.value = { last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

async function demote(m) {
    if (!confirm(`Retirer le rôle mentor pour « ${m.name} » ?\n\nLes mentorats en cours/acceptés seront annulés. Le compte est conservé.`)) return;
    busyId.value = m.id;
    lastAction.value = 'demote';
    try {
        const { data } = await window.axios.delete(`/api/admin/mentors/${m.id}`);
        showToast(data.message || 'Rôle mentor retiré.', 'success');
        await load();
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors du retrait.', 'error');
    } finally {
        busyId.value = null;
        lastAction.value = null;
    }
}

async function hardDelete(m) {
    if (!confirm(`SUPPRIMER DÉFINITIVEMENT « ${m.name} » ?\n\nLes mentorats, profils et toutes les données associées seront perdues.`)) return;
    busyId.value = m.id;
    lastAction.value = 'delete';
    try {
        await window.axios.delete(`/api/admin/mentors/${m.id}`, { params: { delete_account: true } });
        showToast(`Mentor « ${m.name} » supprimé.`, 'success');
        mentors.value = mentors.value.filter(x => x.id !== m.id);
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur.', 'error');
    } finally {
        busyId.value = null;
        lastAction.value = null;
    }
}

function showToast(message, tone = 'success') {
    toast.value = { message, tone };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(load);
</script>
