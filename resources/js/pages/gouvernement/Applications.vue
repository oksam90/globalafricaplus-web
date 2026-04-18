<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <router-link to="/gouvernement/mes-appels" class="text-sm text-sky-600 hover:underline">← Mes appels</router-link>
                <h1 class="text-3xl font-black tracking-tight mt-2">Candidatures reçues</h1>
                <p v-if="callTitle" class="text-slate-600 mt-1">Pour : <strong>{{ callTitle }}</strong></p>
            </div>
        </div>

        <div v-if="loading" class="text-slate-500 py-8">Chargement…</div>
        <div v-else-if="applications.length === 0" class="text-center py-16 text-slate-500 bg-slate-50 rounded-2xl">
            Aucune candidature reçue pour cet appel.
        </div>
        <div v-else class="space-y-4">
            <div v-for="app in applications" :key="app.id"
                class="bg-white border border-slate-100 rounded-2xl p-5">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center text-sm font-bold text-sky-700">
                                {{ app.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <div class="font-semibold">{{ app.user?.name }}</div>
                                <div class="text-xs text-slate-500">{{ app.user?.email }} · {{ app.user?.country }}</div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="appStatusClass(app.status)">
                                {{ appStatusLabel(app.status) }}
                            </span>
                            <span v-if="app.score != null" class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100">
                                Score : {{ app.score }}/100
                            </span>
                        </div>

                        <!-- Linked project -->
                        <div v-if="app.project" class="mb-2">
                            <router-link :to="`/projets/${app.project.slug}`"
                                class="text-sm text-sky-700 font-semibold hover:underline">
                                Projet : {{ app.project.title }}
                            </router-link>
                        </div>

                        <div class="bg-slate-50 rounded-xl p-4 mt-2">
                            <h4 class="text-xs font-bold text-slate-500 uppercase mb-1">Motivation</h4>
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ app.motivation }}</p>
                        </div>

                        <div v-if="app.proposal" class="bg-slate-50 rounded-xl p-4 mt-2">
                            <h4 class="text-xs font-bold text-slate-500 uppercase mb-1">Proposition technique</h4>
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ app.proposal }}</p>
                        </div>

                        <div class="text-xs text-slate-500 mt-2">
                            Soumise le {{ formatDate(app.created_at) }}
                            <span v-if="app.reviewed_at"> · Évaluée le {{ formatDate(app.reviewed_at) }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2 shrink-0 w-40">
                        <select v-model="app._status"
                            class="px-2 py-1.5 rounded-md border border-slate-200 text-xs font-medium">
                            <option value="submitted">Soumise</option>
                            <option value="under_review">En revue</option>
                            <option value="shortlisted">Présélectionnée</option>
                            <option value="accepted">Acceptée</option>
                            <option value="rejected">Rejetée</option>
                        </select>
                        <input v-model.number="app._score" type="number" min="0" max="100" placeholder="Score /100"
                            class="px-2 py-1.5 rounded-md border border-slate-200 text-xs" />
                        <textarea v-model="app._notes" rows="2" placeholder="Notes internes…"
                            class="px-2 py-1.5 rounded-md border border-slate-200 text-xs"></textarea>
                        <button @click="review(app)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-md bg-sky-600 hover:bg-sky-700 text-white">
                            Enregistrer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-8 flex justify-center gap-2">
            <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-medium border"
                :class="n === meta.current_page ? 'bg-sky-600 border-sky-600 text-white' : 'border-slate-200'">
                {{ n }}
            </button>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const callId = route.params.id;

const applications = ref([]);
const meta = ref({});
const loading = ref(true);
const callTitle = ref('');
const page = ref(1);

function appStatusClass(s) {
    return {
        submitted: 'bg-amber-100 text-amber-700',
        under_review: 'bg-blue-100 text-blue-700',
        shortlisted: 'bg-violet-100 text-violet-700',
        accepted: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    }[s] || 'bg-slate-100 text-slate-600';
}
function appStatusLabel(s) {
    return { submitted: 'Soumise', under_review: 'En revue', shortlisted: 'Présélectionnée', accepted: 'Acceptée', rejected: 'Rejetée' }[s] || s;
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
function goToPage(n) { page.value = n; load(); }

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/gouvernement/appels/${callId}/applications`, { params: { page: page.value } });
        applications.value = (data.data || []).map(a => ({
            ...a,
            _status: a.status,
            _score: a.score,
            _notes: a.review_notes || '',
        }));
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };

        // Get call title
        if (applications.value.length > 0 && applications.value[0].call) {
            callTitle.value = applications.value[0].call.title;
        }
    } finally {
        loading.value = false;
    }
}

async function review(app) {
    try {
        const { data } = await window.axios.patch(`/api/gouvernement/applications/${app.id}`, {
            status: app._status,
            score: app._score,
            review_notes: app._notes,
        });
        // Update in place
        Object.assign(app, data.data, { _status: data.data.status, _score: data.data.score, _notes: data.data.review_notes || '' });
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur.');
    }
}

onMounted(load);
</script>
