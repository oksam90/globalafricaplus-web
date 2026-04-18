<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes projets</h1>
                <p class="text-slate-600 mt-1">Gérez vos brouillons, publications et actualités.</p>
            </div>
            <router-link to="/projets/nouveau"
                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                + Nouveau projet
            </router-link>
        </header>

        <div v-if="loading" class="text-slate-500">Chargement…</div>
        <div v-else-if="!projects.length" class="bg-white border border-dashed border-slate-200 rounded-2xl p-12 text-center">
            <div class="text-4xl mb-3">🚀</div>
            <h2 class="font-bold text-lg">Aucun projet pour le moment</h2>
            <p class="text-slate-600 mt-1">Lancez votre première initiative sur Africa+.</p>
            <router-link to="/projets/nouveau" class="mt-5 inline-block px-5 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                Créer un projet
            </router-link>
        </div>

        <div v-else class="grid gap-4">
            <article v-for="p in projects" :key="p.id"
                class="bg-white border border-slate-100 rounded-2xl p-5 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase px-2 py-0.5 rounded" :class="statusClass(p.status)">
                            {{ statusLabel(p.status) }}
                        </span>
                        <span v-if="p.category" class="text-xs font-semibold text-slate-500">{{ p.category.name }}</span>
                    </div>
                    <router-link :to="`/projets/${p.slug}`" class="font-bold text-slate-900 hover:text-emerald-700">
                        {{ p.title }}
                    </router-link>
                    <p class="text-sm text-slate-600 line-clamp-1">{{ p.summary }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4 text-xs text-slate-600 min-w-[220px]">
                    <div>
                        <div class="uppercase text-[10px] text-slate-400">Vues</div>
                        <div class="font-semibold">{{ p.views_count }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-[10px] text-slate-400">Suivis</div>
                        <div class="font-semibold">{{ p.followers_count }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-[10px] text-slate-400">Actus</div>
                        <div class="font-semibold">{{ p.updates_count }}</div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <router-link :to="`/projets/${p.slug}/modifier`"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md border border-slate-200 hover:border-emerald-300">
                        Éditer
                    </router-link>
                    <button v-if="p.status !== 'published'" @click="publish(p)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                        Publier
                    </button>
                    <button @click="remove(p)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md text-rose-600 hover:bg-rose-50">
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
        draft: 'bg-slate-100 text-slate-700',
        pending: 'bg-amber-100 text-amber-800',
        published: 'bg-emerald-100 text-emerald-800',
        closed: 'bg-slate-200 text-slate-600',
        funded: 'bg-blue-100 text-blue-800',
        rejected: 'bg-rose-100 text-rose-700',
    })[s] || 'bg-slate-100 text-slate-700';
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
