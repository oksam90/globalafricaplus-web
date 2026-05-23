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

        <!-- Already verified banner — only when KYC AND AML are done. -->
        <div v-if="investorProfileComplete" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <div class="bg-emerald-50 dark:bg-emerald-900/30 border-2 border-emerald-200 dark:border-emerald-800 rounded-2xl p-6 sm:p-8 text-center">
                <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-800 flex items-center justify-center mx-auto mb-3">
                    <span class="text-3xl">✓</span>
                </div>
                <h2 class="text-2xl font-black text-emerald-900 dark:text-emerald-200">KYC {{ status?.kyc_level === 'certified' ? 'Certifié' : 'Vérifié' }}</h2>
                <p class="mt-2 text-emerald-800 dark:text-emerald-300">
                    <template v-if="status?.kyc_verified_at">
                        Votre identité a été vérifiée le
                        <strong>{{ formatDate(status?.kyc_verified_at) }}</strong>.
                    </template>
                    <template v-else>Votre identité a été vérifiée.</template>
                    <template v-if="status?.kyc_expires_at">
                        Validité jusqu'au <strong>{{ formatDate(status?.kyc_expires_at) }}</strong>.
                    </template>
                </p>
                <p class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">
                    Screening AML effectué le <strong>{{ formatDate(status?.aml_last_checked_at) }}</strong> — statut : <strong>{{ amlLabel(status?.aml_status) }}</strong>.
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


        <!-- Wizard — shown when the investor profile is not yet complete
             (either KYC missing/expired, or KYC done but AML pending). -->
        <div v-else class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- AML pending banner — KYC OK, just AML left. -->
            <div v-if="kycVerified && !amlCompleted"
                class="mb-6 rounded-2xl border-2 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 p-5 flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-800 flex items-center justify-center shrink-0">
                    <span class="text-xl">⚠</span>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-amber-900 dark:text-amber-200">
                        Profil investisseur incomplet — screening AML requis
                    </h3>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                        Votre identité est vérifiée (KYC <strong>{{ tierLabel(status?.kyc_level) }}</strong>),
                        mais le screening AML (lutte anti-blanchiment) n'a pas encore été réalisé.
                        Cette étape est obligatoire pour pouvoir investir.
                    </p>
                    <button v-if="currentStep !== 4" @click="currentStep = 4"
                        class="mt-3 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">
                        Aller à l'étape AML →
                    </button>
                </div>
            </div>

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
                        <label for="kyc-country" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Pays</label>
                        <select id="kyc-country" name="country" v-model="basicForm.country" required autocomplete="country"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">— Choisir —</option>
                            <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="kyc-id-type" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Type de document</label>
                        <select id="kyc-id-type" name="id_type" v-model="basicForm.id_type" required
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option v-for="t in idTypesFor(basicForm.country)" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="kyc-id-number" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Numéro du document</label>
                        <input id="kyc-id-number" name="id_number" v-model="basicForm.id_number" required type="text" maxlength="64"
                            autocomplete="off"
                            placeholder="ex. 1234567890123"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="kyc-first-name" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Prénom</label>
                        <input id="kyc-first-name" name="given-name" v-model="basicForm.first_name" required type="text" maxlength="100"
                            autocomplete="given-name"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="kyc-last-name" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nom</label>
                        <input id="kyc-last-name" name="family-name" v-model="basicForm.last_name" required type="text" maxlength="100"
                            autocomplete="family-name"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="kyc-dob" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Date de naissance</label>
                        <input id="kyc-dob" name="bday" v-model="basicForm.dob" required type="date" :max="todayIso"
                            autocomplete="bday"
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
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-2">
                    Capturez votre selfie et votre document via le widget sécurisé Smile Identity.
                    Liveness check et anti-spoofing intégrés.
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
                    🌍 Pays détecté : <strong>{{ countryName(basicForm.country) }}</strong>.
                    <span v-if="!biometricAvailable">
                        La vérification biométrique n'est pas disponible dans ce pays (l'autorité ne partage pas les photos via API) —
                        utilisez la vérification par document.
                    </span>
                </p>

                <div class="grid gap-4" :class="biometricAvailable ? 'sm:grid-cols-2' : 'sm:max-w-md sm:mx-auto'">
                    <!-- Biometric: shown only where Smile actually supports it. -->
                    <div v-if="biometricAvailable"
                        class="rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                        <h3 class="font-bold mb-2 text-slate-800 dark:text-slate-200">📷 Vérification biométrique</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                            Selfie + comparaison faciale avec la photo de l'autorité gouvernementale.
                        </p>
                        <button @click="launchSdk('biometric_kyc')" :disabled="sdkLaunching"
                            class="w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                            {{ sdkLaunching ? 'Chargement…' : 'Lancer le widget' }}
                        </button>
                    </div>

                    <div v-if="documentAvailable"
                        class="rounded-xl border p-5 relative"
                        :class="!biometricAvailable
                            ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-900/10'
                            : 'border-slate-200 dark:border-slate-700'">
                        <span v-if="!biometricAvailable"
                            class="absolute -top-2 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-600 text-white">
                            Recommandé
                        </span>
                        <h3 class="font-bold mb-2 text-slate-800 dark:text-slate-200">📄 Vérification par document</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">
                            OCR de votre CNI, passeport ou permis de conduire + comparaison selfie ↔ photo du document.
                        </p>
                        <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-0.5 mb-4 pl-1">
                            <li>✓ Carte Nationale d'Identité (CNI)</li>
                            <li>✓ Passeport</li>
                            <li>✓ Permis de conduire</li>
                            <li class="text-[11px] italic text-slate-500 dark:text-slate-500 mt-1">Le document doit comporter votre photo et être en cours de validité.</li>
                        </ul>
                        <button @click="launchSdk('doc_verification')" :disabled="sdkLaunching"
                            class="w-full px-4 py-2.5 rounded-lg font-semibold disabled:opacity-60 text-white"
                            :class="!biometricAvailable
                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                : 'bg-slate-900 hover:bg-slate-800'">
                            {{ sdkLaunching ? 'Chargement…' : 'Lancer le widget' }}
                        </button>
                    </div>
                </div>

                <p v-if="sdkError" class="mt-4 text-sm text-rose-600">{{ sdkError }}</p>

                <!-- REST fallback — opt-in path when /v1/token returns 2205, or
                     simply preferred by the user. Bypasses the Hosted Web SDK
                     entirely and uploads selfie + document straight to /upload. -->
                <div class="mt-6">
                    <button v-if="!showRestFallback" @click="showRestFallback = true" type="button"
                        class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">
                        ⚙ Le widget ne s'ouvre pas ? Utiliser le mode REST direct →
                    </button>
                    <DocVerificationCapture v-else
                        :default-country="basicForm.country"
                        :default-id-type="basicForm.id_type"
                        @submitted="onRestSubmitted"
                        class="mt-3" />
                </div>

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
                        <label for="aml-full-name" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nom complet (tel qu'il apparaît sur le document)</label>
                        <input id="aml-full-name" name="full_name" v-model="amlForm.full_name" required type="text" minlength="3" maxlength="200"
                            autocomplete="name"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="aml-birth-year" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Année de naissance</label>
                        <input id="aml-birth-year" name="birth_year" v-model="amlForm.birth_year" required type="text" pattern="(19|20)\d{2}" maxlength="4"
                            inputmode="numeric"
                            placeholder="1990"
                            class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="aml-countries" class="block text-sm font-semibold mb-1 text-slate-800 dark:text-slate-200">Nationalité(s) à screener</label>
                        <select id="aml-countries" name="countries[]" v-model="amlForm.countries" multiple required
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
import DocVerificationCapture from '../components/DocVerificationCapture.vue';

const auth = useAuthStore();
const kyc = useKycApi();
const { launchSmileSdk } = useSmileWebSdk();

const currentStep = ref(1);
const lastResult = ref(null);
const sdkLaunching = ref(false);
const sdkError = ref('');
// When /v1/token returns 2205 (or any other failure), surface the REST
// fallback so the user can still finish KYC via /upload — see
// components/DocVerificationCapture.vue.
const showRestFallback = ref(false);
const amlResult = ref(null);

const status = computed(() => kyc.status.value);
const kycVerified = computed(() => tierRank(status.value?.kyc_level) >= 1 && !status.value?.is_expired);
/**
 * Optimisation point 3 — investing requires KYC verified AND AML cleared.
 * Users who were certified before the AML gate landed have aml_last_checked_at = null;
 * we treat the profile as incomplete until they run the AML screening too.
 */
const amlCompleted = computed(() => !!status.value?.aml_last_checked_at
    && (status.value?.aml_status ?? 'clear') !== 'blocked');
const investorProfileComplete = computed(() => kycVerified.value && amlCompleted.value);
const todayIso = new Date().toISOString().slice(0, 10);

/**
 * Audit fix 2026-05 — only render the Smile widget for products supported
 * by the user's country. Without this, clicking "Biometric KYC" for a
 * Senegalese user surfaces a confusing `Smile Identity error [2205]`.
 */
const biometricAvailable = computed(() => supportsBiometric(basicForm.country));
const documentAvailable  = computed(() => supportsDocument(basicForm.country));
function countryName(code) {
    return countries.find(c => c.code === code)?.name || code;
}

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

/**
 * Smile Identity Web SDK products available per country.
 *
 * Confirmed by Smile Support ticket #1757 (2026-05): Biometric KYC compares
 * the selfie to a photo the ID authority holds on file. That photo is NOT
 * exposed via API in most UEMOA countries (Senegal, Côte d'Ivoire, Mali…),
 * so `/v1/token` with product=biometric_kyc returns error 2205. Doc
 * Verification (user uploads a photo of their ID + selfie) is the
 * recommended product for those markets.
 *
 * Source: https://docs.usesmileid.com/supported-id-types/for-individuals-kyc/using-document-image/regions/africa
 */
const SMILE_PRODUCTS_BY_COUNTRY = {
    SN: ['doc_verification'],                       // No biometric on Senegal
    CI: ['doc_verification'],
    ML: ['doc_verification'],
    BF: ['doc_verification'],
    BJ: ['doc_verification'],
    TG: ['doc_verification'],
    NE: ['doc_verification'],
    NG: ['biometric_kyc', 'doc_verification'],      // NIN + BVN authorities expose photos
    GH: ['biometric_kyc', 'doc_verification'],      // Ghana Card / Voter ID expose photos
    KE: ['biometric_kyc', 'doc_verification'],      // National ID authority exposes photos
    FR: ['doc_verification'],                       // Diaspora: passport flow only
    DEFAULT: ['doc_verification'],
};
function productsFor(code) {
    return SMILE_PRODUCTS_BY_COUNTRY[code] ?? SMILE_PRODUCTS_BY_COUNTRY.DEFAULT;
}
function supportsBiometric(code) {
    return productsFor(code).includes('biometric_kyc');
}
function supportsDocument(code) {
    return productsFor(code).includes('doc_verification');
}

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
        const msg = e?.response?.data?.message || e?.message || 'Échec du chargement du widget.';
        sdkError.value = msg;
        // Smile error code 2205 ("You are not authorized to do that") on
        // /v1/token means the sandbox can't use the Hosted Web SDK. Auto-
        // surface the REST fallback so the user can still finish KYC.
        if (/\b2205\b/.test(msg) || /not authorized/i.test(msg)) {
            showRestFallback.value = true;
        }
    } finally {
        sdkLaunching.value = false;
    }
}

function onRestSubmitted(resp) {
    lastResult.value = {
        tone: 'info',
        title: 'Vérification soumise',
        message: resp?.message || 'Résultat attendu via callback dans quelques secondes.',
    };
    kyc.fetchStatus();
    currentStep.value = 3;
}

async function onSubmitAml() {
    amlResult.value = null;
    try {
        const res = await kyc.submitAML({ ...amlForm });
        amlResult.value = res;
        // Refresh status so the success banner picks up aml_last_checked_at /
        // aml_status without a manual page reload.
        await kyc.fetchStatus().catch(() => {});
        // Also refresh the auth store so the global investorProfileReady getter
        // unblocks the "Investir" button on project pages.
        await auth.fetchUser().catch(() => {});
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

onMounted(async () => {
    try {
        await kyc.fetchStatus();
        // Land users with KYC done but AML pending directly on step 4 so they
        // don't have to click through the AML CTA banner.
        if (kycVerified.value && !amlCompleted.value) {
            currentStep.value = 4;
        }
    } catch {
        /* non-fatal */
    }
});
</script>
