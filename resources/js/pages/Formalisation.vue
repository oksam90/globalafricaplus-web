<template>
    <div>
        <!-- Hero -->
        <section class="bg-gradient-to-br from-emerald-50 via-teal-50 to-green-50 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <div class="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-2">Hub de formalisation</div>
                    <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-900">
                        Formalisez votre entreprise en <span class="text-emerald-600">Afrique</span>
                    </h1>
                    <p class="mt-4 text-lg text-slate-600">
                        Guide pas-à-pas adapté à chaque pays pour passer de l'informel au formel : création d'entreprise,
                        business plan, et accès au micro-crédit.
                    </p>
                    <div class="flex flex-wrap gap-3 mt-6">
                        <router-link v-if="auth.user" to="/formalisation/mon-parcours"
                            class="px-6 py-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                            Mon parcours de formalisation
                        </router-link>
                        <router-link to="/formalisation/business-plans"
                            class="px-6 py-3 rounded-md border border-emerald-300 text-emerald-700 font-semibold hover:bg-emerald-50">
                            Templates Business Plan
                        </router-link>
                    </div>
                </div>
                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mt-10 max-w-2xl">
                    <div class="text-center">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.countries || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 mt-1">Pays couverts</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.templates || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 mt-1">Templates BP</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.partners || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 mt-1">Partenaires microfinance</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.users_formalized || 0 }}</div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 mt-1">Entrepreneurs accompagnés</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="bg-white border-y border-slate-100 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-bold text-center mb-8">Parcours de formalisation en 4 étapes</h2>
                <div class="grid md:grid-cols-4 gap-6">
                    <div v-for="(step, i) in howItWorks" :key="i" class="text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 flex items-center justify-center text-2xl mb-3">{{ step.icon }}</div>
                        <h3 class="font-semibold text-sm">{{ step.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Country guides -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h2 class="text-2xl font-bold mb-6">Parcours par pays</h2>
            <div v-if="loading" class="text-slate-500">Chargement…</div>
            <div v-else class="grid md:grid-cols-3 gap-6">
                <div v-for="c in countries" :key="c.country"
                    @click="selectCountry(c.country)"
                    class="bg-white border rounded-2xl p-6 cursor-pointer hover:border-emerald-300 transition"
                    :class="selectedCountry === c.country ? 'border-emerald-500 ring-2 ring-emerald-200' : 'border-slate-100'">
                    <h3 class="text-lg font-bold">{{ c.country }}</h3>
                    <p class="text-sm text-slate-500 mt-1">{{ c.steps_count }} étapes de formalisation</p>
                    <div class="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
            </div>

            <!-- Steps for selected country -->
            <div v-if="selectedCountry && steps.length" class="mt-10">
                <h3 class="text-xl font-bold mb-6">
                    Guide de formalisation — {{ selectedCountry }}
                </h3>
                <div class="space-y-4">
                    <div v-for="step in steps" :key="step.id"
                        class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
                        <div @click="toggleStep(step.id)" class="flex items-center gap-4 p-5 cursor-pointer hover:bg-slate-50">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold shrink-0">
                                {{ step.order }}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold">{{ step.title }}</h4>
                                <div class="flex flex-wrap gap-3 mt-1 text-xs text-slate-500">
                                    <span v-if="step.institution">🏛️ {{ step.institution }}</span>
                                    <span v-if="step.estimated_duration">⏱️ {{ step.estimated_duration }}</span>
                                    <span v-if="step.estimated_cost">💰 {{ step.estimated_cost }}</span>
                                </div>
                            </div>
                            <span class="text-slate-400">{{ openSteps.has(step.id) ? '▾' : '▸' }}</span>
                        </div>
                        <div v-if="openSteps.has(step.id)" class="border-t border-slate-100 p-5 bg-slate-50/50">
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ step.description }}</p>
                            <div v-if="step.required_documents?.length" class="mt-4">
                                <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Documents requis</h5>
                                <ul class="space-y-1">
                                    <li v-for="(doc, i) in step.required_documents" :key="i" class="text-sm text-slate-600 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>{{ doc }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="step.tips" class="mt-4 bg-amber-50 border border-amber-100 rounded-xl p-3">
                                <p class="text-xs text-amber-800"><strong>💡 Conseil :</strong> {{ step.tips }}</p>
                            </div>
                            <a v-if="step.link" :href="step.link" target="_blank"
                                class="inline-block mt-3 text-sm text-emerald-600 font-semibold hover:underline">
                                Lien officiel →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Microfinance partners -->
        <section class="bg-slate-50 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold mb-2">Partenaires microfinance</h2>
                <p class="text-slate-600 mb-6">Accédez au financement adapté à votre stade de développement.</p>
                <div v-if="partners.length" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="p in filteredPartners" :key="p.id" class="bg-white border border-slate-100 rounded-2xl p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-bold">{{ p.name }}</h3>
                                <span class="text-xs text-slate-500">{{ p.country }} · {{ p.type }}</span>
                            </div>
                        </div>
                        <p v-if="p.description" class="text-sm text-slate-600 line-clamp-2">{{ p.description }}</p>
                        <div v-if="p.products?.length" class="flex flex-wrap gap-1 mt-3">
                            <span v-for="prod in p.products" :key="prod"
                                class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                                {{ prod }}
                            </span>
                        </div>
                        <div class="mt-3 text-xs text-slate-500 space-y-1">
                            <div v-if="p.min_amount">Montant : {{ p.min_amount }} — {{ p.max_amount }}</div>
                            <div v-if="p.interest_rate">Taux : {{ p.interest_rate }}</div>
                        </div>
                        <a v-if="p.website" :href="p.website" target="_blank"
                            class="inline-block mt-3 text-xs text-emerald-600 font-semibold hover:underline">
                            Visiter le site →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-emerald-700 py-12">
            <div class="max-w-3xl mx-auto px-4 text-center text-white">
                <h2 class="text-2xl font-bold">Prêt à formaliser votre entreprise ?</h2>
                <p class="mt-2 text-emerald-100">Créez votre profil entrepreneur, suivez le guide adapté à votre pays et accédez aux templates de business plan gratuits.</p>
                <div class="flex justify-center gap-3 mt-6">
                    <router-link to="/inscription" class="px-6 py-3 rounded-md bg-white text-emerald-700 font-semibold hover:bg-emerald-50">
                        Créer mon compte
                    </router-link>
                    <router-link to="/formalisation/business-plans" class="px-6 py-3 rounded-md border border-white/40 text-white font-semibold hover:bg-white/10">
                        Templates Business Plan
                    </router-link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const stats = ref({});
const countries = ref([]);
const steps = ref([]);
const partners = ref([]);
const loading = ref(true);
const selectedCountry = ref(null);
const openSteps = reactive(new Set());

const howItWorks = [
    { icon: '📋', title: 'Choisissez votre pays', desc: 'Le parcours s\'adapte à la réglementation locale (RG-029).' },
    { icon: '📜', title: 'Suivez le guide', desc: 'Étape par étape : statuts, RCCM, NINEA, patente, compte bancaire.' },
    { icon: '📝', title: 'Business plan gratuit', desc: 'Utilisez nos templates adaptés aux réalités africaines.' },
    { icon: '💰', title: 'Accès au financement', desc: 'Mise en relation avec des partenaires microfinance.' },
];

const filteredPartners = computed(() =>
    selectedCountry.value
        ? partners.value.filter(p => p.country === selectedCountry.value)
        : partners.value
);

function toggleStep(id) {
    openSteps.has(id) ? openSteps.delete(id) : openSteps.add(id);
}

async function selectCountry(country) {
    selectedCountry.value = country;
    try {
        const { data } = await window.axios.get(`/api/formalisation/countries/${encodeURIComponent(country)}/steps`);
        steps.value = data.data || [];
    } catch { steps.value = []; }
}

async function loadData() {
    loading.value = true;
    try {
        const [statsRes, countriesRes, partnersRes] = await Promise.all([
            window.axios.get('/api/formalisation/stats'),
            window.axios.get('/api/formalisation/countries'),
            window.axios.get('/api/formalisation/partners'),
        ]);
        stats.value = statsRes.data;
        countries.value = countriesRes.data.data || [];
        partners.value = partnersRes.data.data || [];

        // Auto-select first country
        if (countries.value.length) {
            await selectCountry(countries.value[0].country);
        }
    } finally {
        loading.value = false;
    }
}

onMounted(loadData);
</script>
