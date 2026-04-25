<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mon parcours de formalisation</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Suivez vos progrès étape par étape pour formaliser votre entreprise.</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/formalisation" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Hub formalisation →</router-link>
                <router-link to="/formalisation/business-plans" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:underline">Templates BP →</router-link>
            </div>
        </div>

        <!-- Country selector -->
        <div class="mb-6 flex items-center gap-3">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Pays :</label>
            <select v-model="country" @change="load" class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm">
                <option v-for="c in availableCountries" :key="c.country" :value="c.country">
                    {{ c.country }} ({{ c.steps_count }} étapes)
                </option>
            </select>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <template v-else>
            <!-- Progress banner -->
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-teal-950/40 border border-emerald-100 dark:border-emerald-900/50 rounded-2xl p-6 mb-8">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-emerald-900 dark:text-emerald-200">Progression — {{ country }}</h3>
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ progress.completion }}%</span>
                </div>
                <div class="h-3 bg-white dark:bg-slate-900 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full transition-all"
                        :style="{ width: progress.completion + '%' }"></div>
                </div>
                <div class="flex justify-between text-xs text-emerald-700 dark:text-emerald-300 mt-2">
                    <span>{{ progress.completed_steps }} / {{ progress.total_steps }} étapes complétées</span>
                    <span v-if="progress.completion === 100" class="font-bold">🎉 Formalisation terminée !</span>
                </div>
            </div>

            <!-- Steps -->
            <div class="space-y-4">
                <div v-for="item in stepsWithProgress" :key="item.step.id"
                    class="bg-white dark:bg-slate-800 border rounded-2xl overflow-hidden transition"
                    :class="{
                        'border-emerald-300 dark:border-emerald-800/60 bg-emerald-50/20 dark:bg-emerald-900/10': item.progress.status === 'completed',
                        'border-amber-300 dark:border-amber-800/60 bg-amber-50/20 dark:bg-amber-900/10': item.progress.status === 'in_progress',
                        'border-slate-100 dark:border-slate-700': item.progress.status === 'not_started'
                    }">
                    <div class="flex items-start gap-4 p-5">
                        <!-- Step number + status icon -->
                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold shrink-0"
                            :class="{
                                'bg-emerald-500 text-white': item.progress.status === 'completed',
                                'bg-amber-400 text-white': item.progress.status === 'in_progress',
                                'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300': item.progress.status === 'not_started'
                            }">
                            <span v-if="item.progress.status === 'completed'">✓</span>
                            <span v-else>{{ item.step.order }}</span>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-bold">{{ item.step.title }}</h4>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="statusClass(item.progress.status)">
                                    {{ statusLabel(item.progress.status) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ item.step.description }}</p>

                            <div class="flex flex-wrap gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="item.step.institution">🏛️ {{ item.step.institution }}</span>
                                <span v-if="item.step.estimated_duration">⏱️ {{ item.step.estimated_duration }}</span>
                                <span v-if="item.step.estimated_cost">💰 {{ item.step.estimated_cost }}</span>
                            </div>

                            <!-- Documents requis -->
                            <div v-if="item.step.required_documents?.length" class="mt-3">
                                <h5 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Documents requis</h5>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="(doc, i) in item.step.required_documents" :key="i"
                                        class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ doc }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tips -->
                            <div v-if="item.step.tips" class="mt-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-100 dark:border-amber-900/50 rounded-xl p-3">
                                <p class="text-xs text-amber-800 dark:text-amber-200"><strong>💡</strong> {{ item.step.tips }}</p>
                            </div>

                            <!-- Notes -->
                            <div v-if="editingStep === item.step.id" class="mt-3">
                                <textarea v-model="editNotes" rows="2" class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm"
                                    placeholder="Ajoutez des notes personnelles (RDV, documents reçus…)"></textarea>
                            </div>
                            <div v-else-if="item.progress.notes" class="mt-2 text-xs text-slate-500 dark:text-slate-400 italic">
                                📝 {{ item.progress.notes }}
                            </div>

                            <!-- Completed date -->
                            <div v-if="item.progress.completed_at" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                ✓ Complétée le {{ formatDate(item.progress.completed_at) }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="shrink-0 flex flex-col gap-2">
                            <select v-if="editingStep !== item.step.id"
                                :value="item.progress.status"
                                @change="quickUpdate(item.step.id, $event.target.value, item.progress.notes)"
                                class="text-xs px-2 py-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                                <option value="not_started">Non commencé</option>
                                <option value="in_progress">En cours</option>
                                <option value="completed">Terminé</option>
                            </select>
                            <button v-if="editingStep !== item.step.id" @click="startEdit(item)"
                                class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">📝 Notes</button>
                            <template v-if="editingStep === item.step.id">
                                <button @click="saveNotes(item.step.id, item.progress.status)"
                                    class="text-xs px-3 py-1 rounded bg-emerald-600 text-white font-medium">Sauver</button>
                                <button @click="editingStep = null" class="text-xs text-slate-500 dark:text-slate-400">Annuler</button>
                            </template>
                            <a v-if="item.step.link" :href="item.step.link" target="_blank"
                                class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline">Lien →</a>
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
const loading = ref(true);
const availableCountries = ref([]);
const country = ref('');
const stepsWithProgress = ref([]);
const progress = ref({ completion: 0, completed_steps: 0, total_steps: 0 });
const editingStep = ref(null);
const editNotes = ref('');

function statusClass(s) {
    return { completed: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300', in_progress: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300', not_started: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}
function statusLabel(s) {
    return { completed: 'Terminé', in_progress: 'En cours', not_started: 'Non commencé' }[s] || s;
}
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
function startEdit(item) {
    editingStep.value = item.step.id;
    editNotes.value = item.progress.notes || '';
}

async function load() {
    if (!country.value) return;
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/formalisation/mon-parcours', { params: { country: country.value } });
        stepsWithProgress.value = data.steps || [];
        progress.value = {
            completion: data.completion || 0,
            completed_steps: data.completed_steps || 0,
            total_steps: data.total_steps || 0,
        };
    } finally {
        loading.value = false;
    }
}

async function quickUpdate(stepId, status, notes) {
    try {
        await window.axios.post(`/api/formalisation/steps/${stepId}/progress`, { status, notes: notes || null });
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur.');
    }
}

async function saveNotes(stepId, status) {
    await quickUpdate(stepId, status, editNotes.value);
    editingStep.value = null;
}

async function loadCountries() {
    const { data } = await window.axios.get('/api/formalisation/countries');
    availableCountries.value = data.data || [];
    // Default to user country or first available
    const userCountry = auth.user?.country;
    if (userCountry && availableCountries.value.some(c => c.country === userCountry)) {
        country.value = userCountry;
    } else if (availableCountries.value.length) {
        country.value = availableCountries.value[0].country;
    }
}

onMounted(async () => {
    await loadCountries();
    await load();
});
</script>
