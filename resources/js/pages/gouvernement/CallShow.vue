<template>
    <div v-if="loading" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Chargement…</div>
    <div v-else-if="!call" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Appel introuvable.</div>
    <div v-else>
        <!-- Header -->
        <section class="bg-gradient-to-br from-sky-50 via-blue-50 to-slate-50 dark:from-sky-950/40 dark:via-blue-950/30 dark:to-slate-900 border-b border-slate-100 dark:border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <router-link to="/gouvernement" class="text-sm text-sky-600 dark:text-sky-400 hover:underline mb-4 inline-block">
                    ← Tous les appels
                </router-link>

                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="statusClass(call.status)">
                        {{ statusLabel(call.status) }}
                    </span>
                    <span v-if="call.sector" class="text-xs px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 font-medium">{{ call.sector }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 text-[10px] font-bold">Officiel</span>
                </div>

                <h1 class="text-3xl md:text-4xl font-black tracking-tight">{{ call.title }}</h1>

                <div class="flex flex-wrap gap-5 mt-4 text-sm text-slate-600 dark:text-slate-300">
                    <span>{{ call.country }}<template v-if="call.geographic_zone"> — {{ call.geographic_zone }}</template></span>
                    <span v-if="call.budget" class="font-bold text-slate-800 dark:text-slate-200">Budget : {{ fmtMoney(call.budget) }} {{ call.currency }}</span>
                    <span v-if="call.opens_at">Ouvert : {{ formatDate(call.opens_at) }}</span>
                    <span v-if="call.closes_at" :class="isExpired ? 'text-rose-600 dark:text-rose-400 font-semibold' : ''">
                        Limite : {{ formatDate(call.closes_at) }} {{ isExpired ? '(expiré)' : '' }}
                    </span>
                    <span>{{ call.applications_count || 0 }} candidature(s)</span>
                </div>

                <div v-if="call.author" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    Publié par <strong class="text-sky-700 dark:text-sky-300">{{ call.author.name }}</strong>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Description -->
                    <div>
                        <h2 class="text-xl font-bold mb-3">Description</h2>
                        <div class="prose prose-slate max-w-none text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ call.description }}</div>
                    </div>

                    <!-- Eligibility -->
                    <div v-if="call.eligibility_criteria">
                        <h2 class="text-xl font-bold mb-3">Critères d'éligibilité</h2>
                        <div class="bg-sky-50 dark:bg-sky-900/30 border border-sky-100 dark:border-sky-900/50 rounded-xl p-5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ call.eligibility_criteria }}</div>
                    </div>

                    <!-- Required documents -->
                    <div v-if="call.required_documents">
                        <h2 class="text-xl font-bold mb-3">Documents requis</h2>
                        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-100 dark:border-amber-900/50 rounded-xl p-5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ call.required_documents }}</div>
                    </div>

                    <!-- Evaluation criteria -->
                    <div v-if="call.evaluation_criteria">
                        <h2 class="text-xl font-bold mb-3">Critères d'évaluation</h2>
                        <div class="bg-violet-50 dark:bg-violet-900/30 border border-violet-100 dark:border-violet-900/50 rounded-xl p-5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ call.evaluation_criteria }}</div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-5">
                    <!-- Apply CTA -->
                    <div v-if="call.status === 'open' && !isExpired" class="bg-sky-50 dark:bg-sky-900/30 border border-sky-100 dark:border-sky-900/50 rounded-2xl p-5">
                        <h3 class="font-bold text-sm mb-3">Candidater</h3>
                        <template v-if="auth.isAuthenticated">
                            <button v-if="!hasApplied" @click="showApplyModal = true"
                                class="w-full py-2.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm">
                                Soumettre ma candidature
                            </button>
                            <p v-else class="text-sm text-emerald-700 dark:text-emerald-400 font-semibold">✓ Vous avez déjà candidaté.</p>
                        </template>
                        <router-link v-else :to="{ name: 'login', query: { redirect: $route.fullPath } }"
                            class="block w-full py-2.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm text-center">
                            Se connecter pour candidater
                        </router-link>
                    </div>
                    <div v-else-if="call.status === 'closed'" class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 text-center">
                        <p class="text-sm text-slate-600 dark:text-slate-300 font-semibold">Cet appel est clôturé.</p>
                    </div>
                    <div v-else-if="call.status === 'awarded'" class="bg-violet-50 dark:bg-violet-900/30 border border-violet-100 dark:border-violet-900/50 rounded-2xl p-5 text-center">
                        <p class="text-sm text-violet-700 dark:text-violet-300 font-semibold">Projets attribués.</p>
                    </div>

                    <!-- Key info -->
                    <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 space-y-3 text-sm">
                        <div v-if="call.budget">
                            <span class="text-slate-500 dark:text-slate-400">Budget :</span>
                            <span class="font-bold ml-1">{{ fmtMoney(call.budget) }} {{ call.currency }}</span>
                        </div>
                        <div v-if="call.sector">
                            <span class="text-slate-500 dark:text-slate-400">Secteur :</span>
                            <span class="font-semibold ml-1">{{ call.sector }}</span>
                        </div>
                        <div v-if="call.geographic_zone">
                            <span class="text-slate-500 dark:text-slate-400">Zone :</span>
                            <span class="font-medium ml-1">{{ call.geographic_zone }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Vues :</span>
                            <span class="font-medium ml-1">{{ call.views_count || 0 }}</span>
                        </div>
                    </div>

                    <!-- Related calls -->
                    <div v-if="related.length" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                        <h3 class="font-bold text-sm mb-3">Autres appels ({{ call.country }})</h3>
                        <div class="space-y-3">
                            <router-link v-for="r in related" :key="r.id"
                                :to="{ name: 'gouvernement.call', params: { slug: r.slug } }"
                                class="block text-sm hover:text-sky-700 dark:hover:text-sky-400">
                                <div class="font-medium">{{ r.title }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ r.sector }} · {{ r.budget ? fmtMoney(r.budget) + ' ' + (r.currency||'') : '' }}</div>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Apply modal -->
        <div v-if="showApplyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showApplyModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Candidater</h3>
                    <button @click="showApplyModal = false" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-200 text-xl">&times;</button>
                </div>
                <form @submit.prevent="submitApplication" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Projet lié (optionnel)</label>
                        <select v-model="applyForm.project_id" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                            <option :value="null">— Aucun projet spécifique —</option>
                            <option v-for="p in myProjects" :key="p.id" :value="p.id">{{ p.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Motivation *</label>
                        <textarea v-model="applyForm.motivation" rows="4" required maxlength="5000"
                            placeholder="Pourquoi votre projet/organisation est le meilleur candidat…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-sky-400 dark:focus:border-sky-500 focus:outline-none text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Proposition technique</label>
                        <textarea v-model="applyForm.proposal" rows="3" maxlength="5000"
                            placeholder="Décrivez votre approche, méthodologie, planning…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-sky-400 dark:focus:border-sky-500 focus:outline-none text-sm"></textarea>
                    </div>
                    <p v-if="applyError" class="text-sm text-rose-600 dark:text-rose-400">{{ applyError }}</p>
                    <p v-if="applySuccess" class="text-sm text-emerald-600 dark:text-emerald-400">{{ applySuccess }}</p>
                    <button type="submit" :disabled="applyLoading"
                        class="w-full py-2.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white font-semibold disabled:opacity-50">
                        {{ applyLoading ? 'Envoi…' : 'Envoyer ma candidature' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const auth = useAuthStore();

const call = ref(null);
const related = ref([]);
const loading = ref(true);
const hasApplied = ref(false);
const myProjects = ref([]);

const showApplyModal = ref(false);
const applyForm = reactive({ project_id: null, motivation: '', proposal: '' });
const applyLoading = ref(false);
const applyError = ref('');
const applySuccess = ref('');

const isExpired = computed(() => call.value?.closes_at && new Date(call.value.closes_at) < new Date());

function statusClass(s) {
    return {
        open: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        closed: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
        awarded: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function statusLabel(s) {
    return { open: 'Ouvert', closed: 'Clôturé', awarded: 'Attribué' }[s] || s;
}
function fmtMoney(amount) {
    const n = parseFloat(amount) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000) return Math.round(n / 1_000) + 'k';
    return n.toLocaleString('fr-FR');
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

async function loadCall() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/gouvernement/appels/${route.params.slug}`);
        call.value = data.data;
        related.value = data.related || [];

        // Check if user has already applied
        if (auth.isAuthenticated) {
            try {
                const { data: apps } = await window.axios.get('/api/gouvernement/mes-candidatures');
                hasApplied.value = (apps.data || []).some(a => a.call_id === call.value.id);
            } catch { /* ignore */ }

            // Load user projects for linking
            try {
                const { data: proj } = await window.axios.get('/api/me/projects');
                myProjects.value = (proj.data || []).filter(p => p.status === 'published');
            } catch { /* ignore */ }
        }
    } catch {
        call.value = null;
    } finally {
        loading.value = false;
    }
}

async function submitApplication() {
    applyLoading.value = true;
    applyError.value = '';
    applySuccess.value = '';
    try {
        await window.axios.post(`/api/gouvernement/appels/${call.value.id}/apply`, applyForm);
        applySuccess.value = 'Candidature envoyée avec succès !';
        hasApplied.value = true;
        setTimeout(() => { showApplyModal.value = false; }, 1500);
    } catch (e) {
        applyError.value = e?.response?.data?.message || "Erreur lors de l'envoi.";
    } finally {
        applyLoading.value = false;
    }
}

onMounted(loadCall);
watch(() => route.params.slug, loadCall);
</script>
