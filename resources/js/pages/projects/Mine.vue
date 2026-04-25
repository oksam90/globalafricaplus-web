<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes projets</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Gérez vos brouillons, publications et actualités.</p>
            </div>
            <router-link to="/projets/nouveau"
                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                + Nouveau projet
            </router-link>
        </header>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400">Chargement…</div>
        <div v-else-if="!projects.length" class="bg-white dark:bg-slate-800 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center">
            <div class="text-4xl mb-3">🚀</div>
            <h2 class="font-bold text-lg">Aucun projet pour le moment</h2>
            <p class="text-slate-600 dark:text-slate-300 mt-1">Lancez votre première initiative sur Africa+.</p>
            <router-link to="/projets/nouveau" class="mt-5 inline-block px-5 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                Créer un projet
            </router-link>
        </div>

        <div v-else class="grid gap-4">
            <article v-for="p in projects" :key="p.id"
                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase px-2 py-0.5 rounded" :class="statusClass(p.status)">
                            {{ statusLabel(p.status) }}
                        </span>
                        <span v-if="p.category" class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ p.category.name }}</span>
                    </div>
                    <router-link :to="`/projets/${p.slug}`" class="font-bold text-slate-900 dark:text-slate-100 hover:text-emerald-700 dark:hover:text-emerald-400">
                        {{ p.title }}
                    </router-link>
                    <p class="text-sm text-slate-600 dark:text-slate-300 line-clamp-1">{{ p.summary }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4 text-xs text-slate-600 dark:text-slate-300 min-w-[220px]">
                    <div>
                        <div class="uppercase text-[10px] text-slate-400 dark:text-slate-500">Vues</div>
                        <div class="font-semibold">{{ p.views_count }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-[10px] text-slate-400 dark:text-slate-500">Suivis</div>
                        <div class="font-semibold">{{ p.followers_count }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-[10px] text-slate-400 dark:text-slate-500">Actus</div>
                        <div class="font-semibold">{{ p.updates_count }}</div>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap">
                    <router-link :to="`/projets/${p.slug}/modifier`"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 text-slate-700 dark:text-slate-200">
                        Éditer
                    </router-link>
                    <button v-if="p.status !== 'published'" @click="publish(p)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60">
                        Publier
                    </button>
                    <button @click="remove(p)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30">
                        Supprimer
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const projects = ref([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/me/projects');
        projects.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function statusLabel(s) {
    return ({ draft: 'Brouillon', pending: 'En attente', published: 'Publié', closed: 'Clos', rejected: 'Refusé', funded: 'Financé' })[s] || s;
}
function statusClass(s) {
    return ({
        draft: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200',
        pending: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300',
        published: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
        closed: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
        funded: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
        rejected: 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300',
    })[s] || 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200';
}

async function publish(p) {
    try {
        await window.axios.post(`/api/projects/${p.id}/publish`);
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Impossible de publier');
    }
}
async function remove(p) {
    if (!confirm(`Supprimer définitivement "${p.title}" ?`)) return;
    await window.axios.delete(`/api/projects/${p.id}`);
    await load();
}

onMounted(load);
</script>
