<template>
    <div v-if="loading" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Chargement du profil…</div>
    <div v-else-if="!mentor" class="max-w-7xl mx-auto px-4 py-16 text-slate-500 dark:text-slate-400">Mentor introuvable.</div>
    <div v-else>
        <!-- Header -->
        <section class="bg-gradient-to-br from-violet-50 via-fuchsia-50 to-slate-50 dark:from-violet-950/40 dark:via-fuchsia-950/30 dark:to-slate-900 border-b border-slate-100 dark:border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <router-link to="/mentorat" class="text-sm text-violet-600 dark:text-violet-400 hover:underline mb-4 inline-block">
                    ← Annuaire mentors
                </router-link>

                <div class="flex flex-col md:flex-row gap-6 items-start">
                    <!-- Avatar -->
                    <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center text-white text-3xl font-black shadow-lg">
                        {{ initials }}
                    </div>

                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-3xl font-black tracking-tight">{{ mentor.name }}</h1>
                            <span v-if="mentor.kyc_level === 'verified' || mentor.kyc_level === 'certified'"
                                class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                                ✓ Vérifié
                            </span>
                            <span v-if="mentor.is_diaspora"
                                class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">
                                Diaspora
                            </span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400">{{ mentor.country }}{{ mentor.city ? ', ' + mentor.city : '' }}</p>
                        <p v-if="mentor.bio" class="mt-3 text-slate-700 dark:text-slate-200 max-w-2xl">{{ mentor.bio }}</p>

                        <!-- Stats row -->
                        <div class="flex flex-wrap gap-5 mt-4">
                            <div v-if="mentor.avg_rating > 0" class="flex items-center gap-1.5">
                                <div class="flex gap-0.5">
                                    <span v-for="i in 5" :key="i" class="text-lg"
                                        :class="i <= Math.round(mentor.avg_rating) ? 'text-amber-400' : 'text-slate-200 dark:text-slate-600'">★</span>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ parseFloat(mentor.avg_rating).toFixed(1) }}</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">({{ mentor.reviews_count }} avis)</span>
                            </div>
                            <div class="text-sm text-slate-600 dark:text-slate-300">
                                <strong>{{ mentor.mentorships_completed_count || 0 }}</strong> mentorats terminés
                            </div>
                            <div class="text-sm text-slate-600 dark:text-slate-300">
                                <strong>{{ mentor.mentorships_active_count || 0 }}</strong> en cours
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div v-if="auth.isAuthenticated && auth.user?.id !== mentor.id" class="shrink-0">
                        <button @click="showRequestModal = true"
                            class="px-5 py-3 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-semibold shadow-sm">
                            Demander un mentorat
                        </button>
                    </div>
                    <div v-else-if="!auth.isAuthenticated" class="shrink-0">
                        <router-link :to="{ name: 'login', query: { redirect: $route.fullPath } }"
                            class="px-5 py-3 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-semibold shadow-sm inline-block">
                            Se connecter pour demander
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Left column -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Skills -->
                    <div>
                        <h2 class="text-xl font-bold mb-4">Compétences</h2>
                        <div class="grid md:grid-cols-2 gap-3">
                            <div v-for="s in mentor.skills" :key="s.id"
                                class="flex items-center justify-between bg-slate-50 dark:bg-slate-900 rounded-xl p-4">
                                <div>
                                    <div class="font-medium text-sm">{{ s.name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ s.category }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :class="levelClass(s.pivot?.level)">
                                        {{ levelLabel(s.pivot?.level) }}
                                    </span>
                                    <div v-if="s.pivot?.years_experience" class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                        {{ s.pivot.years_experience }} ans
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div>
                        <h2 class="text-xl font-bold mb-4">Avis ({{ reviews.length }})</h2>
                        <div v-if="reviews.length === 0" class="text-slate-500 dark:text-slate-400 py-6 text-center bg-slate-50 dark:bg-slate-900 rounded-xl">
                            Aucun avis pour le moment.
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="r in reviews" :key="r.id"
                                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center text-xs font-bold text-violet-700 dark:text-violet-300">
                                            {{ r.reviewer?.name?.charAt(0) || '?' }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-sm">{{ r.reviewer?.name }}</div>
                                            <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ formatDate(r.created_at) }}</div>
                                        </div>
                                    </div>
                                    <div class="flex gap-0.5">
                                        <span v-for="i in 5" :key="i" class="text-sm"
                                            :class="i <= r.rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-600'">★</span>
                                    </div>
                                </div>
                                <p v-if="r.comment" class="text-sm text-slate-700 dark:text-slate-200">{{ r.comment }}</p>
                                <div v-if="r.tags?.length" class="flex flex-wrap gap-1 mt-2">
                                    <span v-for="t in r.tags" :key="t"
                                        class="text-[10px] px-2 py-0.5 rounded-full bg-violet-50 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300 font-medium">
                                        {{ t }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-5">
                    <!-- Role profile info -->
                    <div v-if="mentorProfile" class="bg-violet-50 dark:bg-violet-900/30 border border-violet-100 dark:border-violet-900/50 rounded-2xl p-5">
                        <h3 class="font-bold text-sm mb-3">Profil mentor</h3>
                        <div class="space-y-2 text-sm">
                            <div v-if="mentorProfile.data?.expertise">
                                <span class="text-slate-500 dark:text-slate-400">Expertise :</span>
                                <span class="font-medium ml-1">{{ mentorProfile.data.expertise }}</span>
                            </div>
                            <div v-if="mentorProfile.data?.availability_hours_week">
                                <span class="text-slate-500 dark:text-slate-400">Disponibilité :</span>
                                <span class="font-medium ml-1">{{ mentorProfile.data.availability_hours_week }}h/semaine</span>
                            </div>
                            <div v-if="mentorProfile.data?.languages?.length">
                                <span class="text-slate-500 dark:text-slate-400">Langues :</span>
                                <span class="font-medium ml-1">{{ mentorProfile.data.languages.join(', ') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Availabilities -->
                    <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                        <h3 class="font-bold text-sm mb-3">Disponibilités</h3>
                        <div v-if="mentor.mentor_availabilities?.length" class="space-y-2">
                            <div v-for="a in mentor.mentor_availabilities" :key="a.id"
                                class="flex items-center justify-between text-sm">
                                <span class="font-medium capitalize">{{ dayLabel(a.day_of_week) }}</span>
                                <span class="text-slate-500 dark:text-slate-400">{{ a.start_time.slice(0, 5) }} — {{ a.end_time.slice(0, 5) }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-400 dark:text-slate-500">Créneaux non renseignés.</p>
                    </div>

                    <!-- Member since -->
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-2xl p-5 text-sm text-slate-600 dark:text-slate-300">
                        Membre depuis {{ formatDate(mentor.created_at) }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Request modal -->
        <div v-if="showRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showRequestModal = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Demander un mentorat à {{ mentor.name }}</h3>
                    <button @click="showRequestModal = false" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-200 text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitRequest" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Sujet *</label>
                        <input v-model="requestForm.topic" type="text" required maxlength="200" placeholder="Ex: Stratégie de levée de fonds"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-violet-400 dark:focus:border-violet-500 focus:outline-none text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Compétence</label>
                        <select v-model="requestForm.skill_id"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                            <option :value="null">— Aucune en particulier —</option>
                            <option v-for="s in mentor.skills" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Message</label>
                        <textarea v-model="requestForm.message" rows="3" maxlength="2000"
                            placeholder="Présentez-vous et expliquez votre besoin…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-violet-400 dark:focus:border-violet-500 focus:outline-none text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Objectifs</label>
                        <textarea v-model="requestForm.goals" rows="2" maxlength="2000"
                            placeholder="Que souhaitez-vous accomplir ?"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-violet-400 dark:focus:border-violet-500 focus:outline-none text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Durée souhaitée</label>
                        <select v-model.number="requestForm.duration_weeks"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                            <option :value="4">4 semaines</option>
                            <option :value="8">8 semaines</option>
                            <option :value="12">12 semaines (3 mois)</option>
                            <option :value="24">24 semaines (6 mois)</option>
                        </select>
                    </div>

                    <p v-if="requestError" class="text-sm text-rose-600 dark:text-rose-400">{{ requestError }}</p>
                    <p v-if="requestSuccess" class="text-sm text-emerald-600 dark:text-emerald-400">{{ requestSuccess }}</p>

                    <button type="submit" :disabled="requestLoading"
                        class="w-full py-2.5 rounded-md bg-violet-600 hover:bg-violet-700 text-white font-semibold disabled:opacity-50">
                        {{ requestLoading ? 'Envoi…' : 'Envoyer la demande' }}
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

const mentor = ref(null);
const reviews = ref([]);
const loading = ref(true);

const showRequestModal = ref(false);
const requestForm = reactive({ topic: '', skill_id: null, message: '', goals: '', duration_weeks: 8 });
const requestLoading = ref(false);
const requestError = ref('');
const requestSuccess = ref('');

const initials = computed(() => {
    const parts = (mentor.value?.name || '').split(' ');
    return parts.map(p => p.charAt(0).toUpperCase()).slice(0, 2).join('');
});

const mentorProfile = computed(() => {
    return (mentor.value?.role_profiles || []).find(p => p.role?.slug === 'mentor') || null;
});

const DAY_LABELS = { monday: 'Lundi', tuesday: 'Mardi', wednesday: 'Mercredi', thursday: 'Jeudi', friday: 'Vendredi', saturday: 'Samedi', sunday: 'Dimanche' };
function dayLabel(d) { return DAY_LABELS[d] || d; }

function levelClass(l) {
    return {
        expert: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
        advanced: 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        intermediate: 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        beginner: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    }[l] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}

function levelLabel(l) {
    return { expert: 'Expert', advanced: 'Avancé', intermediate: 'Intermédiaire', beginner: 'Débutant' }[l] || l;
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
}

async function loadMentor() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/mentorat/mentors/${route.params.id}`);
        mentor.value = data.data;
        reviews.value = data.reviews || [];
    } catch {
        mentor.value = null;
    } finally {
        loading.value = false;
    }
}

async function submitRequest() {
    requestLoading.value = true;
    requestError.value = '';
    requestSuccess.value = '';
    try {
        await window.axios.post('/api/mentorat/request', {
            mentor_id: mentor.value.id,
            ...requestForm,
        });
        requestSuccess.value = 'Demande envoyée ! Le mentor sera notifié.';
        setTimeout(() => { showRequestModal.value = false; requestSuccess.value = ''; }, 2000);
    } catch (e) {
        requestError.value = e?.response?.data?.message || 'Erreur lors de l\'envoi.';
    } finally {
        requestLoading.value = false;
    }
}

onMounted(loadMentor);
watch(() => route.params.id, loadMentor);
</script>
