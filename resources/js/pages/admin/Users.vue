<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Gestion des utilisateurs</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">{{ meta.total || 0 }} utilisateurs enregistrés.</p>
            </div>
            <router-link to="/dashboard" class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                ← Dashboard admin
            </router-link>
        </div>

        <!-- Filters -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-5 gap-3">
                <input v-model="filters.search" @input="debouncedLoad"
                    type="search" placeholder="Rechercher nom ou email…"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-red-400 focus:outline-none text-sm md:col-span-2" />

                <select v-model="filters.role" @change="load"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous les rôles</option>
                    <option v-for="r in availableRoles" :key="r" :value="r">{{ ROLE_LABELS[r] || r }}</option>
                </select>

                <select v-model="filters.kyc_level" @change="load"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous KYC</option>
                    <option value="none">Aucun</option>
                    <option value="basic">Basique</option>
                    <option value="verified">Vérifié</option>
                    <option value="certified">Certifié</option>
                </select>

                <select v-model="filters.sort" @change="load"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="recent">Plus récents</option>
                    <option value="oldest">Plus anciens</option>
                    <option value="name">Nom A-Z</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else>
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Utilisateur</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Rôles</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">KYC</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Inscrit le</th>
                            <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-slate-700/70">
                        <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                        :class="u.roles?.some(r => r.slug === 'admin') ? 'bg-red-500' : 'bg-slate-400 dark:bg-slate-600'">
                                        {{ u.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <div>
                                        <div class="font-semibold">{{ u.name }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ u.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="r in u.roles" :key="r.slug"
                                        class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                        :class="roleColorClass(r.slug)">
                                        {{ r.name || r.slug }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="kycClass(u.kyc_level)">
                                    {{ KYC_LABELS[u.kyc_level] || u.kyc_level }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ u.country || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ formatDate(u.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openUserModal(u)"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 hover:underline">
                                    Gérer
                                </button>
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
        </div>

        <!-- User edit modal -->
        <div v-if="editUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editUser = null">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold">Gérer : {{ editUser.name }}</h3>
                    <button @click="editUser = null" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 text-xl">&times;</button>
                </div>

                <div class="space-y-4">
                    <!-- Basic info -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Nom</label>
                        <input v-model="editForm.name" type="text" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input v-model="editForm.email" type="email" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Pays</label>
                            <input v-model="editForm.country" type="text" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Niveau KYC</label>
                            <select v-model="editForm.kyc_level" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                                <option value="none">Aucun</option>
                                <option value="basic">Basique</option>
                                <option value="verified">Vérifié</option>
                                <option value="certified">Certifié</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_diaspora" type="checkbox" class="accent-red-600" />
                        Membre de la diaspora
                    </label>

                    <!-- Roles management -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Rôles</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="r in availableRoles" :key="r"
                                @click="toggleRole(editUser.id, r)"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full border transition"
                                :class="editUser.roles?.some(ur => ur.slug === r)
                                    ? 'border-transparent text-white ' + roleColorBg(r)
                                    : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600'">
                                {{ ROLE_LABELS[r] || r }}
                                <span v-if="editUser.roles?.some(ur => ur.slug === r)" class="ml-1">✓</span>
                            </button>
                        </div>
                    </div>

                    <p v-if="editError" class="text-sm text-rose-600 dark:text-rose-400">{{ editError }}</p>
                    <p v-if="editSuccess" class="text-sm text-emerald-600 dark:text-emerald-400">{{ editSuccess }}</p>

                    <div class="flex gap-3">
                        <button @click="saveUser" :disabled="editSaving"
                            class="px-5 py-2 rounded-md bg-red-600 hover:bg-red-700 text-white font-semibold text-sm disabled:opacity-50">
                            {{ editSaving ? 'Enregistrement…' : 'Enregistrer' }}
                        </button>
                        <button @click="editUser = null"
                            class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-semibold">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';

const users = ref([]);
const meta = ref({});
const loading = ref(true);
const page = ref(1);

const filters = reactive({ search: '', role: '', kyc_level: '', sort: 'recent' });

const editUser = ref(null);
const editForm = reactive({});
const editSaving = ref(false);
const editError = ref('');
const editSuccess = ref('');

const availableRoles = ['entrepreneur', 'investor', 'mentor', 'jobseeker', 'government', 'admin'];

const ROLE_LABELS = {
    entrepreneur: 'Entrepreneur', investor: 'Investisseur', mentor: 'Mentor',
    jobseeker: "Chercheur d'emploi", government: 'Gouvernement', admin: 'Admin',
};

const KYC_LABELS = { none: 'Aucun', basic: 'Basique', verified: 'Vérifié', certified: 'Certifié' };

function roleColorClass(slug) {
    return {
        entrepreneur: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        investor: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        mentor: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
        jobseeker: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        government: 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300',
        admin: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    }[slug] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}

function roleColorBg(slug) {
    return {
        entrepreneur: 'bg-emerald-600',
        investor: 'bg-blue-600',
        mentor: 'bg-violet-600',
        jobseeker: 'bg-amber-600',
        government: 'bg-sky-600',
        admin: 'bg-red-600',
    }[slug] || 'bg-slate-600';
}

function kycClass(level) {
    return {
        none: 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
        basic: 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300',
        verified: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        certified: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    }[level] || 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
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
        const { data } = await window.axios.get('/api/admin/users', { params });
        users.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

function openUserModal(u) {
    editUser.value = u;
    Object.assign(editForm, {
        name: u.name,
        email: u.email,
        country: u.country,
        city: u.city,
        kyc_level: u.kyc_level || 'none',
        is_diaspora: !!u.is_diaspora,
    });
    editError.value = '';
    editSuccess.value = '';
}

async function saveUser() {
    editSaving.value = true;
    editError.value = '';
    editSuccess.value = '';
    try {
        const { data } = await window.axios.patch(`/api/admin/users/${editUser.value.id}`, editForm);
        editSuccess.value = data.message || 'Enregistré.';
        // Update in list
        const idx = users.value.findIndex(u => u.id === editUser.value.id);
        if (idx !== -1) Object.assign(users.value[idx], data.user);
        editUser.value = data.user;
    } catch (e) {
        editError.value = e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})[0]?.[0] || 'Erreur.';
    } finally {
        editSaving.value = false;
    }
}

async function toggleRole(userId, slug) {
    try {
        const { data } = await window.axios.post(`/api/admin/users/${userId}/toggle-role`, { slug });
        // Update user in list + modal
        const idx = users.value.findIndex(u => u.id === userId);
        if (idx !== -1) Object.assign(users.value[idx], data.user);
        editUser.value = data.user;
        editSuccess.value = data.message;
    } catch (e) {
        editError.value = e?.response?.data?.message || 'Erreur.';
    }
}

onMounted(load);
</script>
