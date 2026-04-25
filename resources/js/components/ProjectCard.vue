<template>
    <router-link :to="{ name: 'projects.show', params: { slug: project.slug } }"
        class="block bg-white dark:bg-slate-800 dark:bg-brand-black-50 rounded-2xl border border-brand-gold-200/50 dark:border-brand-gold-700/20 overflow-hidden hover:border-brand-gold-400 dark:hover:border-brand-gold-500/60 hover:shadow-md transition group">
        <div class="h-32 bg-gradient-to-br relative" :style="`background-image: linear-gradient(135deg, ${color}, #0f172a)`">
            <span class="absolute top-3 left-3 text-xs font-semibold px-2 py-1 rounded-md bg-white/95 dark:bg-slate-900/80 text-slate-800 dark:text-slate-100 backdrop-blur-sm shadow-sm">
                {{ project.category?.name || 'Projet' }}
            </span>
            <span class="absolute top-3 right-3 text-xs font-semibold px-2 py-1 rounded-md bg-black/40 text-white backdrop-blur">
                {{ project.country }}
            </span>
            <span v-if="project.stage"
                class="absolute bottom-3 left-3 text-[10px] font-bold uppercase px-2 py-1 rounded bg-white/95 dark:bg-slate-900/80 text-slate-700 dark:text-slate-100 backdrop-blur-sm shadow-sm">
                {{ stageLabel }}
            </span>
        </div>
        <div class="p-5">
            <h3 class="font-bold text-slate-900 dark:text-slate-100 dark:text-white group-hover:text-brand-gold-700 dark:group-hover:text-brand-gold-400 transition line-clamp-1">
                {{ project.title }}
            </h3>
            <p v-if="project.sub_category" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ project.sub_category.name }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300 dark:text-slate-400 mt-1.5 line-clamp-2">{{ project.summary }}</p>

            <div v-if="project.sdgs?.length" class="flex flex-wrap gap-1 mt-3">
                <span v-for="s in project.sdgs.slice(0, 4)" :key="s.id"
                    class="inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold text-white"
                    :style="{ backgroundColor: s.color }" :title="`ODD ${s.number} — ${s.name}`">
                    {{ s.number }}
                </span>
            </div>

            <div class="mt-4">
                <div class="flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300 dark:text-slate-400 mb-1">
                    <span>{{ formatAmount(project.amount_raised) }}</span>
                    <span>{{ progress }}%</span>
                </div>
                <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-brand-gold-500 to-brand-red-500" :style="{ width: progress + '%' }"></div>
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>objectif {{ formatAmount(project.amount_needed) }}</span>
                    <span v-if="project.followers_count !== undefined">♥ {{ project.followers_count }}</span>
                </div>
            </div>
        </div>
    </router-link>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ project: { type: Object, required: true } });

const progress = computed(() => {
    const need = parseFloat(props.project.amount_needed) || 0;
    const raised = parseFloat(props.project.amount_raised) || 0;
    if (need <= 0) return 0;
    return Math.min(100, Math.round((raised / need) * 100));
});
const color = computed(() => props.project?.category?.color || '#10b981');
const stageLabel = computed(() =>
    ({ idea: 'Idée', mvp: 'MVP', launch: 'Lancement', scaling: 'Croissance' })[props.project.stage] || ''
);

function formatAmount(v) {
    const n = parseFloat(v) || 0;
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n);
}
</script>
