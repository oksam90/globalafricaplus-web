<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Templates de Business Plan</h1>
                <p class="text-slate-600 mt-1">Modèles gratuits adaptés aux réalités africaines (RG-030).</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/formalisation" class="text-sm font-semibold text-emerald-600 hover:underline">Hub formalisation →</router-link>
                <router-link v-if="auth.user" to="/formalisation/mon-parcours" class="text-sm font-semibold text-sky-600 hover:underline">Mon parcours →</router-link>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-8">
            <button @click="sectorFilter = ''" class="px-4 py-2 rounded-full text-sm font-medium border"
                :class="!sectorFilter ? 'bg-emerald-600 text-white border-emerald-600' : 'border-slate-200 hover:bg-slate-50'">
                Tous
            </button>
            <button v-for="s in sectors" :key="s" @click="sectorFilter = s"
                class="px-4 py-2 rounded-full text-sm font-medium border capitalize"
                :class="sectorFilter === s ? 'bg-emerald-600 text-white border-emerald-600' : 'border-slate-200 hover:bg-slate-50'">
                {{ sectorLabels[s] || s }}
            </button>
        </div>

        <div v-if="loading" class="text-slate-500 py-8">Chargement…</div>
        <template v-else>
            <!-- Templates grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="tpl in filtered" :key="tpl.id"
                    class="bg-white border border-slate-100 rounded-2xl p-6 hover:border-emerald-200 transition">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold capitalize">
                            {{ sectorLabels[tpl.sector] || tpl.sector }}
                        </span>
                        <span v-if="tpl.is_free" class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 font-semibold">
                            Gratuit
                        </span>
                    </div>
                    <h3 class="font-bold text-lg">{{ tpl.title }}</h3>
                    <p class="text-sm text-slate-600 mt-1 line-clamp-2">{{ tpl.description }}</p>

                    <div class="flex items-center gap-3 mt-3 text-xs text-slate-500">
                        <span>{{ tpl.sections?.length || 0 }} sections</span>
                        <span>{{ tpl.downloads_count }} téléchargements</span>
                    </div>

                    <button @click="openTemplate(tpl)" class="mt-4 w-full px-4 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                        Voir le template
                    </button>
                </div>
            </div>

            <!-- No results -->
            <div v-if="filtered.length === 0" class="text-center py-16 text-slate-500 bg-slate-50 rounded-2xl">
                Aucun template pour ce secteur.
            </div>
        </template>

        <!-- Template detail modal -->
        <div v-if="selectedTemplate" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="selectedTemplate = null">
            <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[85vh] overflow-y-auto p-8">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold capitalize">
                                {{ sectorLabels[selectedTemplate.sector] || selectedTemplate.sector }}
                            </span>
                            <span class="text-xs text-slate-500">{{ selectedTemplate.downloads_count }} téléchargements</span>
                        </div>
                        <h2 class="text-2xl font-bold">{{ selectedTemplate.title }}</h2>
                        <p class="text-slate-600 mt-1">{{ selectedTemplate.description }}</p>
                    </div>
                    <button @click="selectedTemplate = null" class="text-slate-400 hover:text-slate-700 text-2xl font-bold">&times;</button>
                </div>

                <div class="space-y-6">
                    <div v-for="(section, i) in selectedTemplate.sections" :key="i"
                        class="bg-slate-50 border border-slate-100 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-sm">
                                {{ i + 1 }}
                            </span>
                            <h3 class="font-bold">{{ section.title }}</h3>
                        </div>
                        <p class="text-sm text-slate-600 italic">{{ section.prompt }}</p>
                    </div>
                </div>

                <div class="mt-6 bg-sky-50 border border-sky-100 rounded-xl p-4">
                    <p class="text-xs text-sky-700">
                        <strong>💡 Astuce :</strong> Utilisez ce template comme guide structurant. Chaque section contient un prompt
                        pour vous aider à rédiger. Adaptez le contenu à votre projet et à votre marché cible.
                    </p>
                </div>

                <button @click="selectedTemplate = null" class="mt-4 px-6 py-2.5 rounded-md border border-slate-200 hover:bg-slate-50 font-semibold text-sm">
                    Fermer
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const templates = ref([]);
const loading = ref(true);
const sectorFilter = ref('');
const selectedTemplate = ref(null);

const sectorLabels = {
    agriculture: 'Agriculture & Agritech',
    commerce: 'Commerce & Retail',
    artisanat: 'Artisanat & Production',
    services: 'Services & Consulting',
    tech: 'Tech & Innovation',
};

const sectors = computed(() => [...new Set(templates.value.map(t => t.sector))]);
const filtered = computed(() =>
    sectorFilter.value ? templates.value.filter(t => t.sector === sectorFilter.value) : templates.value
);

async function openTemplate(tpl) {
    try {
        const { data } = await window.axios.get(`/api/formalisation/templates/${tpl.slug}`);
        selectedTemplate.value = data.data;
    } catch {
        selectedTemplate.value = tpl;
    }
}

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/formalisation/templates');
        templates.value = data.data || [];
    } finally {
        loading.value = false;
    }
});
</script>
