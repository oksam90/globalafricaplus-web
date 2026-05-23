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
                <span v-if="project.sub_category" class="text-xs font-semibold px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                    {{ project.sub_category.name }}
                </span>
                <span class="text-xs font-semibold px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
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
            <h1 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-slate-100">{{ project.title }}</h1>
            <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">{{ project.summary }}</p>
        </header>

        <!-- Tabs + Sidebar -->
        <div class="mt-8 grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <!-- Tab nav -->
                <div class="border-b border-slate-200 dark:border-slate-700 mb-6 flex gap-6 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.id" @click="tab = t.id"
                        class="px-1 py-3 -mb-px text-sm font-semibold whitespace-nowrap border-b-2 transition"
                        :class="tab === t.id
                            ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                            : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'">
                        {{ t.label }}
                        <span v-if="t.count !== undefined" class="ml-1 text-xs text-slate-400 dark:text-slate-500">{{ t.count }}</span>
                    </button>
                </div>

                <!-- About -->
                <div v-if="tab === 'about'" class="space-y-6">
                    <section>
                        <h2 class="text-lg font-bold mb-2 text-slate-900 dark:text-slate-100">À propos du projet</h2>
                        <p class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ project.description || 'Aucune description fournie.' }}</p>
                    </section>

                    <section v-if="project.sdgs?.length">
                        <h2 class="text-lg font-bold mb-2 text-slate-900 dark:text-slate-100">Objectifs de Développement Durable</h2>
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
                        <h2 class="text-lg font-bold mb-2 text-slate-900 dark:text-slate-100">Tags</h2>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in project.tags" :key="t" class="px-2.5 py-1 text-xs rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                #{{ t }}
                            </span>
                        </div>
                    </section>

                    <section v-if="project.website || project.video_url || project.pitch_deck_url" class="space-y-2">
                        <h2 class="text-lg font-bold mb-2 text-slate-900 dark:text-slate-100">Liens</h2>
                        <ul class="text-sm space-y-1">
                            <li v-if="project.website"><a :href="project.website" target="_blank" class="text-emerald-700 dark:text-emerald-400 hover:underline">🌐 Site web</a></li>
                            <li v-if="project.video_url"><a :href="project.video_url" target="_blank" class="text-emerald-700 dark:text-emerald-400 hover:underline">🎬 Vidéo de présentation</a></li>
                            <li v-if="project.pitch_deck_url"><a :href="project.pitch_deck_url" target="_blank" class="text-emerald-700 dark:text-emerald-400 hover:underline">📊 Pitch deck</a></li>
                        </ul>
                    </section>
                </div>

                <!-- Updates -->
                <div v-else-if="tab === 'updates'">
                    <div v-if="canEdit" class="mb-6 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                        <h3 class="font-bold mb-3 text-slate-900 dark:text-slate-100">Publier une actualité</h3>
                        <form @submit.prevent="postUpdate" class="space-y-3">
                            <input v-model="updateForm.title" type="text" placeholder="Titre" required maxlength="150"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-emerald-400 focus:outline-none text-sm" />
                            <textarea v-model="updateForm.body" rows="4" placeholder="Contenu…" required maxlength="5000"
                                class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-emerald-400 focus:outline-none text-sm"></textarea>
                            <button :disabled="posting" class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-60">
                                {{ posting ? 'Publication…' : 'Publier' }}
                            </button>
                        </form>
                    </div>

                    <div v-if="!project.updates?.length" class="text-center py-10 text-slate-500">
                        Aucune actualité pour le moment.
                    </div>
                    <div v-else class="space-y-4">
                        <article v-for="u in project.updates" :key="u.id" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold text-slate-900 dark:text-slate-100">{{ u.title }}</h3>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(u.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-line text-slate-700 dark:text-slate-300 text-sm">{{ u.body }}</p>
                            <p v-if="u.author" class="mt-3 text-xs text-slate-500 dark:text-slate-400">par {{ u.author.name }}</p>
                        </article>
                    </div>
                </div>

                <!-- Milestones / escrow -->
                <div v-else-if="tab === 'milestones'">
                    <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                        Les fonds des investisseurs sont conservés en séquestre et libérés au porteur de projet à la validation de chaque jalon.
                    </p>
                    <EscrowMilestones
                        :project-id="project.id"
                        :can-manage="canEdit"
                        :investor-id="auth.user?.id || null" />
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
            <aside class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6 h-fit lg:sticky lg:top-20">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ formatAmount(project.amount_raised) }}</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">levés sur {{ formatAmount(project.amount_needed) }}</div>

                <div class="mt-4 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500" :style="{ width: progress + '%' }"></div>
                </div>
                <div class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">{{ progress }}% financé</div>

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Emplois ciblés</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ project.jobs_target || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Porteur</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ project.user?.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Suivis</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ project.followers_count || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Actualités</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ project.updates_count || project.updates?.length || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Vues</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ project.views_count }}</dd>
                    </div>
                </dl>

                <button @click="openInvestModal" :disabled="investing"
                    class="mt-6 w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                    {{ investing ? 'Redirection…' : 'Investir dans ce projet' }}
                </button>

                <button v-if="auth.isAuthenticated" @click="toggleFollow"
                    class="mt-2 w-full px-4 py-2.5 rounded-lg border font-semibold transition"
                    :class="isFollowing
                        ? 'border-rose-300 dark:border-rose-500/60 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30'
                        : 'border-slate-200 dark:border-slate-600 hover:border-emerald-300 dark:hover:border-emerald-500/60 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-700'">
                    {{ isFollowing ? '♥ Suivi' : '♡ Suivre' }}
                </button>

                <router-link v-if="canEdit" :to="`/projets/${project.slug}/modifier`"
                    class="mt-2 block text-center w-full px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-emerald-300 dark:hover:border-emerald-500/60 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold">
                    ✎ Modifier
                </router-link>
            </aside>
        </div>
    </article>
    <div v-else class="max-w-5xl mx-auto p-12 text-slate-500">Projet introuvable.</div>

    <!-- Investment modal -->
    <Modal v-model="showInvestModal" size="md" title="Investir dans ce projet">
        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
            Vous investissez dans <strong>{{ project?.title }}</strong>. Les fonds sont sécurisés en
            <em>escrow</em> et libérés au fur et à mesure de l'atteinte des jalons du projet.
        </p>

        <form id="invest-form" @submit.prevent="submitInvestment" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Montant ({{ projectCurrency }})</label>
                        <input v-model.number="investForm.amount" type="number" min="1" step="1" required
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 focus:border-emerald-400 focus:outline-none text-sm" />
                        <p v-if="projectCurrency === 'EUR' && investForm.amount" class="mt-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                            &asymp; {{ formatXof(investForm.amount) }} seront débités via PayDunya
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Type</label>
                        <select v-model="investForm.type"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 focus:border-emerald-400 focus:outline-none text-sm">
                            <option value="equity">Participation (equity)</option>
                            <option value="donation">Don</option>
                            <option value="loan">Prêt</option>
                            <option value="reward">Contrepartie (reward)</option>
                        </select>
                    </div>

                    <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 space-y-2">
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                            <input type="checkbox" v-model="splitPayment"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            Étaler en plusieurs paiements
                        </label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Disponible jusqu'à 12 échéances. Vous payez la 1<sup>re</sup> tranche maintenant ; les suivantes seront facturées automatiquement.
                        </p>

                        <div v-if="splitPayment" class="grid grid-cols-2 gap-2 pt-1">
                            <div>
                                <label class="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">Échéances</label>
                                <select v-model.number="investForm.installments"
                                    class="w-full px-2 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                                    <option v-for="n in [2,3,4,6,12]" :key="n" :value="n">{{ n }} fois</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">Fréquence</label>
                                <select v-model="investForm.frequency"
                                    class="w-full px-2 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                                    <option value="weekly">Hebdomadaire</option>
                                    <option value="biweekly">Bimensuelle</option>
                                    <option value="monthly">Mensuelle</option>
                                </select>
                            </div>
                            <p v-if="installmentPreview" class="col-span-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                Soit {{ installmentPreview }} par échéance.
                            </p>
                        </div>
                    </div>

                    <!-- Structured error: subscription / KYC / generic -->
                    <div v-if="investError" class="rounded-lg border p-3 text-sm"
                        :class="investError.kind === 'subscription'
                            ? 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200'
                            : investError.kind === 'kyc'
                                ? 'bg-orange-50 dark:bg-orange-900/30 border-orange-200 dark:border-orange-800/60 text-orange-900 dark:text-orange-200'
                                : 'bg-rose-50 dark:bg-rose-900/30 border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-200'">
                        <div class="flex items-start gap-2">
                            <span class="text-base shrink-0">
                                {{ investError.kind === 'subscription' ? '💳' : investError.kind === 'kyc' ? '🪪' : '⚠️' }}
                            </span>
                            <div class="flex-1">
                                <div>{{ investError.message }}</div>
                                <div v-if="investError.kind" class="mt-2 flex gap-2">
                                    <router-link v-if="investError.kind === 'subscription'" to="/tarifs"
                                        class="text-xs font-bold underline">
                                        Voir les packs →
                                    </router-link>
                                    <router-link v-if="investError.kind === 'kyc'" to="/kyc"
                                        class="text-xs font-bold underline">
                                        Compléter mon KYC →
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>

        </form>

        <template #footer="{ close }">
            <button type="button" @click="close"
                class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">
                Annuler
            </button>
            <button type="submit" form="invest-form" :disabled="investing || isInvestBlocked"
                class="flex-1 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold">
                {{ investing ? 'En cours…' : 'Payer' }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import ProjectCard from '../../components/ProjectCard.vue';
import EscrowMilestones from '../../components/EscrowMilestones.vue';
import Modal from '../../components/Modal.vue';

// Declare `slug` because the route is registered with `props: true`. Without
// this, Vue logs an "Extraneous non-props attributes (slug)" warning because
// the template has multiple root nodes (article + Teleport) and can't fall
// back to attribute inheritance.
defineProps({
    slug: { type: String, default: null },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const showInvestModal = ref(false);
const investing = ref(false);
const investError = ref('');
const investForm = reactive({ amount: 100, type: 'equity', installments: 3, frequency: 'monthly' });
const splitPayment = ref(false);

/** Disables the Payer submit when the user is pre-known to be ineligible. */
const isInvestBlocked = computed(() =>
    investError.value?.kind === 'subscription' || investError.value?.kind === 'kyc'
);

const installmentPreview = computed(() => {
    if (!splitPayment.value || !investForm.amount || !investForm.installments) return '';
    const per = (investForm.amount / investForm.installments);
    try {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: projectCurrency.value,
            maximumFractionDigits: 2,
        }).format(per);
    } catch {
        return `${per.toFixed(2)} ${projectCurrency.value}`;
    }
});

const EUR_TO_XOF = 655.957;
const xofFmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 });
function formatXof(eur) {
    const n = parseFloat(eur) || 0;
    return xofFmt.format(Math.round(n * EUR_TO_XOF));
}

const projectCurrency = computed(() => (project.value?.currency || 'EUR').toUpperCase());

function openInvestModal() {
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }

    // Audit fix 2026-05 — pre-check on the client so the user is not
    // surprised by a 403 after filling out the form. Server-side gating
    // remains the source of truth.
    investError.value = null;
    showInvestModal.value = true;

    if (!auth.canInvest) {
        investError.value = {
            kind: 'subscription',
            message: 'L\'investissement est réservé aux abonnements Pro et Entreprise. Mettez votre abonnement à niveau pour participer au financement de ce projet.',
        };
    } else if (auth.amlStatus === 'blocked') {
        investError.value = {
            kind: 'kyc',
            message: 'Votre compte est bloqué pour conformité AML. Contactez le support avant toute opération.',
        };
    } else if (auth.amlStatus === 'flagged') {
        investError.value = {
            kind: 'kyc',
            message: 'Votre dossier AML est en cours de revue. Vous pourrez investir dès la levée du flag.',
        };
    } else if (!auth.amlCompleted) {
        investError.value = {
            kind: 'kyc',
            message: 'Votre profil investisseur n\'est pas encore validé : un screening AML est requis. Complétez votre vérification KYC/AML pour investir.',
        };
    } else if (!auth.isKycVerified) {
        investError.value = {
            kind: 'kyc',
            message: 'Une vérification d\'identité (KYC) est obligatoire avant tout investissement.',
        };
    }
}

async function submitInvestment() {
    if (!project.value || !investForm.amount || investForm.amount <= 0) {
        investError.value = { message: 'Montant invalide.' };
        return;
    }
    investing.value = true;
    investError.value = null;
    try {
        const payload = {
            project_id: project.value.id,
            amount: investForm.amount,
            type: investForm.type,
            country: auth.user?.country || 'SN',
        };
        if (splitPayment.value && investForm.installments > 1) {
            payload.installments = investForm.installments;
            payload.frequency = investForm.frequency;
        }
        const { data } = await window.axios.post('/api/investments', payload);

        if ((data.status === 'checkout_required' || data.status === 'installments_scheduled') && data.checkout?.url) {
            window.location.href = data.checkout.url;
            return;
        }
        investError.value = { message: data.message || 'Réponse inattendue du serveur.' };
    } catch (e) {
        // Audit fix 2026-05 — surface structured 403 from the new
        // subscribed + kyc.smile:verified middlewares with deep-link CTAs.
        const body = e?.response?.data || {};
        if (body.subscription_required) {
            investError.value = { kind: 'subscription', message: body.message || 'L\'investissement est réservé aux abonnements Pro et Entreprise.' };
        } else if (body.error === 'kyc_insufficient' || body.error === 'kyc_expired') {
            investError.value = { kind: 'kyc', message: body.message || 'Une vérification KYC est requise pour investir.' };
        } else if (body.error === 'aml_blocked') {
            investError.value = { kind: 'kyc', message: body.message || 'Votre compte est bloqué pour conformité AML — contactez le support.' };
        } else if (body.error === 'aml_flagged') {
            investError.value = { kind: 'kyc', message: body.message || 'Votre dossier AML est en cours de revue.' };
        } else if (body.error === 'aml_required') {
            investError.value = { kind: 'kyc', message: body.message || 'Un screening AML est requis avant d\'investir.' };
        } else {
            investError.value = { message: body.message || 'Erreur lors de l\'initiation du paiement.' };
        }
    } finally {
        investing.value = false;
    }
}

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
    { id: 'milestones', label: 'Jalons & séquestre' },
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
    const cur = (project.value?.currency || 'EUR').toUpperCase();
    try {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(parseFloat(v) || 0);
    } catch {
        return `${parseFloat(v) || 0} ${cur}`;
    }
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

onMounted(() => {
    const t = route.query.tab;
    if (t && ['about', 'updates', 'milestones', 'related'].includes(String(t))) {
        tab.value = String(t);
    }
    load();
});
watch(() => route.params.slug, load);
</script>
