<template>
    <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 md:p-8">
        <h3 class="text-xl font-bold mb-6">Simulateur d'investissement diaspora</h3>

        <form @submit.prevent="simulate" class="space-y-5">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Montant (€)</label>
                    <input v-model.number="form.amount" type="number" min="100" max="10000000" step="100"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        placeholder="10 000" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Type d'investissement</label>
                    <select v-model="form.investment_type"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                        <option value="equity">Prise de participation (equity)</option>
                        <option value="loan">Prêt (loan)</option>
                        <option value="donation">Don (donation)</option>
                        <option value="reward">Récompense (reward)</option>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Pays de résidence</label>
                    <select v-model="form.origin_country"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                        <option v-for="c in residenceCountries" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Pays de destination</label>
                    <select v-model="form.destination_country"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                        <option v-for="c in destinationCountries" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Secteur cible</label>
                    <select v-model="form.target_sector"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                        <option value="">Tous secteurs</option>
                        <option v-for="s in sectors" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Durée (mois)</label>
                    <input v-model.number="form.duration_months" type="range" min="6" max="120" step="6"
                        class="w-full accent-emerald-600 mt-2" />
                    <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ form.duration_months }} mois ({{ (form.duration_months / 12).toFixed(1) }} ans)</div>
                </div>
            </div>

            <button type="submit" :disabled="loading"
                class="w-full py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-50 transition">
                {{ loading ? 'Calcul en cours…' : 'Calculer l\'impact' }}
            </button>
        </form>

        <!-- Results -->
        <transition name="fade">
            <div v-if="result" class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-8">
                <h4 class="font-bold text-lg mb-4">Résultats de la simulation</h4>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-100 dark:border-emerald-900/50 rounded-xl p-4 text-center">
                        <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ result.impact.estimated_jobs }}</div>
                        <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">Emplois créés</div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-900/50 rounded-xl p-4 text-center">
                        <div class="text-2xl font-black text-blue-700 dark:text-blue-300">{{ result.impact.people_impacted }}</div>
                        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">Personnes impactées</div>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-100 dark:border-amber-900/50 rounded-xl p-4 text-center">
                        <div class="text-2xl font-black text-amber-700 dark:text-amber-300">{{ result.impact.sdg_score }}/100</div>
                        <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">Score ODD</div>
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-5 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600 dark:text-slate-300">Montant investi</span>
                        <span class="font-semibold">{{ fmt(form.amount) }} €</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600 dark:text-slate-300">Coût de transfert estimé ({{ result.impact.transfer_cost_pct }}%)</span>
                        <span class="font-semibold text-red-600 dark:text-red-400">-{{ fmt(result.impact.transfer_cost) }} €</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600 dark:text-slate-300">Investissement net</span>
                        <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ fmt(result.impact.net_investment) }} €</span>
                    </div>
                    <div v-if="result.impact.estimated_return > 0" class="flex justify-between text-sm pt-3 border-t border-slate-200 dark:border-slate-700">
                        <span class="text-slate-600 dark:text-slate-300">Rendement estimé ({{ result.impact.annual_return_pct }}%/an sur {{ (form.duration_months / 12).toFixed(1) }} ans)</span>
                        <span class="font-bold text-emerald-700 dark:text-emerald-400">+{{ fmt(result.impact.estimated_return) }} €</span>
                    </div>
                    <div v-else class="text-sm text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-200 dark:border-slate-700 italic">
                        Ce type d'investissement ({{ typeLabel }}) n'a pas de rendement financier, mais un impact social direct.
                    </div>
                </div>

                <p class="text-xs text-slate-400 dark:text-slate-500 mt-4">
                    * Estimations basées sur des moyennes sectorielles et des données World Bank. Ne constitue pas un conseil en investissement.
                </p>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const residenceCountries = [
    'France', 'Belgique', 'Canada', 'États-Unis', 'Allemagne', 'Italie', 'Espagne',
    'Royaume-Uni', 'Suisse', 'Portugal', 'Pays-Bas', 'Chine', 'Émirats arabes unis',
    'Arabie saoudite', 'Gabon', 'Congo', 'Cameroun', 'Autre',
];

const destinationCountries = [
    'Sénégal', "Côte d'Ivoire", 'Nigeria', 'Maroc', 'Kenya', 'Rwanda',
    'Cameroun', 'Ghana', 'Tunisie', 'Éthiopie', 'Mali', 'Guinée',
    'Burkina Faso', 'Bénin', 'Togo', 'Niger', 'RDC', 'Madagascar',
    'Tanzanie', 'Ouganda', 'Mozambique', 'Afrique du Sud',
];

const sectors = [
    'Agritech', 'Fintech', 'Healthtech', 'Edtech',
    'Énergie', 'Commerce & Retail', 'Industrie', 'Tourisme & Culture',
];

const typeLabels = { equity: 'participation', loan: 'prêt', donation: 'don', reward: 'récompense' };

const form = ref({
    amount: 10000,
    investment_type: 'equity',
    origin_country: 'France',
    destination_country: 'Sénégal',
    target_sector: '',
    duration_months: 24,
    currency: 'EUR',
});

const loading = ref(false);
const result = ref(null);
const typeLabel = ref('');

function fmt(n) {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n);
}

async function simulate() {
    loading.value = true;
    result.value = null;
    typeLabel.value = typeLabels[form.value.investment_type] || form.value.investment_type;

    try {
        const { data } = await window.axios.post('/api/diaspora/simulate', form.value);
        result.value = data;
    } catch (e) {
        alert('Erreur lors de la simulation. Vérifiez les paramètres.');
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
