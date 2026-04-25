<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900">
        <!-- Hero -->
        <section class="bg-gradient-to-br from-orange-50 via-amber-50 to-slate-50 dark:from-orange-950/40 dark:via-amber-950/30 dark:to-slate-900 border-b border-orange-100">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-orange-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Vérification KYC — Globalafrica+</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-300">Système de Confiance et Transparence — Vérification d'identité eKYC via IDNorm</p>

                <!-- Progress bar -->
                <div class="mt-6 max-w-lg mx-auto">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">
                        <span>Progression</span>
                        <span>{{ completion }}%</span>
                    </div>
                    <div class="h-2 bg-white dark:bg-slate-800 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700">
                        <div class="h-full bg-gradient-to-r from-orange-400 to-emerald-500 rounded-full transition-all duration-500"
                            :style="{ width: completion + '%' }"></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Already verified -->
            <div v-if="kycVerified" class="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-emerald-900">KYC Vérifié</h2>
                <p class="mt-2 text-emerald-700">Votre identité a été vérifiée avec succès par Globalafrica+. Vous avez désormais accès à toutes les fonctionnalités de la plateforme, y compris les transactions escrow, le mentorat et la publication de projets.</p>
                <div class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                    Verifie le {{ session?.verified_at ? new Date(session.verified_at).toLocaleDateString('fr-FR') : '' }}
                </div>
                <router-link to="/dashboard"
                    class="mt-6 inline-block px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                    Retour au dashboard
                </router-link>
            </div>

            <!-- Rejected -->
            <div v-else-if="session?.status === 'rejected'" class="bg-red-50 border-2 border-red-200 rounded-2xl p-8 text-center mb-8">
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-red-900">Verification rejetee</h2>
                <p class="mt-2 text-red-700">{{ session.rejection_reason || 'Votre verification KYC a ete rejetee.' }}</p>
                <button @click="restartKyc"
                    class="mt-6 inline-block px-6 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold">
                    Recommencer la verification
                </button>
            </div>

            <!-- ═══ IDnorm hosted verification (actif en attente d'intégration API) ═══ -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-10">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 text-xs font-semibold uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                        Vérification sécurisée via IDnorm
                    </div>
                    <h2 class="mt-4 text-2xl font-black text-slate-900 dark:text-slate-100">Vérifiez votre identité</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 max-w-xl mx-auto">
                        Cliquez sur le bouton ci-dessous pour ouvrir le flux de vérification complet hébergé par notre
                        partenaire IDnorm (eKYC, documents, biométrie, AML). Vous pouvez aussi scanner le QR code avec
                        votre smartphone pour continuer sur mobile.
                    </p>

                    <!-- Prerequisites -->
                    <ul class="mt-6 max-w-xl mx-auto text-left space-y-2.5 text-sm text-slate-700 dark:text-slate-200">
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 flex items-center justify-center text-xs font-bold">1</span>
                            <span>Munissez-vous de votre pièce d'identité officielle valide.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 flex items-center justify-center text-xs font-bold">2</span>
                            <span>Il vous sera demandé de scanner les deux faces du document.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 flex items-center justify-center text-xs font-bold">3</span>
                            <span>Préparez-vous à prendre un selfie pour confirmer que vous êtes bien vous.</span>
                        </li>
                    </ul>
                </div>

                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <!-- QR code -->
                    <div class="flex flex-col items-center gap-3">
                        <div class="bg-white p-4 rounded-xl border border-slate-200 dark:border-slate-600 shadow-sm">
                            <img :src="qrCodeUrl" :alt="`QR code vérification IDnorm ${idnormVerificationId}`"
                                class="w-56 h-56 block" loading="lazy" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 text-center">
                            Scannez avec votre smartphone pour démarrer la vérification sur mobile.
                        </p>
                    </div>

                    <!-- CTA + infos -->
                    <div class="space-y-4">
                        <a :href="idnormVerificationUrl" target="_blank" rel="noopener noreferrer"
                            class="group flex items-center justify-center gap-2 w-full px-6 py-4 rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold text-base shadow-lg shadow-orange-500/30 transition-all hover:shadow-xl hover:-translate-y-0.5">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Vérifier mon identité
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>

                        <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 dark:text-emerald-400 mt-0.5">✓</span>
                                <span>Vérification d'identité biométrique (eKYC)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 dark:text-emerald-400 mt-0.5">✓</span>
                                <span>Authentification des documents officiels</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 dark:text-emerald-400 mt-0.5">✓</span>
                                <span>Screening listes de sanctions &amp; PPE</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-600 dark:text-emerald-400 mt-0.5">✓</span>
                                <span>Conforme UEMOA N° 02/2015/CM/UEMOA</span>
                            </li>
                        </ul>

                        <p class="text-xs text-slate-500 dark:text-slate-400 italic pt-2 border-t border-slate-200 dark:border-slate-700">
                            Réf. vérification IDnorm : <code class="text-slate-700 dark:text-slate-200">{{ idnormVerificationId }}</code>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ═══ LEGACY : formulaire 4 étapes — désactivé en attente d'intégration API IDnorm ═══
                 Le flux ci-dessous reste dans le code pour référence et sera réactivé une fois les
                 endpoints API IDnorm intégrés côté backend. Retirer `v-if="false"` pour le réactiver.
            -->
            <template v-if="false">
                <div class="flex items-center justify-center gap-2 mb-8">
                    <button v-for="s in 4" :key="s" @click="goToStep(s)"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                        :class="stepClass(s)">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="stepIconClass(s)">
                            <template v-if="isStepComplete(s)">&#10003;</template>
                            <template v-else>{{ s }}</template>
                        </span>
                        <span class="hidden sm:inline">{{ stepLabels[s - 1] }}</span>
                    </button>
                </div>

                <div v-if="loading" class="text-center py-12 text-slate-500 dark:text-slate-400">Chargement...</div>

                <template v-else>
                    <!-- ═══ STEP 1: Identity ═══ -->
                    <div v-if="currentStep === 1" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
                        <h2 class="text-xl font-black mb-1">Étape 1 — Collecte des données d'identité</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Renseignez vos informations personnelles ou celles de votre entreprise. Ces données sont requises conformément à la Directive UEMOA N° 02/2015/CM/UEMOA.</p>

                        <!-- Person type -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold mb-2">Type de personne</label>
                            <div class="flex gap-3">
                                <button @click="form.person_type = 'physical'"
                                    class="flex-1 py-3 rounded-lg border-2 text-sm font-semibold transition-all"
                                    :class="form.person_type === 'physical' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    Personne physique
                                </button>
                                <button @click="form.person_type = 'moral'"
                                    class="flex-1 py-3 rounded-lg border-2 text-sm font-semibold transition-all"
                                    :class="form.person_type === 'moral' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    Personne morale
                                </button>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Prenom *</label>
                                <input v-model="form.first_name" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Nom *</label>
                                <input v-model="form.last_name" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Date de naissance *</label>
                                <input v-model="form.date_of_birth" type="date" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Lieu de naissance</label>
                                <input v-model="form.place_of_birth" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Nationalite *</label>
                                <input v-model="form.nationality" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Genre</label>
                                <select v-model="form.gender" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">--</option>
                                    <option value="male">Homme</option>
                                    <option value="female">Femme</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold mb-1">Adresse *</label>
                                <input v-model="form.address" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Ville *</label>
                                <input v-model="form.city" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Code postal</label>
                                <input v-model="form.postal_code" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Pays *</label>
                                <input v-model="form.country" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Telephone *</label>
                                <input v-model="form.phone" type="tel" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>

                        <!-- Legal entity fields -->
                        <template v-if="form.person_type === 'moral'">
                            <hr class="my-6 border-slate-200 dark:border-slate-700">
                            <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200 mb-4 uppercase tracking-wider">Informations de l'entreprise</h3>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Denomination sociale *</label>
                                    <input v-model="form.company_name" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">N RCCM *</label>
                                    <input v-model="form.company_registration_number" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Forme juridique</label>
                                    <select v-model="form.legal_form" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                        <option value="">--</option>
                                        <option value="ei">Entreprise Individuelle</option>
                                        <option value="suarl">SUARL</option>
                                        <option value="sarl">SARL</option>
                                        <option value="sas">SAS</option>
                                        <option value="sa">SA</option>
                                        <option value="gie">GIE</option>
                                        <option value="other">Autre</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Representant legal</label>
                                    <input v-model="form.legal_representative_name" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold mb-1">Adresse du siege</label>
                                    <input v-model="form.company_address" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                            </div>
                        </template>

                        <div class="mt-6 flex justify-end">
                            <button @click="saveStep1" :disabled="saving"
                                class="px-6 py-2.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm disabled:opacity-50">
                                {{ saving ? 'Enregistrement...' : 'Enregistrer et continuer' }}
                            </button>
                        </div>
                    </div>

                    <!-- ═══ STEP 2: Documents ═══ -->
                    <div v-if="currentStep === 2" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
                        <h2 class="text-xl font-black mb-1">Étape 2 — Vérification des documents KYC</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Fournissez un document d'identité officiel en cours de validité. Globalafrica+ vérifie l'authenticité via son service eKYC, compatible avec les documents d'identité locaux et internationaux.</p>

                        <div class="grid md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Type de document *</label>
                                <select v-model="form.document_type" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">--</option>
                                    <option value="cni">Carte Nationale d'Identite</option>
                                    <option value="passport">Passeport</option>
                                    <option value="permis">Permis de conduire</option>
                                    <option value="carte_sejour">Carte de sejour</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Numero du document *</label>
                                <input v-model="form.document_number" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Date d'expiration *</label>
                                <input v-model="form.document_expiry" type="date" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Pays de delivrance *</label>
                                <input v-model="form.document_issuing_country" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>

                        <!-- File uploads -->
                        <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200 mb-4 uppercase tracking-wider">Pieces jointes</h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div v-for="doc in docUploads" :key="doc.field"
                                class="border-2 border-dashed rounded-xl p-4 text-center transition-colors"
                                :class="form[doc.field + '_url'] ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 dark:border-slate-700 hover:border-orange-300'">
                                <div class="text-2xl mb-1">{{ form[doc.field + '_url'] ? '&#10003;' : doc.icon }}</div>
                                <div class="text-sm font-semibold mb-1">{{ doc.label }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ doc.hint }}</div>
                                <label class="inline-block px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer transition-colors"
                                    :class="form[doc.field + '_url'] ? 'bg-emerald-600 text-white' : 'bg-orange-100 text-orange-700 hover:bg-orange-200'">
                                    {{ form[doc.field + '_url'] ? 'Remplacer' : 'Telecharger' }}
                                    <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf"
                                        @change="uploadFile($event, doc.field)">
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button @click="currentStep = 1"
                                class="px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-semibold hover:bg-slate-50">
                                Retour
                            </button>
                            <button @click="saveStep2" :disabled="saving"
                                class="px-6 py-2.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm disabled:opacity-50">
                                {{ saving ? 'Enregistrement...' : 'Enregistrer et continuer' }}
                            </button>
                        </div>
                    </div>

                    <!-- ═══ STEP 3: Risk Assessment ═══ -->
                    <div v-if="currentStep === 3" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
                        <h2 class="text-xl font-black mb-1">Étape 3 — Évaluation du risque lié au client</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Informations requises pour l'évaluation du profil de risque conformément aux normes UEMOA. Cela inclut la source de vos fonds, notamment pour les investissements via escrow et les transferts diaspora.</p>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Profession / Occupation *</label>
                                <input v-model="form.occupation" type="text" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Source des fonds *</label>
                                <select v-model="form.source_of_funds" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">--</option>
                                    <option value="salaire">Salaire</option>
                                    <option value="activite_commerciale">Activite commerciale</option>
                                    <option value="investissements">Investissements</option>
                                    <option value="heritage">Heritage</option>
                                    <option value="epargne">Epargne</option>
                                    <option value="remittances">Transferts diaspora</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Volume mensuel estime (EUR) *</label>
                                <input v-model.number="form.expected_monthly_volume" type="number" min="0" step="100"
                                    class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Personne Politiquement Exposee (PPE) *</label>
                                <div class="flex gap-3 mt-1">
                                    <button @click="form.is_pep = false"
                                        class="flex-1 py-2 rounded-lg border-2 text-sm font-semibold"
                                        :class="form.is_pep === false ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 dark:border-slate-700'">
                                        Non
                                    </button>
                                    <button @click="form.is_pep = true"
                                        class="flex-1 py-2 rounded-lg border-2 text-sm font-semibold"
                                        :class="form.is_pep === true ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 dark:border-slate-700'">
                                        Oui
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Risk indicator preview -->
                        <div v-if="form.source_of_funds && form.occupation" class="mt-6 p-4 rounded-xl border"
                            :class="riskPreviewClass">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg"
                                    :class="riskIconClass">
                                    {{ riskIcon }}
                                </div>
                                <div>
                                    <div class="font-bold text-sm">Niveau de risque estime : {{ riskLabel }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Ce niveau sera confirme apres l'analyse complete.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button @click="currentStep = 2"
                                class="px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-semibold hover:bg-slate-50">
                                Retour
                            </button>
                            <button @click="saveStep3" :disabled="saving"
                                class="px-6 py-2.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm disabled:opacity-50">
                                {{ saving ? 'Enregistrement...' : 'Enregistrer et continuer' }}
                            </button>
                        </div>
                    </div>

                    <!-- ═══ STEP 4: AML Verification & Submit ═══ -->
                    <div v-if="currentStep === 4" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
                        <h2 class="text-xl font-black mb-1">Étape 4 — Vérification AML (Anti-Blanchiment)</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                            Soumettez votre dossier pour vérification automatisée via IDNorm.
                            Les contrôles anti-blanchiment, listes de sanctions et PPE seront effectués automatiquement,
                            conformément à l'article 81 de la Directive UEMOA et aux obligations de la Cellule Nationale de Traitement des Informations Financières.
                        </p>

                        <!-- Summary of completed steps -->
                        <div class="space-y-3 mb-6">
                            <div v-for="(s, idx) in summarySteps" :key="idx"
                                class="flex items-center gap-3 p-3 rounded-lg"
                                :class="s.done ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                                    :class="s.done ? 'bg-emerald-600 text-white' : 'bg-red-200 text-red-700'">
                                    <template v-if="s.done">&#10003;</template>
                                    <template v-else>!</template>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold" :class="s.done ? 'text-emerald-800' : 'text-red-800'">
                                        {{ s.title }}
                                    </div>
                                    <div class="text-xs" :class="s.done ? 'text-emerald-600' : 'text-red-600'">
                                        {{ s.done ? 'Complete' : 'A completer' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AML checks description -->
                        <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-5 mb-6">
                            <h3 class="font-bold text-sm mb-3">Vérifications qui seront effectuées par Globalafrica+ via IDNorm :</h3>
                            <div class="grid md:grid-cols-2 gap-3">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs text-blue-700">1</span>
                                    <span>Vérification d'identité biométrique (eKYC)</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs text-blue-700">2</span>
                                    <span>Authentification des documents officiels</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs text-blue-700">3</span>
                                    <span>Screening listes de sanctions internationales</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs text-blue-700">4</span>
                                    <span>Contrôle PPE, ayant droit économique & médias défavorables</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button @click="currentStep = 3"
                                class="px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-semibold hover:bg-slate-50">
                                Retour
                            </button>
                            <button @click="submitKyc" :disabled="saving || !allStepsComplete"
                                class="px-6 py-3 rounded-lg text-sm font-bold transition-all disabled:opacity-50"
                                :class="allStepsComplete
                                    ? 'bg-gradient-to-r from-orange-600 to-emerald-600 hover:from-orange-700 hover:to-emerald-700 text-white shadow-lg'
                                    : 'bg-slate-200 text-slate-500 dark:text-slate-400 cursor-not-allowed'">
                                {{ saving ? 'Soumission en cours...' : 'Soumettre pour verification IDNorm' }}
                            </button>
                        </div>
                    </div>
                </template>
            </template>
        </div>

        <!-- Toast -->
        <Teleport to="body">
            <div v-if="toast" class="fixed bottom-6 right-6 z-50 max-w-sm px-5 py-3 rounded-lg shadow-lg text-sm font-medium"
                :class="toast.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
                {{ toast.message }}
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

// ─────────────────────────────────────────────────────────────
// IDnorm hosted verification (temporaire — en attente de l'API)
// À remplacer par la création d'une session IDnorm côté backend
// lorsque les credentials/endpoints seront disponibles.
// ─────────────────────────────────────────────────────────────
const idnormVerificationId = '994b9944-e4f3-4bf7-a26e-7c9391f598d6';
// URL de vérification hébergée fournie par IDnorm (avec session token opaque).
// À remplacer dynamiquement par la création d'une session IDnorm côté backend
// une fois l'intégration API finalisée (endpoint à prévoir : POST /api/kyc/idnorm/session).
const idnormVerificationUrl = 'https://verify.idnorm.com?session=QUFVQUFBSUFBQUFBQUFBQUFBQUFBQUFBQUFBaFNPVnBBQUFBQUNHTzZta0FBQUFBRkdWc2FYUmxNMmx1Wm05QVoyMWhhV3d1WTI5dEFBQUFBQUFBQUFBQUFBQUFBQUFBQUFka1pXWmhkV3gwQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBYU1ERkxVRVpOV0RZeldEUkZSVGROVkRoTFJUWkJNelV3U3pZQUFBQUFBQUFBQUFBQUVBR2RwNkhDZHVodHpCcy94OWpRVVBGRkF3QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQWhpYU5LS0VLS1VNREMyNlNJYXZUMEdSL2JiY2pXRmNDK3NIWFJjVjRyUDB1K1FuUC9rWkQwbEIyZzJneEhGMmwwdGpWamIzdWNwTUhnYzJoTXUwUWVRPT0';
// QR code généré à la volée via qrserver.com (stopgap — à remplacer par
// génération locale via la lib `qrcode` en prod pour éviter la dépendance externe).
const qrCodeUrl = computed(() =>
    `https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data=${encodeURIComponent(idnormVerificationUrl)}`
);

const loading = ref(true);
const saving = ref(false);
const currentStep = ref(1);
const session = ref(null);
const toast = ref(null);

const stepLabels = ['Identite', 'Documents', 'Risque', 'AML'];

const form = reactive({
    person_type: 'physical',
    first_name: '', last_name: '', date_of_birth: '', place_of_birth: '',
    nationality: '', gender: '', address: '', city: '', postal_code: '',
    country: '', phone: '',
    company_name: '', company_address: '', company_registration_number: '',
    legal_form: '', legal_representative_name: '',
    document_type: '', document_number: '', document_expiry: '', document_issuing_country: '',
    document_front_url: '', document_back_url: '', selfie_url: '', proof_of_address_url: '',
    rccm_url: '', statuts_url: '',
    occupation: '', source_of_funds: '', expected_monthly_volume: 0, is_pep: false,
});

const kycVerified = computed(() => auth.kyc?.is_verified);
const completion = computed(() => session.value?.completion ?? 0);

const allStepsComplete = computed(() =>
    session.value?.step1_complete && session.value?.step2_complete && session.value?.step3_complete
);

const docUploads = computed(() => {
    const base = [
        { field: 'document_front', icon: '🪪', label: 'Recto du document', hint: 'JPG, PNG ou PDF - max 5Mo' },
        { field: 'document_back', icon: '🔄', label: 'Verso du document', hint: 'JPG, PNG ou PDF - max 5Mo' },
        { field: 'selfie', icon: '🤳', label: 'Selfie avec document', hint: 'Photo de vous tenant le document' },
        { field: 'proof_of_address', icon: '🏠', label: 'Justificatif de domicile', hint: 'Facture < 3 mois' },
    ];
    if (form.person_type === 'moral') {
        base.push(
            { field: 'rccm', icon: '📜', label: 'Extrait RCCM', hint: 'Datant de moins de 3 mois' },
            { field: 'statuts', icon: '📋', label: 'Statuts de l\'entreprise', hint: 'Copie certifiee conforme' },
        );
    }
    return base;
});

const summarySteps = computed(() => [
    { title: 'Etape 1 — Collecte des donnees d\'identite', done: session.value?.step1_complete },
    { title: 'Etape 2 — Verification des documents KYC', done: session.value?.step2_complete },
    { title: 'Etape 3 — Evaluation du risque client', done: session.value?.step3_complete },
    { title: 'Etape 4 — Verification AML', done: session.value?.step4_complete },
]);

// Risk preview
const estimatedRisk = computed(() => {
    if (form.is_pep) return 'high';
    if (form.expected_monthly_volume > 200000) return 'high';
    if (form.expected_monthly_volume > 50000) return 'medium';
    return 'low';
});
const riskLabel = computed(() => ({ low: 'Faible', medium: 'Moyen', high: 'Eleve' })[estimatedRisk.value]);
const riskIcon = computed(() => ({ low: '🟢', medium: '🟡', high: '🔴' })[estimatedRisk.value]);
const riskPreviewClass = computed(() => ({
    low: 'bg-emerald-50 border-emerald-200',
    medium: 'bg-amber-50 border-amber-200',
    high: 'bg-red-50 border-red-200',
})[estimatedRisk.value]);
const riskIconClass = computed(() => ({
    low: 'bg-emerald-100',
    medium: 'bg-amber-100',
    high: 'bg-red-100',
})[estimatedRisk.value]);

function stepClass(s) {
    if (s === currentStep.value) return 'bg-orange-100 text-orange-800 border border-orange-300';
    if (isStepComplete(s)) return 'bg-emerald-50 text-emerald-700';
    return 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700';
}
function stepIconClass(s) {
    if (isStepComplete(s)) return 'bg-emerald-600 text-white';
    if (s === currentStep.value) return 'bg-orange-600 text-white';
    return 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400';
}
function isStepComplete(s) {
    if (!session.value) return false;
    return session.value[`step${s}_complete`];
}
function goToStep(s) {
    currentStep.value = s;
}

async function loadSession() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/kyc/session');
        if (data.session) {
            session.value = data.session;
            // Pre-fill form
            Object.keys(form).forEach(k => {
                if (data.session[k] !== null && data.session[k] !== undefined) {
                    form[k] = data.session[k];
                }
            });
            // Go to next incomplete step
            if (data.session.status === 'verified') return;
            if (!data.session.step1_complete) currentStep.value = 1;
            else if (!data.session.step2_complete) currentStep.value = 2;
            else if (!data.session.step3_complete) currentStep.value = 3;
            else currentStep.value = 4;
        }
        // Pre-fill from user profile
        if (!form.first_name && auth.user?.name) {
            const parts = auth.user.name.split(' ');
            form.first_name = parts[0] || '';
            form.last_name = parts.slice(1).join(' ') || '';
        }
        if (!form.country && auth.user?.country) form.country = auth.user.country;
        if (!form.phone && auth.user?.phone) form.phone = auth.user.phone;
    } catch (e) {
        console.error('KYC session load error', e);
    } finally {
        loading.value = false;
    }
}

async function saveStep1() {
    saving.value = true;
    try {
        const { data } = await window.axios.post('/api/kyc/step1', {
            person_type: form.person_type,
            first_name: form.first_name, last_name: form.last_name,
            date_of_birth: form.date_of_birth, place_of_birth: form.place_of_birth,
            nationality: form.nationality, gender: form.gender,
            address: form.address, city: form.city, postal_code: form.postal_code,
            country: form.country, phone: form.phone,
            company_name: form.company_name, company_address: form.company_address,
            company_registration_number: form.company_registration_number,
            legal_form: form.legal_form, legal_representative_name: form.legal_representative_name,
        });
        session.value = data.session;
        showToast('Etape 1 enregistree.', 'success');
        currentStep.value = 2;
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de l\'enregistrement.', 'error');
    } finally { saving.value = false; }
}

async function saveStep2() {
    saving.value = true;
    try {
        const { data } = await window.axios.post('/api/kyc/step2', {
            document_type: form.document_type, document_number: form.document_number,
            document_expiry: form.document_expiry, document_issuing_country: form.document_issuing_country,
        });
        session.value = data.session;
        showToast('Etape 2 enregistree.', 'success');
        currentStep.value = 3;
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de l\'enregistrement.', 'error');
    } finally { saving.value = false; }
}

async function saveStep3() {
    saving.value = true;
    try {
        const { data } = await window.axios.post('/api/kyc/step3', {
            occupation: form.occupation, source_of_funds: form.source_of_funds,
            expected_monthly_volume: form.expected_monthly_volume, is_pep: form.is_pep,
        });
        session.value = data.session;
        showToast('Etape 3 enregistree.', 'success');
        currentStep.value = 4;
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de l\'enregistrement.', 'error');
    } finally { saving.value = false; }
}

async function uploadFile(event, field) {
    const file = event.target.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('type', field);
    try {
        const { data } = await window.axios.post('/api/kyc/upload', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form[field + '_url'] = data.url;
        session.value = data.session;
        showToast('Document televerse.', 'success');
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors du telechargement.', 'error');
    }
}

async function submitKyc() {
    saving.value = true;
    try {
        const { data } = await window.axios.post('/api/kyc/submit');
        session.value = data.session;
        showToast(data.message || 'KYC soumis pour verification.', 'success');
        // Refresh auth to get updated KYC status
        await auth.fetchUser();
        if (auth.kyc?.is_verified) {
            // DEV mode auto-verified
            setTimeout(() => router.push('/dashboard'), 1500);
        }
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de la soumission.', 'error');
    } finally { saving.value = false; }
}

async function restartKyc() {
    session.value = null;
    currentStep.value = 1;
    Object.keys(form).forEach(k => {
        if (typeof form[k] === 'boolean') form[k] = false;
        else if (typeof form[k] === 'number') form[k] = 0;
        else form[k] = '';
    });
    form.person_type = 'physical';
}

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(loadSession);
</script>
