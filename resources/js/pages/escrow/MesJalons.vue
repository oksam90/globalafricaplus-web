<template>
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-8">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Mes jalons à valider</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">
                Validez ou refusez les jalons soumis par les porteurs de projets que vous avez financés.
                Une validation déclenche la libération automatique des fonds depuis le séquestre.
            </p>
        </header>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400">Chargement…</div>

        <div v-else-if="!milestones.length"
            class="bg-white dark:bg-slate-800 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center text-slate-500 dark:text-slate-400">
            Aucun jalon en attente. Vous serez notifié dès qu'un porteur de projet soumet une preuve de réalisation.
        </div>

        <div v-else class="space-y-4">
            <article v-for="m in milestones" :key="m.id"
                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 hover:shadow-md transition">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div class="flex items-start gap-3">
                        <img v-if="m.project?.cover_image" :src="m.project.cover_image" alt=""
                            class="w-14 h-14 rounded-lg object-cover" />
                        <div v-else class="w-14 h-14 rounded-lg bg-emerald-100 dark:bg-emerald-900/30"></div>
                        <div>
                            <router-link :to="`/projets/${m.project?.slug}`"
                                class="font-bold text-slate-900 dark:text-slate-100 hover:text-emerald-700 dark:hover:text-emerald-400">
                                {{ m.project?.title || `Projet #${m.project_id}` }}
                            </router-link>
                            <div class="text-sm text-slate-600 dark:text-slate-300">
                                Jalon {{ m.position }} — {{ m.title }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {{ formatAmount(m.amount, m.currency) }} ({{ m.percentage }}%)
                                · échéance {{ formatDate(m.due_at) || '—' }}
                            </div>
                        </div>
                    </div>

                    <span class="text-xs font-bold uppercase px-2 py-1 rounded-md whitespace-nowrap" :class="statusClass(m.status)">
                        {{ statusLabel(m.status) }}
                    </span>
                </div>

                <router-link :to="`/projets/${m.project?.slug}?tab=milestones`"
                    class="inline-block text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">
                    Voir le détail et valider →
                </router-link>
            </article>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const milestones = ref([]);
const loading = ref(true);

function statusLabel(s) {
    return ({
        pending:   'À livrer',
        in_review: 'À valider',
        approved:  'En libération',
        released:  'Libéré',
        rejected:  'Refusé',
    })[s] || s;
}
function statusClass(s) {
    return ({
        pending:   'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200',
        in_review: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        approved:  'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        released:  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        rejected:  'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
    })[s] || 'bg-slate-100 text-slate-700';
}
function formatAmount(v, cur) {
    const c = (cur || 'EUR').toUpperCase();
    try { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: c, maximumFractionDigits: 0 }).format(parseFloat(v) || 0); }
    catch { return `${parseFloat(v) || 0} ${c}`; }
}
function formatDate(d) {
    return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/escrow/milestones/mine');
        milestones.value = data.data || [];
    } catch (e) {
        milestones.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
