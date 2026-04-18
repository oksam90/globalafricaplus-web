<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-10">
            <h1 class="text-3xl md:text-4xl font-black tracking-tight">Secteurs</h1>
            <p class="text-slate-600 dark:text-slate-300 mt-2 max-w-2xl">
                Africa+ couvre tous les secteurs clés du développement économique panafricain.
                Explorez les projets par secteur et découvrez les opportunités.
            </p>
        </header>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400">Chargement…</div>
        <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <router-link v-for="s in sectors" :key="s.id" :to="`/secteurs/${s.slug}`"
                class="group bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 hover:border-emerald-200 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-black text-lg"
                        :style="{ backgroundColor: s.color }">
                        {{ s.name.charAt(0) }}
                    </div>
                    <span class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ s.projects_count }}</span>
                </div>

                <h3 class="mt-4 font-bold text-lg group-hover:text-emerald-700">{{ s.name }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ s.sub_categories?.length || 0 }} sous-secteurs
                </p>

                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <div class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px]">Levés</div>
                        <div class="font-semibold">{{ formatShort(s.total_raised) }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px]">Emplois</div>
                        <div class="font-semibold">{{ s.jobs_target }}</div>
                    </div>
                </div>
            </router-link>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const sectors = ref([]);
const loading = ref(true);

function formatShort(n) {
    const v = parseFloat(n) || 0;
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M €';
    if (v >= 1_000) return Math.round(v / 1_000) + 'k €';
    return v + ' €';
}

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/sectors');
        sectors.value = data.data || [];
    } finally {
        loading.value = false;
    }
});
</script>
