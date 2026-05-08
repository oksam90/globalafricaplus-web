<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900">
        <!-- Hero -->
        <section class="bg-gradient-to-br from-emerald-50 via-amber-50 to-slate-50 dark:from-emerald-950/40 dark:via-amber-950/30 dark:to-slate-900 border-b border-emerald-100 dark:border-emerald-900/30">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Vérification d'identité</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-300">eKYC propulsé par Smile Identity — UEMOA &amp; Afrique</p>

                <!-- Progress bar -->
                <div class="mt-6 max-w-lg mx-auto">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">
                        <span>Étape {{ currentStep }} sur 4</span>
                        <span>{{ progress }}%</span>
                    </div>
                    <div class="h-2 bg-white dark:bg-slate-800 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-amber-500 rounded-full transition-all duration-500"
                            :style="{ width: progress + '%' }"></div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span v-for="s in steps" :key="s.id"
                            :class="[
                                currentStep === s.id ? 'text-emerald-600 dark:text-emerald-400 font-bold' : '',
                                currentStep > s.id ? 'text-emerald-500 dark:text-emerald-300' : 'text-slate-400 dark:text-slate-500',
                            ]">
                            {{ s.label }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Already verified banner -->
        <div v-if="kycVerified" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <div class="bg-emerald-50 dark:bg-emerald-900/30 border-2 border-emerald-200 dark:border-emerald-800 rounded-2xl p-6 sm:p-8 text-center">
                <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-800 flex items-center justify-center mx-auto mb-3">
                    <span class="text-3xl">✓</span>
                </div>
                <h2 class="text-2xl font-black text-emerald-900 dark:text-emerald-200">KYC {{ status?.kyc_level === 'certified' ? 'Certifié' : 'Vérifié' }}</h2>
                <p class="mt-2 text-emerald-800 dark:text-emerald-300">
                    Votre identité a été vérifiée le
                    <strong>{{ formatDate(status?.kyc_verified_at) }}</strong>.
                    Validité jusqu'au <strong>{{ formatDate(status?.kyc_expires_at) }}</strong>.
                </p>
                <div v-if="status?.aml_status === 'flagged'" class="mt-3 px-3 py-2 rounded-lg bg-amber-100 text-amber-800 inline-block text-sm font-semibold">
                    ⚠ Signalement AML — votre dossier est en revue.
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    <router-link to="/dashboard"
                        class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                        Retour au tableau de bord
                    </router-link>
                    <button v-if="status?.kyc_level !== 'certified'" @click="restart"
                        class="px-5 py-2.5 rounded-lg border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 font-semibold">
                        Passer au niveau Certifié
                    </button>
                </div>
            </div>
        </div>

        <!-- Wizard -->
        <div v-else class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Toast/result banner -->
            <div v-if="lastResult" class="mb-6 rounded-2xl border p-4 flex gap-3"
                :class="resultBannerClasses(lastResult.tone)">
                <div class="text-2xl shrink-0">{{ resultIcon(lastResult.tone) }}</div>
                <div class="flex-1">
                    <div class="font-bold">{{ lastResult.title }}</div>
                    <div class="text-sm mt-1">{{ lastResult.message }}</div>
                    <div v-if="lastResult.suggest === 'document'" class="mt-2 text-xs">
                        <button @click="currentStep = 2" class="underline font-semibold">Essayer la vérification par document</button>
                    </div>
                </div>
                <button @click="lastResult = null" class="text-current opacity-50 hover:opacity-100">✕</button>
            </div>

            <!-- ─── STEP 1 — Identity (Basic KYC) ─────────────────────────── -->
            <section v-show="currentStep === 1" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
                <h2 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-1">Étape 1 — Collecte d'identité</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">
                    Vos informations sont vérifiées en temps réel auprès de l'autorité gouvernementale de votre pays.
                </p>

                <form @submit.prevent="onSubmitBasic" class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Pays</label>
                        <select v-model="basicForm.country" required
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">— Choisir —</option>
                            <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Type de document</label>
                        <select v-model="basicForm.id_type" required
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option v-for="t in idTypesFor(basicForm.country)" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Numéro du document</label>
                        <input v-model="basicForm.id_number" required type="text" maxlength="64"
                            placeholder="ex. 1234567890123"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Prénom</label>
                        <input v-model="basicForm.first_name" required type="text" maxlength="100"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nom</label>
                        <input v-model="basicForm.last_name" required type="text" maxlength="100"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Date de naissance</label>
                        <input v-model="basicForm.dob" required type="date" :max="todayIso"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>

                    <div class="sm:col-span-2 flex items-center justify-between pt-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            🔒 Votre numéro de document est haché (SHA-256) — jamais stocké en clair.
                        </p>
                        <button type="submit" :disabled="kyc.submitting.value"
                            class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                            {{ kyc.submitting.value ? 'Soumission…' : 'Soumettre →' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- ─── STEP 2 — Documents (Smile Web SDK) ─────────────────── -->
            <section v-show="currentStep === 2" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
                <h2 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-1">Étape 2 — Documents &amp; biométrie</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">
                    Capturez votre selfie et votre document via le widget sécurisé Smile Identity.
                    Liveness check et anti-spoofing intégrés.
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="font-bold mb-2 text-slate-800 dark:text-slate-200">📷 Vérification biométrique</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                            Selfie + comparaison faciale avec la photo de l'autorité gouvernementale.
                        </p>
                        <button @click="launchSdk('biometric_kyc')" :disabled="sdkLaunching"
                            class="w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                            {{ sdkLaunching ? 'Chargement…' : 'Lancer le widget' }}
                        </button>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="font-bold mb-2 text-slate-800 dark:text-slate-200">📄 Vérification par document</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                            OCR de votre CNI/passeport + comparaison selfie ↔ photo du document.
                        </p>
                        <button @click="launchSdk('doc_verification')" :disabled="sdkLaunching"
                            class="w-full px-4 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-60 text-white font-semibold">
                            {{ sdkLaunching ? 'Chargement…' : 'Lancer le widget' }}
                        </button>
                    </div>
                </div>

                <p v-if="sdkError" class="mt-4 text-sm text-rose-600">{{ sdkError }}</p>

                <div class="mt-6 flex justify-between">
                    <button @click="currentStep = 1" class="text-sm text-slate-600 dark:text-slate-300 hover:underline">← Retour</button>
                    <button @click="currentStep = 3" class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">Continuer →</button>
                </div>
            </section>

            <!-- ─── STEP 3 — Évaluation du risque (informatif) ─────────── -->
            <section v-show="currentStep === 3" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
                <h2 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-1">Étape 3 — Évaluation du risque</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">
                    Score interne calculé automatiquement à partir de vos vérifications précédentes.
                </p>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Niveau actuel</div>
                        <div class="mt-1 text-2xl font-black"
                            :class="status?.kyc_level === 'certified' ? 'text-emerald-600' : status?.kyc_level === 'verified' ? 'text-amber-600' : 'text-slate-500'">
                            {{ tierLabel(status?.kyc_level) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Statut AML</div>
                        <div class="mt-1 text-2xl font-black"
                            :class="status?.aml_status === 'clear' ? 'text-emerald-600' : status?.aml_status === 'flagged' ? 'text-amber-600' : 'text-rose-600'">
                            {{ amlLabel(status?.aml_status) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Selfie enregistré</div>
                        <div class="mt-1 text-2xl font-black"
                            :class="status?.selfie_registered ? 'text-emerald-600' : 'text-slate-400'">
                            {{ status?.selfie_registered ? 'Oui' : 'Non' }}
                        </div>
                    </div>
                </div>

                <div v-if="status?.latest" class="mt-5 text-sm text-slate-600 dark:text-slate-400 rounded-lg bg-slate-50 dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-700">
                    <div class="font-semibold text-slate-800 dark:text-slate-200 mb-2">Dernière vérification</div>
                    <ul class="space-y-1 text-xs">
                        <li><strong>Type:</strong> {{ status.latest.job_type }}</li>
                        <li><strong>Statut:</strong> {{ status.latest.status }}</li>
                        <li v-if="status.latest.result_code"><strong>Code:</strong> {{ status.latest.result_code }} — {{ status.latest.result_text }}</li>
                        <li v-if="status.latest.confidence_value"><strong>Confiance:</strong> {{ status.latest.confidence_value }}%</li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-between">
                    <button @click="currentStep = 2" class="text-sm text-slate-600 dark:text-slate-300 hover:underline">← Retour</button>
                    <button @click="currentStep = 4" class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">Continuer vers l'AML →</button>
                </div>
            </section>

            <!-- ─── STEP 4 — AML Check (synchrone) ────────────────────── -->
            <section v-show="currentStep === 4" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
                <h2 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-1">Étape 4 — Vérification AML</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">
                    Screening contre 1&nbsp;100+ listes de sanctions, PEP et médias défavorables. Résultat immédiat.
                </p>

                <form @submit.prevent="onSubmitAml" class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nom complet (tel qu'il apparaît sur le document)</label>
                        <input v-model="amlForm.full_name" required type="text" minlength="3" maxlength="200"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Année de naissance</label>
                        <input v-model="amlForm.birth_year" required type="text" pattern="(19|20)\d{2}" maxlength="4"
                            placeholder="1990"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nationalité(s) à screener</label>
                        <select v-model="amlForm.countries" multiple required
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm h-24">
                            <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ c.name }}</option>
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Ctrl/Cmd+clic pour en sélectionner plusieurs.</p>
                    </div>

                    <div class="sm:col-span-2 flex items-center justify-between pt-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            🔒 Conforme Directive UEMOA N° 02/2015/CM/UEMOA (LCB-FT)
                        </p>
                        <button type="submit" :disabled="kyc.submitting.value"
                            class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                            {{ kyc.submitting.value ? 'Screening…' : 'Lancer le screening' }}
                        </button>
                    </div>
                </form>

                <!-- AML result -->
                <div v-if="amlResult" class="mt-6 rounded-xl border p-5"
                    :class="amlBannerClasses(amlResult.risk_level)">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">{{ amlIcon(amlResult.risk_level) }}</span>
                        <span class="font-bold">Niveau de risque : {{ describeRiskLevel(amlResult.risk_level).label }}</span>
                    </div>
                    <ul class="text-sm space-y-1">
                        <li>Sanctions : <strong>{{ amlResult.flags.sanctions ? 'Match' : 'Aucun' }}</strong></li>
                        <li>PEP : <strong>{{ amlResult.flags.pep ? 'Match' : 'Aucun' }}</strong></li>
                        <li>Médias défavorables : <strong>{{ amlResult.flags.adverse_media ? 'Match' : 'Aucun' }}</strong></li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-between">
                    <button @click="currentStep = 3" class="text-sm text-slate-600 dark:text-slate-300 hover:underline">← Retour</button>
                    <router-link to="/dashboard"
                        class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">Terminer →</router-link>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useKycApi } from '../composables/useKycApi';
import { useSmileWebSdk } from '../composables/useSmileWebSdk';
import { describeResultCode, describeRiskLevel, tierRank } from '../utils/smileResultCodes';

const auth = useAuthStore();
const kyc = useKycApi();
const { launchSmileSdk } = useSmileWebSdk();

const currentStep = ref(1);
const lastResult = ref(null);
const sdkLaunching = ref(false);
const sdkError = ref('');
const amlResult = ref(null);

const status = computed(() => kyc.status.value);
const kycVerified = computed(() => tierRank(status.value?.kyc_level) >= 1 && !status.value?.is_expired);
const todayIso = new Date().toISOString().slice(0, 10);

const steps = [
    { id: 1, label: 'Identité' },
    { id: 2, label: 'Documents' },
    { id: 3, label: 'Risque' },
    { id: 4, label: 'AML' },
];
const progress = computed(() => Math.round((currentStep.value / steps.length) * 100));

const countries = [
    { code: 'SN', name: 'Sénégal',       flag: '🇸🇳' },
    { code: 'CI', name: 'Côte d\'Ivoire', flag: '🇨🇮' },
    { code: 'ML', name: 'Mali',           flag: '🇲🇱' },
    { code: 'BF', name: 'Burkina Faso',   flag: '🇧🇫' },
    { code: 'BJ', name: 'Bénin',          flag: '🇧🇯' },
    { code: 'TG', name: 'Togo',           flag: '🇹🇬' },
    { code: 'NE', name: 'Niger',          flag: '🇳🇪' },
    { code: 'NG', name: 'Nigeria',        flag: '🇳🇬' },
    { code: 'GH', name: 'Ghana',          flag: '🇬🇭' },
    { code: 'KE', name: 'Kenya',          flag: '🇰🇪' },
    { code: 'FR', name: 'France',         flag: '🇫🇷' },
];

const ID_TYPES_BY_COUNTRY = {
    SN: [{ code: 'NATIONAL_ID', label: 'Carte Nationale d\'Identité' }, { code: 'PASSPORT', label: 'Passeport' }, { code: 'DRIVERS_LICENSE', label: 'Permis de conduire' }],
    CI: [{ code: 'NATIONAL_ID', label: 'Carte Nationale d\'Identité' }, { code: 'PASSPORT', label: 'Passeport' }, { code: 'DRIVERS_LICENSE', label: 'Permis de conduire' }],
    ML: [{ code: 'NATIONAL_ID', label: 'NINA' }, { code: 'PASSPORT', label: 'Passeport' }],
    NG: [{ code: 'NIN_V2', label: 'NIN' }, { code: 'BVN', label: 'BVN' }, { code: 'VOTER_ID', label: 'Voter\'s Card' }, { code: 'DRIVERS_LICENSE', label: 'Permis de conduire' }, { code: 'PASSPORT', label: 'Passeport' }],
    GH: [{ code: 'NATIONAL_ID', label: 'Ghana Card' }, { code: 'VOTER_ID', label: 'Voter\'s ID' }, { code: 'SSNIT', label: 'SSNIT' }, { code: 'PASSPORT', label: 'Passeport' }],
    KE: [{ code: 'NATIONAL_ID', label: 'National ID' }, { code: 'PASSPORT', label: 'Passeport' }, { code: 'ALIEN_CARD', label: 'Alien ID' }],
    DEFAULT: [{ code: 'PASSPORT', label: 'Passeport' }],
};
function idTypesFor(code) { return ID_TYPES_BY_COUNTRY[code] ?? ID_TYPES_BY_COUNTRY.DEFAULT; }

const basicForm = reactive({
    country: auth.user?.country?.toUpperCase() ?? 'SN',
    id_type: 'NATIONAL_ID',
    id_number: '',
    first_name: '',
    last_name: '',
    dob: '',
});

const amlForm = reactive({
    full_name: '',
    countries: [auth.user?.country?.toUpperCase() ?? 'SN'],
    birth_year: '',
});

function tierLabel(level) { return ({ basic: 'Basic', verified: 'Vérifié', certified: 'Certifié' })[level] || 'Basic'; }
function amlLabel(s) { return ({ clear: 'Clair', flagged: 'Signalé', blocked: 'Bloqué' })[s] || '—'; }

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function resultIcon(tone) { return ({ success: '✅', warning: '⚠️', error: '⛔', info: 'ℹ️' })[tone] || 'ℹ️'; }
function resultBannerClasses(tone) {
    return ({
        success: 'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100',
        warning: 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100',
        error:   'bg-rose-50 dark:bg-rose-900/30 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100',
        info:    'bg-sky-50 dark:bg-sky-900/30 border-sky-200 dark:border-sky-800 text-sky-900 dark:text-sky-100',
    })[tone] || 'bg-slate-50 border-slate-200';
}

function amlIcon(level) { return ({ low: '✅', medium: '⚠️', high: '⛔', critical: '🚨' })[level] || 'ℹ️'; }
function amlBannerClasses(level) {
    return ({
        low:      'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800',
        medium:   'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800',
        high:     'bg-rose-50 dark:bg-rose-900/30 border-rose-200 dark:border-rose-800',
        critical: 'bg-red-100 dark:bg-red-900/40 border-red-300 dark:border-red-700',
    })[level] || 'bg-slate-50 border-slate-200';
}

async function onSubmitBasic() {
    lastResult.value = null;
    try {
        const res = await kyc.submitBasic({ ...basicForm });
        const code = res?.verification?.result_code;
        lastResult.value = code ? describeResultCode(code) : { tone: 'info', title: 'Vérification soumise', message: res?.message || 'Résultat attendu via callback dans quelques secondes.' };
        currentStep.value = 2;
    } catch (e) {
        lastResult.value = { tone: 'error', title: 'Échec de soumission', message: e?.response?.data?.message || 'Erreur inattendue.' };
    }
}

async function launchSdk(product) {
    sdkError.value = '';
    sdkLaunching.value = true;
    try {
        const tokenResp = await kyc.fetchWebToken(product);
        await launchSmileSdk({
            token: tokenResp.token,
            product,
            onSuccess: () => {
                lastResult.value = { tone: 'info', title: 'Capture envoyée', message: 'Le résultat sera disponible dans quelques secondes.' };
                kyc.fetchStatus();
                currentStep.value = 3;
            },
            onError: (err) => {
                sdkError.value = err?.message || 'Erreur lors du widget Smile Identity.';
            },
        });
    } catch (e) {
        sdkError.value = e?.response?.data?.message || e?.message || 'Échec du chargement du widget.';
    } finally {
        sdkLaunching.value = false;
    }
}

async function onSubmitAml() {
    amlResult.value = null;
    try {
        const res = await kyc.submitAML({ ...amlForm });
        amlResult.value = res;
    } catch (e) {
        lastResult.value = { tone: 'error', title: 'Échec du screening AML', message: e?.response?.data?.message || 'Erreur inattendue.' };
    }
}

function restart() {
    currentStep.value = 1;
    Object.assign(basicForm, { id_number: '', first_name: '', last_name: '', dob: '' });
    Object.assign(amlForm, { full_name: '', birth_year: '' });
    lastResult.value = null;
    amlResult.value = null;
}

onMounted(() => {
    kyc.fetchStatus().catch(() => {});
});
</script>
