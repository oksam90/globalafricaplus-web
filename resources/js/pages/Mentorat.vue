<template>
    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-br from-violet-50 via-fuchsia-50 to-slate-50 dark:from-violet-950/40 dark:via-fuchsia-950/30 dark:to-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold mb-4">
                        Mentorat & Compétences
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-slate-100 leading-[1.05]">
                        Apprenez des
                        <span class="block bg-gradient-to-r from-violet-500 via-fuchsia-500 to-purple-600 bg-clip-text text-transparent">
                            meilleurs.
                        </span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl">
                        Marketplace panafricaine de compétences. Connectez-vous à des mentors experts
                        ou partagez votre savoir-faire pour accompagner la prochaine génération d'entrepreneurs.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#mentors" class="px-5 py-3 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-semibold shadow-sm">
                            Trouver un mentor
                        </a>
                        <router-link to="/inscription" class="px-5 py-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-violet-300 font-semibold text-slate-800 dark:text-slate-200">
                            Devenir mentor →
                        </router-link>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-violet-600">{{ stats.mentors_count || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Mentors actifs</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ stats.skills_count || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Compétences</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-emerald-600">{{ stats.completed_mentorships || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Mentorats terminés</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                        <div class="text-2xl font-black text-amber-500">{{ stats.avg_rating || '—' }}★</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">Note moyenne</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black tracking-tight text-center mb-12">Comment ça marche ?</h2>
            <div class="grid md:grid-cols-4 gap-6">
                <div v-for="(step, i) in steps" :key="i" class="text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-violet-100 flex items-center justify-center text-violet-700 text-xl font-black mb-4">
                        {{ i + 1 }}
                    </div>
                    <h3 class="font-bold text-lg">{{ step.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">{{ step.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Skills by category -->
    <section class="py-16 bg-slate-50 dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black tracking-tight text-center mb-4">Compétences disponibles</h2>
            <p class="text-center text-slate-600 dark:text-slate-300 mb-10">Nos mentors couvrent un large éventail de domaines.</p>

            <div v-if="skillsGrouped" class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="(skills, category) in skillsGrouped" :key="category"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-5">
                    <h3 class="font-bold text-sm text-violet-700 uppercase tracking-wider mb-3">{{ category }}</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="s in skills" :key="s.id"
                            @click="filterBySkill(s.slug)"
                            class="text-xs px-2.5 py-1 rounded-full font-medium transition"
                            :class="filters.skill === s.slug ? 'bg-violet-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-violet-50 hover:text-violet-700'">
                            {{ s.name }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mentor directory -->
    <section id="mentors" class="py-16 bg-white dark:bg-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black tracking-tight">Annuaire des mentors</h2>
                    <p class="text-slate-600 dark:text-slate-300 mt-2">{{ mentorsMeta.total || 0 }} mentors à votre disposition.</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
                <div class="grid md:grid-cols-4 gap-3">
                    <input v-model="filters.search" @input="debouncedLoadMentors"
                        type="search" placeholder="Rechercher un mentor…"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 focus:border-violet-400 focus:outline-none text-sm md:col-span-2" />

                    <select v-model="filters.country" @change="loadMentors"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                        <option value="">Tous pays</option>
                        <option v-for="c in mentorCountries" :key="c" :value="c">{{ c }}</option>
                    </select>

                    <select v-model="filters.sort" @change="loadMentors"
                        class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm">
                        <option value="rating">Mieux notés</option>
                        <option value="sessions">Plus expérimentés</option>
                        <option value="recent">Derniers inscrits</option>
                    </select>
                </div>

                <div v-if="filters.skill" class="mt-3 flex items-center gap-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Filtré par compétence :</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-medium">
                        {{ skillNameFor(filters.skill) }}
                        <button @click="filters.skill = ''; loadMentors()" class="ml-1 hover:text-violet-900">&times;</button>
                    </span>
                </div>
            </div>

            <!-- Grid -->
            <div v-if="mentorsLoading" class="text-slate-500 dark:text-slate-400 py-8">Chargement des mentors…</div>
            <div v-else-if="mentors.length === 0" class="text-center py-16 text-slate-500 dark:text-slate-400">
                Aucun mentor ne correspond à vos critères.
            </div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <MentorCard v-for="m in mentors" :key="m.id" :mentor="m" />
            </div>

            <!-- Pagination -->
            <div v-if="mentorsMeta.last_page > 1" class="mt-10 flex justify-center gap-2">
                <button v-for="n in mentorsMeta.last_page" :key="n"
                    @click="goToPage(n)"
                    class="px-3 py-1.5 rounded-md text-sm font-medium border"
                    :class="n === mentorsMeta.current_page ? 'bg-violet-600 border-violet-600 text-white' : 'border-slate-200 dark:border-slate-700 hover:border-violet-300'">
                    {{ n }}
                </button>
            </div>
        </div>
    </section>

    <!-- Become a mentor CTA -->
    <section class="py-20 bg-gradient-to-br from-violet-600 via-purple-700 to-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black">Devenez mentor Africa+</h2>
                    <p class="mt-4 text-violet-100">
                        Partagez votre expertise avec des entrepreneurs africains ambitieux.
                        Définissez vos créneaux, choisissez vos mentees, créez de l'impact.
                    </p>
                    <ul class="mt-6 space-y-3 text-sm text-violet-100">
                        <li class="flex items-start gap-2"><span class="text-violet-300 mt-0.5">✓</span> Profil public dans l'annuaire panafricain</li>
                        <li class="flex items-start gap-2"><span class="text-violet-300 mt-0.5">✓</span> Gestion flexible des disponibilités</li>
                        <li class="flex items-start gap-2"><span class="text-violet-300 mt-0.5">✓</span> Suivi des sessions et progression des mentees</li>
                        <li class="flex items-start gap-2"><span class="text-violet-300 mt-0.5">✓</span> Badge et avis vérifiés</li>
                        <li class="flex items-start gap-2"><span class="text-violet-300 mt-0.5">✓</span> Matching IA avec les entrepreneurs</li>
                    </ul>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div v-for="a in advantages" :key="a.title" class="bg-white/10 backdrop-blur rounded-2xl p-5">
                        <div class="text-2xl mb-2">{{ a.icon }}</div>
                        <h3 class="font-bold text-sm">{{ a.title }}</h3>
                        <p class="text-xs text-violet-200 mt-1">{{ a.text }}</p>
                    </div>
                </div>
            </div>
            <div class="mt-10 text-center">
                <router-link to="/inscription"
                    class="inline-block px-6 py-3 rounded-lg bg-white dark:bg-slate-800 text-violet-700 font-semibold hover:bg-violet-50">
                    Créer mon compte mentor
                </router-link>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import MentorCard from '../components/MentorCard.vue';

const route = useRoute();

const stats = ref({});
const mentors = ref([]);
const mentorsMeta = ref({});
const mentorsLoading = ref(true);
const skillsGrouped = ref(null);
const allSkills = ref([]);
const mentorCountries = ref([]);
const page = ref(1);

const filters = reactive({
    search: '',
    country: '',
    skill: '',
    sort: 'rating',
});

const steps = [
    { title: 'Explorez l\'annuaire', text: 'Parcourez les profils de mentors par compétence, pays et disponibilité.' },
    { title: 'Envoyez une demande', text: 'Décrivez votre besoin, vos objectifs et la durée souhaitée.' },
    { title: 'Sessions de mentorat', text: 'Planifiez des sessions régulières, prenez des notes, suivez votre progression.' },
    { title: 'Évaluez et progressez', text: 'Laissez un avis après chaque mentorat. Construisez votre réseau.' },
];

const advantages = [
    { icon: '🌍', title: 'Réseau panafricain', text: 'Mentors dans 20+ pays.' },
    { icon: '📊', title: 'Impact mesurable', text: 'Suivi sessions et progression.' },
    { icon: '🤖', title: 'Matching IA', text: 'Algorithme de suggestion.' },
    { icon: '🛡️', title: 'Profils vérifiés', text: 'KYC et avis authentiques.' },
];

let debounce;
function debouncedLoadMentors() {
    clearTimeout(debounce);
    debounce = setTimeout(() => { page.value = 1; loadMentors(); }, 300);
}

function filterBySkill(slug) {
    filters.skill = filters.skill === slug ? '' : slug;
    page.value = 1;
    loadMentors();
}

function skillNameFor(slug) {
    return allSkills.value.find(s => s.slug === slug)?.name || slug;
}

function goToPage(n) {
    page.value = n;
    loadMentors();
    window.scrollTo({ top: document.getElementById('mentors')?.offsetTop - 80, behavior: 'smooth' });
}

async function loadMentors() {
    mentorsLoading.value = true;
    try {
        const params = { ...filters, page: page.value };
        Object.keys(params).forEach(k => (params[k] === '' || params[k] == null) && delete params[k]);
        const { data } = await window.axios.get('/api/mentorat/mentors', { params });
        mentors.value = data.data || [];
        mentorsMeta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        mentorsLoading.value = false;
    }
}

async function load() {
    try {
        const [{ data: s }, { data: sk }] = await Promise.all([
            window.axios.get('/api/mentorat/stats'),
            window.axios.get('/api/mentorat/skills'),
        ]);
        stats.value = s;
        allSkills.value = sk.data || [];
        skillsGrouped.value = sk.grouped || {};

        // Extract unique countries from mentors (quick approach: load first page)
        mentorCountries.value = [...new Set((await window.axios.get('/api/mentorat/mentors', { params: { per_page: 100 } }))
            .data.data.map(m => m.country).filter(Boolean))].sort();
    } catch (e) {
        console.error('Mentorat load error:', e);
    }

    await loadMentors();
}

onMounted(load);

// Refetch mentor list when URL / query params change on /mentorat
watch(() => route.fullPath, () => { if (route.name === 'mentorat') loadMentors(); });
</script>
