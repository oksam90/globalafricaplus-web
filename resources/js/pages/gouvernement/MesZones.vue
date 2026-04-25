<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes Zones Economiques</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Gérez les ZES de votre territoire.</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/gouvernement" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:underline">Portail public →</router-link>
                <button @click="openCreate" class="px-4 py-2 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">+ Nouvelle zone</button>
            </div>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="zones.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
            Aucune zone économique enregistrée.
        </div>
        <div v-else class="grid md:grid-cols-2 gap-6">
            <div v-for="z in zones" :key="z.id" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                        :class="z.status === 'active' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : z.status === 'planned' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                        {{ { active: 'Active', planned: 'En projet', closed: 'Fermée' }[z.status] || z.status }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ z.country }} · {{ z.region }}</span>
                </div>
                <h3 class="font-bold text-lg">{{ z.name }}</h3>
                <p v-if="z.description" class="text-sm text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ z.description }}</p>
                <div v-if="z.area_hectares" class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ z.area_hectares }} ha</div>
                <div v-if="z.sectors?.length" class="flex flex-wrap gap-1 mt-2">
                    <span v-for="s in z.sectors" :key="s" class="text-[10px] px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 font-medium">{{ s }}</span>
                </div>
                <div v-if="z.incentives?.length" class="mt-2">
                    <div v-for="inc in z.incentives.slice(0,3)" :key="inc" class="text-xs text-emerald-700 dark:text-emerald-400">✓ {{ inc }}</div>
                </div>
                <div class="flex gap-3 mt-4">
                    <button @click="openEdit(z)" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Modifier</button>
                    <button @click="deleteZone(z.id)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">Supprimer</button>
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">{{ editingId ? 'Modifier la zone' : 'Nouvelle zone' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-200 text-xl">&times;</button>
                </div>
                <form @submit.prevent="saveZone" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nom *</label>
                        <input v-model="form.name" type="text" required class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Pays *</label>
                            <input v-model="form.country" type="text" required class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Région</label>
                            <input v-model="form.region" type="text" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Superficie (ha)</label>
                            <input v-model.number="form.area_hectares" type="number" min="0" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Statut</label>
                            <select v-model="form.status" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                                <option value="active">Active</option>
                                <option value="planned">En projet</option>
                                <option value="closed">Fermée</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Site web</label>
                        <input v-model="form.website" type="url" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email contact</label>
                        <input v-model="form.contact_email" type="email" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm" />
                    </div>
                    <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
                    <button type="submit" :disabled="saving"
                        class="w-full py-2.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm disabled:opacity-50">
                        {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';

const zones = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editingId = ref(null);
const form = reactive({ name: '', country: '', region: '', description: '', area_hectares: null, status: 'active', website: '', contact_email: '' });
const saving = ref(false);
const formError = ref('');

function openCreate() {
    editingId.value = null;
    Object.keys(form).forEach(k => form[k] = k === 'status' ? 'active' : (typeof form[k] === 'number' ? null : ''));
    showModal.value = true;
}
function openEdit(z) {
    editingId.value = z.id;
    Object.assign(form, { name: z.name, country: z.country, region: z.region || '', description: z.description || '', area_hectares: z.area_hectares, status: z.status, website: z.website || '', contact_email: z.contact_email || '' });
    showModal.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/gouvernement/mes-zones');
        zones.value = data || [];
    } finally { loading.value = false; }
}

async function saveZone() {
    saving.value = true; formError.value = '';
    try {
        if (editingId.value) {
            await window.axios.patch(`/api/gouvernement/zones/${editingId.value}`, form);
        } else {
            await window.axios.post('/api/gouvernement/zones', form);
        }
        showModal.value = false;
        await load();
    } catch (e) {
        formError.value = e?.response?.data?.message || 'Erreur.';
    } finally { saving.value = false; }
}

async function deleteZone(id) {
    if (!confirm('Supprimer cette zone ?')) return;
    try { await window.axios.delete(`/api/gouvernement/zones/${id}`); await load(); }
    catch (e) { alert(e?.response?.data?.message || 'Erreur.'); }
}

onMounted(load);
</script>
