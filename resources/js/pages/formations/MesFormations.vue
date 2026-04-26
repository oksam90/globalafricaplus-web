<template>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <header class="mb-8">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Mes formations</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">Vos achats, accès et plans de paiement.</p>
        </header>

        <!-- Tabs -->
        <div class="flex gap-2 border-b border-slate-200 dark:border-slate-700 mb-6">
            <button v-for="t in tabs" :key="t.id" @click="tab = t.id"
                class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
                :class="tab === t.id
                    ? 'border-emerald-500 text-emerald-600'
                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200'">
                {{ t.label }}
            </button>
        </div>

        <!-- Trainings -->
        <section v-if="tab === 'trainings'">
            <div v-if="loadingT" class="text-center py-12 text-slate-500">Chargement…</div>
            <div v-else-if="!purchases.length" class="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700">
                <div class="text-5xl mb-3">🎓</div>
                <p class="text-slate-600 dark:text-slate-300 mb-4">Vous n'avez pas encore de formation.</p>
                <router-link to="/formations" class="inline-block px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    Découvrir les formations
                </router-link>
            </div>
            <div v-else class="space-y-4">
                <div v-for="p in purchases" :key="p.id"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-5 flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[240px]">
                        <h3 class="font-bold text-slate-900 dark:text-slate-100">{{ p.training?.title || 'Formation' }}</h3>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ formatDate(p.paid_at) }} · {{ formatPrice(p.amount, p.currency) }}
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span>
                    <router-link v-if="p.status === 'active' && p.training?.slug" :to="`/formations/${p.training.slug}`"
                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                        ▶ Accéder
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Installment plans -->
        <section v-else-if="tab === 'installments'">
            <div v-if="loadingI" class="text-center py-12 text-slate-500">Chargement…</div>
            <div v-else-if="!plans.length" class="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700">
                <div class="text-5xl mb-3">💳</div>
                <p class="text-slate-600 dark:text-slate-300">Aucun plan de paiement fractionné en cours.</p>
            </div>
            <div v-else class="space-y-4">
                <div v-for="plan in plans" :key="plan.id"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-5">
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <h3 class="font-bold text-slate-900 dark:text-slate-100 flex-1">
                            {{ planLabel(plan) }} — {{ formatPrice(plan.total_amount, plan.currency) }}
                        </h3>
                        <span class="text-xs font-bold px-2 py-1 rounded-full" :class="planStatusClass(plan.status)">{{ planStatusLabel(plan.status) }}</span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                        {{ plan.paid_installments }} / {{ plan.total_installments }} échéances payées · {{ frequencyLabel(plan.frequency) }}
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden mb-3">
                        <div class="h-full bg-emerald-500 transition-all" :style="{ width: `${(plan.paid_installments / plan.total_installments) * 100}%` }"></div>
                    </div>
                    <ul class="space-y-1 text-sm">
                        <li v-for="i in plan.installments" :key="i.id"
                            class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-700 last:border-0">
                            <span class="text-slate-700 dark:text-slate-200">
                                Échéance {{ i.number }} · {{ formatDate(i.due_date) }}
                            </span>
                            <span class="text-xs font-semibold" :class="installmentStatusClass(i.status)">
                                {{ formatPrice(i.amount, i.currency) }} · {{ installmentStatusLabel(i.status) }}
                            </span>
                        </li>
                    </ul>
                    <button v-if="plan.status === 'active' && nextDue(plan)" @click="payNext(plan)" :disabled="paying === plan.id"
                        class="mt-4 w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold">
                        {{ paying === plan.id ? 'Redirection…' : 'Payer la prochaine échéance' }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const tab = ref('trainings');
const tabs = [
    { id: 'trainings', label: 'Mes formations' },
    { id: 'installments', label: 'Mes paiements fractionnés' },
];

const purchases = ref([]);
const plans = ref([]);
const loadingT = ref(true);
const loadingI = ref(true);
const paying = ref(null);

function formatPrice(amount, currency) {
    const cur = (currency || 'EUR').toUpperCase();
    try { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: cur === 'XOF' ? 0 : 2 }).format(parseFloat(amount) || 0); }
    catch { return `${amount} ${cur}`; }
}
function formatDate(d) { return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''; }

function statusLabel(s) { return ({ active: 'Actif', pending: 'En attente', refunded: 'Remboursé', cancelled: 'Annulé', failed: 'Échoué' })[s] || s; }
function statusClass(s) {
    return ({
        active: 'bg-emerald-100 text-emerald-700',
        pending: 'bg-amber-100 text-amber-700',
        refunded: 'bg-slate-200 text-slate-600',
        cancelled: 'bg-slate-200 text-slate-500',
        failed: 'bg-rose-100 text-rose-700',
    })[s] || 'bg-slate-100 text-slate-700';
}

function planStatusLabel(s) { return ({ active: 'Actif', completed: 'Terminé', cancelled: 'Annulé', defaulted: 'Défaut' })[s] || s; }
function planStatusClass(s) {
    return ({
        active: 'bg-emerald-100 text-emerald-700',
        completed: 'bg-violet-100 text-violet-700',
        cancelled: 'bg-slate-200 text-slate-500',
        defaulted: 'bg-rose-100 text-rose-700',
    })[s] || 'bg-slate-100 text-slate-700';
}
function frequencyLabel(f) { return ({ weekly: 'Hebdomadaire', biweekly: 'Bimensuelle', monthly: 'Mensuelle' })[f] || f; }
function planLabel(p) {
    return ({ investment: 'Investissement', subscription: 'Abonnement', training: 'Formation' })[p.payment_type] || 'Plan';
}

function installmentStatusLabel(s) { return ({ pending: 'À venir', invoiced: 'Facturée', paid: 'Payée', failed: 'Échouée', skipped: 'Sautée' })[s] || s; }
function installmentStatusClass(s) {
    return ({
        paid: 'text-emerald-600',
        invoiced: 'text-amber-600',
        pending: 'text-slate-500',
        failed: 'text-rose-600',
        skipped: 'text-slate-400',
    })[s] || 'text-slate-500';
}
function nextDue(plan) {
    return plan.installments?.find(i => i.status === 'pending' || i.status === 'failed');
}

async function loadTrainings() {
    loadingT.value = true;
    try {
        const { data } = await window.axios.get('/api/trainings/mine');
        purchases.value = data.data || [];
    } catch { purchases.value = []; }
    finally { loadingT.value = false; }
}

async function loadPlans() {
    loadingI.value = true;
    try {
        const { data } = await window.axios.get('/api/installments/mine');
        plans.value = data.data || [];
    } catch { plans.value = []; }
    finally { loadingI.value = false; }
}

async function payNext(plan) {
    paying.value = plan.id;
    try {
        const { data } = await window.axios.post(`/api/installments/${plan.id}/pay-next`);
        if (data.checkout?.url) {
            window.location.href = data.checkout.url;
            return;
        }
        alert('Réponse inattendue.');
    } catch (e) {
        alert(e?.response?.data?.message || 'Erreur lors de la facturation.');
    } finally {
        paying.value = null;
    }
}

onMounted(() => {
    loadTrainings();
    loadPlans();
});
</script>
