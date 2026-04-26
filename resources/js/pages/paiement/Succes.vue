<template>
    <div class="min-h-[70vh] flex items-center justify-center px-4">
        <div class="max-w-xl w-full bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-lg p-8 text-center">
            <div v-if="loading" class="py-10">
                <div class="inline-block w-12 h-12 border-4 border-emerald-200 dark:border-emerald-900 border-t-emerald-600 rounded-full animate-spin"></div>
                <p class="mt-4 text-slate-600 dark:text-slate-300">Vérification de votre paiement…</p>
            </div>

            <template v-else-if="status === 'completed'">
                <div class="text-5xl mb-3">✅</div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Paiement confirmé</h1>
                <p v-if="paymentType === 'investment' && investment" class="mt-3 text-slate-600 dark:text-slate-300">
                    Merci&nbsp;! Votre investissement de
                    <strong>{{ formatMoney(investment.amount, investment.currency) }}</strong>
                    dans <strong>{{ investment.project?.title }}</strong> est sécurisé en <em>escrow</em>.
                    Les fonds seront libérés au fur et à mesure de l'atteinte des jalons.
                </p>
                <p v-else-if="paymentType === 'training' && training" class="mt-3 text-slate-600 dark:text-slate-300">
                    Merci&nbsp;! Vous avez désormais accès à la formation <strong>{{ training.title }}</strong>.
                    Garantie satisfait ou remboursé pendant 30 jours.
                </p>
                <p v-else class="mt-3 text-slate-600 dark:text-slate-300">
                    Merci&nbsp;! Votre abonnement est désormais actif. Un reçu vous a été envoyé par email.
                </p>
                <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                    <router-link v-if="paymentType === 'investment' && investment?.project?.slug"
                        :to="`/projets/${investment.project.slug}`"
                        class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                        Retour au projet
                    </router-link>
                    <router-link v-else-if="paymentType === 'training' && training?.slug"
                        :to="`/formations/${training.slug}`"
                        class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                        ▶ Accéder à la formation
                    </router-link>
                    <router-link v-else to="/dashboard"
                        class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                        Accéder au tableau de bord
                    </router-link>
                    <a v-if="receiptUrl" :href="receiptUrl" target="_blank" rel="noopener"
                        class="px-5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold">
                        Télécharger le reçu
                    </a>
                </div>

                <div v-if="paymentType === 'investment' && investment?.milestones?.length" class="mt-8 text-left">
                    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Jalons de libération</h2>
                    <ul class="space-y-2">
                        <li v-for="m in investment.milestones" :key="m.id"
                            class="flex items-center justify-between text-sm px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700">
                            <span>
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ m.position }}. {{ m.title }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 ml-2">{{ m.percentage }}%</span>
                            </span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">
                                {{ formatMoney(m.amount, m.currency) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </template>

            <template v-else-if="status === 'pending' || status === 'processing'">
                <div class="text-5xl mb-3">⏳</div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Paiement en cours de traitement</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">
                    Nous attendons la confirmation de PayDunya. Vous recevrez une notification dès son activation.
                </p>
                <router-link to="/tarifs"
                    class="mt-6 inline-block px-5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold">
                    Retour aux tarifs
                </router-link>
            </template>

            <template v-else>
                <div class="text-5xl mb-3">⚠️</div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Paiement non confirmé</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">
                    Nous n'avons pas pu confirmer votre paiement. Si le montant a été débité, contactez-nous.
                </p>
                <router-link to="/tarifs"
                    class="mt-6 inline-block px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                    Réessayer
                </router-link>
            </template>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const loading = ref(true);
const status = ref(null);
const receiptUrl = ref(null);
const paymentType = ref(null);
const investment = ref(null);
const training = ref(null);

function formatMoney(v, currency) {
    const n = parseFloat(v) || 0;
    const cur = (currency || 'EUR').toUpperCase();
    try {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(n);
    } catch {
        return `${n} ${cur}`;
    }
}

async function tryVerify(endpoint, token) {
    try {
        const { data } = await window.axios.post(endpoint, { token });
        return { ok: true, data };
    } catch (e) {
        return { ok: false, error: e };
    }
}

onMounted(async () => {
    const token = route.query.token;
    if (!token) {
        loading.value = false;
        status.value = 'unknown';
        return;
    }

    // Try investment, then training, then subscription. Each verify endpoint
    // is scoped to its own payment_type and 404s otherwise.
    let res = await tryVerify('/api/investments/verify', token);
    if (res.ok) {
        paymentType.value = 'investment';
        status.value = res.data.status;
        receiptUrl.value = res.data.receipt_url;
        investment.value = res.data.investment;
        loading.value = false;
        return;
    }

    res = await tryVerify('/api/trainings/verify', token);
    if (res.ok) {
        paymentType.value = 'training';
        status.value = res.data.status;
        receiptUrl.value = res.data.transaction?.paydunya_receipt_url || null;
        training.value = res.data.purchase?.training || null;
        loading.value = false;
        return;
    }

    res = await tryVerify('/api/subscription/verify', token);
    if (res.ok) {
        paymentType.value = 'subscription';
        status.value = res.data.status;
        receiptUrl.value = res.data.receipt_url;
        if (res.data.status === 'completed') {
            await auth.fetchUser();
        }
        loading.value = false;
        return;
    }

    status.value = 'failed';
    loading.value = false;
});
</script>
