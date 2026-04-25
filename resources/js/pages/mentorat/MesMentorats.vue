<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes mentorats</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Gérez vos demandes, sessions et avis.</p>
            </div>
            <router-link to="/mentorat" class="text-sm font-semibold text-violet-600 dark:text-violet-400 hover:underline">
                ← Annuaire mentors
            </router-link>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <template v-else>
            <!-- Tabs -->
            <div class="flex gap-1 mb-8 bg-slate-100 dark:bg-slate-800 rounded-lg p-1 w-fit">
                <button @click="tab = 'mentor'" v-if="asMentor.length"
                    class="px-4 py-2 rounded-md text-sm font-medium transition"
                    :class="tab === 'mentor' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100'">
                    En tant que mentor ({{ asMentor.length }})
                </button>
                <button @click="tab = 'mentee'"
                    class="px-4 py-2 rounded-md text-sm font-medium transition"
                    :class="tab === 'mentee' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100'">
                    En tant que mentee ({{ asMentee.length }})
                </button>
            </div>

            <!-- As Mentor -->
            <div v-if="tab === 'mentor'">
                <div v-if="!asMentor.length" class="text-center py-12 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
                    Aucune demande de mentorat reçue.
                </div>
                <div v-else class="space-y-4">
                    <div v-for="m in asMentor" :key="m.id"
                        class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold">{{ m.mentee?.name }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ m.mentee?.country }}</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :class="statusClass(m.status)">{{ statusLabel(m.status) }}</span>
                                </div>
                                <p class="text-sm font-medium text-violet-700 dark:text-violet-300">{{ m.topic }}</p>
                                <p v-if="m.message" class="text-sm text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ m.message }}</p>
                                <p v-if="m.goals" class="text-xs text-slate-500 dark:text-slate-400 mt-1"><strong>Objectifs :</strong> {{ m.goals }}</p>
                                <div class="flex gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span v-if="m.skill">🏷️ {{ m.skill.name }}</span>
                                    <span>📅 {{ m.duration_weeks }} semaines</span>
                                    <span>💬 {{ m.sessions_count || 0 }} session(s)</span>
                                </div>
                            </div>

                            <div class="flex gap-2 shrink-0">
                                <template v-if="m.status === 'requested'">
                                    <button @click="respond(m.id, 'accept')"
                                        class="px-3 py-1.5 text-sm font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                                        Accepter
                                    </button>
                                    <button @click="respond(m.id, 'decline')"
                                        class="px-3 py-1.5 text-sm font-semibold rounded-md border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200">
                                        Décliner
                                    </button>
                                </template>
                                <button v-if="m.status === 'accepted'" @click="completeMentorship(m.id)"
                                    class="px-3 py-1.5 text-sm font-semibold rounded-md bg-violet-600 hover:bg-violet-700 text-white">
                                    Terminer
                                </button>
                            </div>
                        </div>

                        <!-- Sessions -->
                        <div v-if="m.sessions?.length" class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4">
                            <h4 class="text-sm font-bold mb-2">Sessions</h4>
                            <div class="space-y-2">
                                <div v-for="s in m.sessions" :key="s.id"
                                    class="flex items-center justify-between text-sm bg-slate-50 dark:bg-slate-900/60 rounded-lg px-3 py-2">
                                    <div>
                                        <span class="font-medium">{{ s.title || 'Session' }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400 ml-2">{{ formatDateShort(s.scheduled_at) }}</span>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="sessionStatusClass(s.status)">
                                        {{ sessionStatusLabel(s.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- As Mentee -->
            <div v-if="tab === 'mentee'">
                <div v-if="!asMentee.length" class="text-center py-12 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl">
                    Vous n'avez pas encore de mentorat.
                    <router-link to="/mentorat" class="text-violet-600 dark:text-violet-400 font-semibold hover:underline ml-1">
                        Trouver un mentor →
                    </router-link>
                </div>
                <div v-else class="space-y-4">
                    <div v-for="m in asMentee" :key="m.id"
                        class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <router-link :to="{ name: 'mentorat.mentor', params: { id: m.mentor?.id } }"
                                        class="font-bold text-violet-700 dark:text-violet-300 hover:underline">
                                        {{ m.mentor?.name }}
                                    </router-link>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ m.mentor?.country }}</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :class="statusClass(m.status)">{{ statusLabel(m.status) }}</span>
                                </div>
                                <p class="text-sm font-medium">{{ m.topic }}</p>
                                <div class="flex gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span v-if="m.skill">🏷️ {{ m.skill.name }}</span>
                                    <span>📅 {{ m.duration_weeks }} semaines</span>
                                    <span>💬 {{ m.sessions_count || 0 }} session(s)</span>
                                </div>
                            </div>

                            <button v-if="m.status === 'accepted'" @click="completeMentorship(m.id)"
                                class="px-3 py-1.5 text-sm font-semibold rounded-md bg-violet-600 hover:bg-violet-700 text-white shrink-0">
                                Terminer
                            </button>
                        </div>

                        <!-- Sessions -->
                        <div v-if="m.sessions?.length" class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4">
                            <h4 class="text-sm font-bold mb-2">Sessions</h4>
                            <div class="space-y-2">
                                <div v-for="s in m.sessions" :key="s.id"
                                    class="flex items-center justify-between text-sm bg-slate-50 dark:bg-slate-900/60 rounded-lg px-3 py-2">
                                    <div>
                                        <span class="font-medium">{{ s.title || 'Session' }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400 ml-2">{{ formatDateShort(s.scheduled_at) }}</span>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="sessionStatusClass(s.status)">
                                        {{ sessionStatusLabel(s.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();

const asMentor = ref([]);
const asMentee = ref([]);
const loading = ref(true);
const tab = ref('mentee');

function statusClass(s) {
    return {
        requested: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        accepted: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        declined: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
        completed: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function statusLabel(s) {
    return { requested: 'En attente', accepted: 'En cours', declined: 'Déclinée', completed: 'Terminé' }[s] || s;
}

function sessionStatusClass(s) {
    return {
        scheduled: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        completed: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        cancelled: 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
        no_show: 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400',
    }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function sessionStatusLabel(s) {
    return { scheduled: 'Planifiée', completed: 'Terminée', cancelled: 'Annulée', no_show: 'Absence' }[s] || s;
}

function formatDateShort(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function loadData() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/mentorat/my');
        asMentor.value = data.as_mentor || [];
        asMentee.value = data.as_mentee || [];
        // Default to mentor tab if there are pending requests
        if (asMentor.value.some(m => m.status === 'requested')) {
            tab.value = 'mentor';
        }
    } finally {
        loading.value = false;
    }
}

async function respond(mentorshipId, action) {
    try {
        await window.axios.post(`/api/mentorat/${mentorshipId}/respond`, { action });
        await loadData();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur');
    }
}

async function completeMentorship(mentorshipId) {
    if (!confirm('Marquer ce mentorat comme terminé ?')) return;
    try {
        await window.axios.post(`/api/mentorat/${mentorshipId}/complete`);
        await loadData();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur');
    }
}

onMounted(loadData);
</script>
