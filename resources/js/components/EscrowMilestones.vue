<template>
    <div class="space-y-4">
        <div v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Chargement des jalons…</div>

        <div v-else-if="!milestones.length"
            class="bg-white dark:bg-slate-800 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl p-8 text-center text-slate-500 dark:text-slate-400">
            Aucun jalon n'a encore été créé pour ce projet.
            <p v-if="canManage" class="text-xs mt-2">Les jalons sont créés automatiquement après le premier investissement.</p>
        </div>

        <article v-for="m in milestones" :key="m.id"
            class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
            <header class="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                            {{ m.position }}
                        </span>
                        <h3 class="font-bold text-slate-900 dark:text-slate-100">{{ m.title }}</h3>
                    </div>
                    <p v-if="m.description" class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ m.description }}</p>
                </div>

                <span class="text-xs font-bold uppercase px-2 py-1 rounded-md whitespace-nowrap" :class="statusClass(m.status)">
                    {{ statusLabel(m.status) }}
                </span>
            </header>

            <div class="grid sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Montant</div>
                    <div class="font-bold text-slate-900 dark:text-slate-100">
                        {{ formatAmount(m.amount, m.currency) }}
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400">({{ m.percentage }}%)</span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Échéance</div>
                    <div class="text-slate-700 dark:text-slate-200">{{ formatDate(m.due_at) || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Libéré le</div>
                    <div class="text-slate-700 dark:text-slate-200">{{ formatDate(m.released_at) || '—' }}</div>
                </div>
            </div>

            <!-- Evidence display -->
            <div v-if="m.evidence && (m.evidence.notes || m.evidence.urls?.length)"
                class="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-sm">
                <div class="text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 mb-1">Preuves de réalisation</div>
                <p v-if="m.evidence.notes" class="text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ m.evidence.notes }}</p>
                <ul v-if="m.evidence.urls?.length" class="mt-1 space-y-0.5">
                    <li v-for="(url, i) in m.evidence.urls" :key="i">
                        <a :href="url" target="_blank" rel="noopener" class="text-emerald-700 dark:text-emerald-400 hover:underline break-all">↗ {{ url }}</a>
                    </li>
                </ul>
            </div>

            <p v-if="m.status === 'rejected' && m.admin_notes" class="mt-3 text-sm text-rose-600 dark:text-rose-400">
                <strong>Refusé :</strong> {{ m.admin_notes }}
            </p>

            <!-- Entrepreneur actions -->
            <div v-if="canManage && ['pending', 'rejected'].includes(m.status)" class="mt-4">
                <button @click="openSubmit(m)"
                    class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                    {{ m.status === 'rejected' ? 'Soumettre à nouveau' : 'Soumettre les preuves' }}
                </button>
            </div>

            <!-- Investor actions -->
            <div v-if="canValidate(m) && m.status === 'in_review'" class="mt-4 flex flex-wrap gap-2">
                <button @click="approve(m)" :disabled="acting === m.id"
                    class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold">
                    ✓ Valider et libérer
                </button>
                <button @click="openReject(m)" :disabled="acting === m.id"
                    class="px-4 py-2 rounded-lg border border-rose-300 dark:border-rose-500/50 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30 text-sm font-semibold">
                    ✕ Refuser
                </button>
            </div>

            <p v-if="m.status === 'in_review' && !canValidate(m)" class="mt-3 text-xs italic text-slate-500 dark:text-slate-400">
                En attente de validation par les investisseurs.
            </p>
        </article>

        <!-- Submit-evidence modal -->
        <Teleport to="body">
            <div v-if="submitting" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="submitting = null">
                <div class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6">
                    <h3 class="text-lg font-bold mb-4 text-slate-900 dark:text-slate-100">
                        Soumettre les preuves — {{ submitting.title }}
                    </h3>
                    <form @submit.prevent="doSubmit" class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Description / explication</label>
                        <textarea v-model="evidenceForm.notes" rows="4" required maxlength="5000"
                            placeholder="Décrivez ce qui a été livré pour ce jalon…"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-emerald-400 focus:outline-none"></textarea>

                        <label class="block text-sm font-semibold text-slate-800 dark:text-slate-200 mt-2">Liens vers les preuves (rapports, photos, vidéos)</label>
                        <div v-for="(_, i) in evidenceForm.urls" :key="i" class="flex gap-2">
                            <input v-model="evidenceForm.urls[i]" type="url" placeholder="https://..."
                                class="flex-1 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />
                            <button type="button" @click="evidenceForm.urls.splice(i, 1)"
                                class="px-2 text-rose-600 dark:text-rose-400">✕</button>
                        </div>
                        <button type="button" @click="evidenceForm.urls.push('')"
                            class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">+ Ajouter un lien</button>

                        <p v-if="actionError" class="text-sm text-rose-600">{{ actionError }}</p>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="submitting = null"
                                class="flex-1 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold">
                                Annuler
                            </button>
                            <button type="submit" :disabled="acting === submitting?.id"
                                class="flex-1 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold">
                                {{ acting === submitting?.id ? 'Envoi…' : 'Soumettre' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reject modal -->
            <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="rejecting = null">
                <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6">
                    <h3 class="text-lg font-bold mb-4 text-slate-900 dark:text-slate-100">Refuser le jalon</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">
                        Le jalon retournera à l'entrepreneur avec votre justification.
                    </p>
                    <form @submit.prevent="doReject" class="space-y-3">
                        <textarea v-model="rejectReason" rows="4" required maxlength="2000"
                            placeholder="Pourquoi refusez-vous ce jalon ?"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm focus:border-rose-400 focus:outline-none"></textarea>

                        <p v-if="actionError" class="text-sm text-rose-600">{{ actionError }}</p>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="rejecting = null"
                                class="flex-1 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold">
                                Annuler
                            </button>
                            <button type="submit" :disabled="acting === rejecting?.id"
                                class="flex-1 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 disabled:opacity-60 text-white text-sm font-semibold">
                                {{ acting === rejecting?.id ? 'Envoi…' : 'Refuser' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    canManage: { type: Boolean, default: false },     // entrepreneur (project owner)
    investorId: { type: [Number, String], default: null }, // current user id, if investor
});

const milestones = ref([]);
const loading = ref(true);
const acting = ref(null);
const actionError = ref(null);

const submitting = ref(null);
const rejecting = ref(null);
const rejectReason = ref('');
const evidenceForm = reactive({ notes: '', urls: [''] });

function statusLabel(s) {
    return ({
        pending:   'À livrer',
        in_review: 'En validation',
        approved:  'Approuvé',
        released:  'Libéré',
        rejected:  'Refusé',
        cancelled: 'Annulé',
    })[s] || s;
}
function statusClass(s) {
    return ({
        pending:   'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200',
        in_review: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        approved:  'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        released:  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        rejected:  'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
        cancelled: 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300',
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

function canValidate(m) {
    return props.investorId && m.investment?.investor_id === Number(props.investorId);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/escrow/projects/${props.projectId}/milestones`);
        milestones.value = data.data || [];
    } catch (e) {
        milestones.value = [];
    } finally {
        loading.value = false;
    }
}

function openSubmit(m) {
    submitting.value = m;
    actionError.value = null;
    evidenceForm.notes = m.evidence?.notes || '';
    evidenceForm.urls = (m.evidence?.urls?.length ? [...m.evidence.urls] : ['']);
}
function openReject(m) {
    rejecting.value = m;
    actionError.value = null;
    rejectReason.value = '';
}

async function doSubmit() {
    if (!submitting.value) return;
    acting.value = submitting.value.id;
    actionError.value = null;
    try {
        const evidence = {
            notes: evidenceForm.notes,
            urls: evidenceForm.urls.filter(Boolean),
        };
        await window.axios.post(`/api/escrow/milestones/${submitting.value.id}/submit`, { evidence });
        submitting.value = null;
        await load();
    } catch (e) {
        actionError.value = e?.response?.data?.message || "Échec de la soumission.";
    } finally {
        acting.value = null;
    }
}

async function approve(m) {
    if (!confirm(`Confirmez la libération de ${formatAmount(m.amount, m.currency)} pour le jalon « ${m.title} » ?`)) return;
    acting.value = m.id;
    actionError.value = null;
    try {
        await window.axios.post(`/api/escrow/milestones/${m.id}/approve`);
        await load();
    } catch (e) {
        actionError.value = e?.response?.data?.message || "Échec de la validation.";
        alert(actionError.value);
    } finally {
        acting.value = null;
    }
}

async function doReject() {
    if (!rejecting.value) return;
    acting.value = rejecting.value.id;
    actionError.value = null;
    try {
        await window.axios.post(`/api/escrow/milestones/${rejecting.value.id}/reject`, { reason: rejectReason.value });
        rejecting.value = null;
        await load();
    } catch (e) {
        actionError.value = e?.response?.data?.message || "Échec du refus.";
    } finally {
        acting.value = null;
    }
}

watch(() => props.projectId, load);
onMounted(load);

defineExpose({ reload: load });
</script>
