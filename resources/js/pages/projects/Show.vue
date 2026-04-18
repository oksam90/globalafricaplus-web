<template>
    <div v-if="loading" class="max-w-5xl mx-auto p-12 text-slate-500">Chargement…</div>
    <article v-else-if="project" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/projets" class="text-sm text-emerald-700 hover:underline">← Retour aux projets</router-link>

        <!-- Header -->
        <header class="mt-4">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <router-link v-if="project.category" :to="`/secteurs/${project.category.slug}`"
                    class="text-xs font-semibold px-2 py-1 rounded-md text-white"
                    :style="{ backgroundColor: project.category.color || '#10b981' }">
                    {{ project.category.name }}
                </router-link>
                <span v-if="project.sub_category" class="text-xs font-semibold px-2 py-1 rounded-md bg-slate-100 text-slate-700">
                    {{ project.sub_category.name }}
                </span>
                <span class="text-xs font-semibold px-2 py-1 rounded-md bg-slate-100 text-slate-700">
                    {{ project.country }}<span v-if="project.city"> · {{ project.city }}</span>
                </span>
                <span class="text-xs font-semibold px-2 py-1 rounded-md bg-amber-100 text-amber-800">
                    {{ stageLabel(project.stage) }}
                </span>
                <span v-if="project.status !== 'published'"
                    class="text-xs font-semibold px-2 py-1 rounded-md bg-rose-100 text-rose-700 uppercase">
                    {{ project.status }}
                </span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight">{{ project.title }}</h1>
            <p class="mt-3 text-lg text-slate-600">{{ project.summary }}</p>
        </header>

        <!-- Tabs + Sidebar -->
        <div class="mt-8 grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <!-- Tab nav -->
                <div class="border-b border-slate-200 mb-6 flex gap-6 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.id" @click="tab = t.id"
                        class="px-1 py-3 -mb-px text-sm font-semibold whitespace-nowrap border-b-2 transition"
                        :class="tab === t.id ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'">
                        {{ t.label }}
                        <span v-if="t.count !== undefined" class="ml-1 text-xs text-slate-400">{{ t.count }}</span>
                    </button>
                </div>

                <!-- About -->
                <div v-if="tab === 'about'" class="space-y-6">
                    <section>
                        <h2 class="text-lg font-bold mb-2">À propos du projet</h2>
                        <p class="whitespace-pre-line text-slate-700">{{ project.description || 'Aucune description fournie.' }}</p>
                    </section>

                    <section v-if="project.sdgs?.length">
                        <h2 class="text-lg font-bold mb-2">Objectifs de Développement Durable</h2>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="s in project.sdgs" :key="s.id"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-white text-sm font-medium"
                                :style="{ backgroundColor: s.color }">
                                <span class="font-black">{{ s.number }}</span>
                                <span>{{ s.name }}</span>
                            </div>
                        </div>
                    </section>

                    <section v-if="project.tags?.length">
                        <h2 class="text-lg font-bold mb-2">Tags</h2>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in project.tags" :key="t" class="px-2.5 py-1 text-xs rounded-full bg-slate-100 text-slate-700">
                                #{{ t }}
                            </span>
                        </div>
                    </section>

                    <section v-if="project.website || project.video_url || project.pitch_deck_url" class="space-y-2">
                        <h2 class="text-lg font-bold mb-2">Liens</h2>
                        <ul class="text-sm space-y-1">
                            <li v-if="project.website"><a :href="project.website" target="_blank" class="text-emerald-700 hover:underline">🌐 Site web</a></li>
                            <li v-if="project.video_url"><a :href="project.video_url" target="_blank" class="text-emerald-700 hover:underline">🎬 Vidéo de présentation</a></li>
                            <li v-if="project.pitch_deck_url"><a :href="project.pitch_deck_url" target="_blank" class="text-emerald-700 hover:underline">📊 Pitch deck</a></li>
                        </ul>
                    </section>
                </div>

                <!-- Updates -->
                <div v-else-if="tab === 'updates'">
                    <div v-if="canEdit" class="mb-6 bg-white border border-slate-100 rounded-2xl p-5">
                        <h3 class="font-bold mb-3">Publier une actualité</h3>
                        <form @submit.prevent="postUpdate" class="space-y-3">
                            <input v-model="updateForm.title" type="text" placeholder="Titre" required maxlength="150"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none text-sm" />
                            <textarea v-model="updateForm.body" rows="4" placeholder="Contenu…" required maxlength="5000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none text-sm"></textarea>
                            <button :disabled="posting" class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-60">
                                {{ posting ? 'Publication…' : 'Publier' }}
                            </button>
                        </form>
                    </div>

                    <div v-if="!project.updates?.length" class="text-center py-10 text-slate-500">
                        Aucune actualité pour le moment.
                    </div>
                    <div v-else class="space-y-4">
                        <article v-for="u in project.updates" :key="u.id" class="bg-white border border-slate-100 rounded-2xl p-5">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold">{{ u.title }}</h3>
                                <span class="text-xs text-slate-500">{{ formatDate(u.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-line text-slate-700 text-sm">{{ u.body }}</p>
                            <p v-if="u.author" class="mt-3 text-xs text-slate-500">par {{ u.author.name }}</p>
                        </article>
                    </div>
                </div>

                <!-- Related -->
                <div v-else-if="tab === 'related'">
                    <div v-if="!related.length" class="text-center py-10 text-slate-500">
                        Aucun projet similaire trouvé.
                    </div>
                    <div v-else class="grid md:grid-cols-2 gap-5">
                        <ProjectCard v-for="p in related" :key="p.id" :project="p" />
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <aside class="bg-white rounded-2xl border border-slate-100 p-6 h-fit lg:sticky lg:top-20">
                <div class="text-3xl font-black">{{ formatAmount(project.amount_raised) }}</div>
                <div class="text-sm text-slate-500">levés sur {{ formatAmount(project.amount_needed) }}</div>

                <div class="mt-4 h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500" :style="{ width: progress + '%' }"></div>
                </div>
                <div class="mt-2 text-xs font-medium text-slate-600">{{ progress }}% financé</div>

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Emplois ciblés</dt>
                        <dd class="font-semibold">{{ project.jobs_target || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Porteur</dt>
                        <dd class="font-semibold">{{ project.user?.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Suivis</dt>
                        <dd class="font-semibold">{{ project.followers_count || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Actualités</dt>
                        <dd class="font-semibold">{{ project.updates_count || project.updates?.length || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Vues</dt>
                        <dd class="font-semibold">{{ project.views_count }}</dd>
                    </div>
                </dl>

                <button class="mt-6 w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                    Investir dans ce projet
                </button>

                <button v-if="auth.isAuthenticated" @click="toggleFollow"
                    class="mt-2 w-full px-4 py-2.5 rounded-lg border font-semibold transition"
                    :class="isFollowing ? 'border-rose-300 text-rose-600 hover:bg-rose-50' : 'border-slate-200 hover:border-emerald-300 text-slate-800'">
                    {{ isFollowing ? '♥ Suivi' : '♡ Suivre' }}
                </button>

                <router-link v-if="canEdit" :to="`/projets/${project.slug}/modifier`"
                    class="mt-2 block text-center w-full px-4 py-2.5 rounded-lg border border-slate-200 hover:border-emerald-300 text-slate-800 font-semibold">
                    ✎ Modifier
                </router-link>
            </aside>
        </div>
    </article>
    <div v-else class="max-w-5xl mx-auto p-12 text-slate-500">Projet introuvable.</div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import ProjectCard from '../../components/ProjectCard.vue';

const route = useRoute();
const auth = useAuthStore();

const project = ref(null);
const related = ref([]);
const loading = ref(true);
const tab = ref('about');
const isFollowing = ref(false);
const canEdit = ref(false);
const posting = ref(false);
const updateForm = reactive({ title: '', body: '' });

const tabs = computed(() => [
    { id: 'about', label: 'À propos' },
    { id: 'updates', label: 'Actualités', count: project.value?.updates?.length ?? 0 },
    { id: 'related', label: 'Projets similaires' },
]);

const progress = computed(() => {
    if (!project.value) return 0;
    const need = parseFloat(project.value.amount_needed) || 0;
    const raised = parseFloat(project.value.amount_raised) || 0;
    if (need <= 0) return 0;
    return Math.min(100, Math.round((raised / need) * 100));
});

function formatAmount(v) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(parseFloat(v) || 0);
}
function formatDate(d) {
    return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
}
function stageLabel(s) {
    return ({ idea: 'Idée', mvp: 'MVP', launch: 'Lancement', scaling: 'Croissance' })[s] || s;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/projects/${route.params.slug}`);
        project.value = data.data;
        related.value = data.related || [];
        isFollowing.value = data.is_following;
        canEdit.value = data.can_edit;
    } catch {
        project.value = null;
    } finally {
        loading.value = false;
    }
}

async function toggleFollow() {
    if (!project.value) return;
    try {
        const { data } = isFollowing.value
            ? await window.axios.delete(`/api/projects/${project.value.id}/follow`)
            : await window.axios.post(`/api/projects/${project.value.id}/follow`);
        isFollowing.value = data.is_following;
        project.value.followers_count = data.followers_count;
    } catch (e) {
        console.error(e);
    }
}

async function postUpdate() {
    posting.value = true;
    try {
        const { data } = await window.axios.post(`/api/projects/${project.value.id}/updates`, updateForm);
        project.value.updates = [data.data, ...(project.value.updates || [])];
        updateForm.title = '';
        updateForm.body = '';
    } finally {
        posting.value = false;
    }
}

onMounted(load);
watch(() => route.params.slug, load);
</script>
