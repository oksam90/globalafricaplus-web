<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-center justify-between gap-4">
            <router-link to="/projets/mes-projets" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">← Mes projets</router-link>
            <span class="text-xs" :class="autosaveClass">{{ autosaveText }}</span>
        </div>

        <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
            {{ isEdit ? 'Modifier le projet' : 'Nouveau projet' }}
        </h1>
        <p class="text-slate-600 dark:text-slate-300 mt-1">
            Complétez votre demande étape par étape. Votre progression est enregistrée automatiquement — vous pouvez reprendre à tout moment.
        </p>

        <!-- Bannière de reprise de brouillon (nouveau projet) -->
        <div v-if="restoredFromLocal"
            class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 dark:border-emerald-700/50 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-200">
            <span>📝 Brouillon repris automatiquement <span v-if="restoredAtLabel">(sauvegardé {{ restoredAtLabel }})</span>.</span>
            <button type="button" @click="restartFresh" class="font-semibold underline hover:no-underline">Recommencer à zéro</button>
        </div>

        <!-- Barre de progression + étapes -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Étape {{ currentStep + 1 }} / {{ totalSteps }} — {{ steps[currentStep].label }}
                </span>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ progressPct }}%</span>
            </div>
            <div class="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500 transition-all duration-300"
                    :style="{ width: progressPct + '%' }"></div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button v-for="(s, i) in steps" :key="s.id" type="button" @click="goTo(i)"
                    class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border transition"
                    :class="i === currentStep
                        ? 'bg-emerald-600 text-white border-emerald-600'
                        : (i < currentStep
                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-700/50'
                            : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600 hover:border-emerald-300')">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                        :class="i === currentStep ? 'bg-white/20' : (i < currentStep ? 'bg-emerald-600 text-white' : 'bg-slate-200 dark:bg-slate-600')">
                        <span v-if="i < currentStep">✓</span><span v-else>{{ i + 1 }}</span>
                    </span>
                    <span class="hidden sm:inline">{{ s.label }}</span>
                </button>
            </div>
        </div>

        <form @submit.prevent class="mt-6 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">

            <!-- ÉTAPE 1 — Présentation -->
            <div v-show="currentStep === 0" class="space-y-5">
                <Field label="Stade du projet *" hint="Le stade détermine les informations et documents attendus par les investisseurs.">
                    <select v-model="form.stage" required class="input">
                        <option value="idea">Idée</option>
                        <option value="mvp">MVP</option>
                        <option value="launch">Lancement</option>
                        <option value="scaling">Croissance</option>
                    </select>
                </Field>

                <Field label="Titre du projet *">
                    <input v-model="form.title" type="text" required maxlength="200" class="input" />
                </Field>

                <Field label="Résumé (accroche) *" hint="Une phrase qui résume votre projet (500 caractères max).">
                    <textarea v-model="form.summary" rows="2" required maxlength="500" class="input"></textarea>
                </Field>

                <Field label="Description détaillée">
                    <textarea v-model="form.description" rows="6" maxlength="20000" class="input"
                        placeholder="Contexte, problème, solution, modèle économique, équipe…"></textarea>
                </Field>
            </div>

            <!-- ÉTAPE 2 — Détails -->
            <div v-show="currentStep === 1" class="space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <Field label="Secteur *">
                        <select v-model="form.category_id" @change="onCategoryChange" required class="input">
                            <option value="">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </Field>
                    <Field label="Sous-secteur">
                        <select v-model="form.sub_category_id" class="input" :disabled="!subCategories.length">
                            <option value="">—</option>
                            <option v-for="s in subCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </Field>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <Field label="Pays *">
                        <input v-model="form.country" type="text" required class="input" />
                    </Field>
                    <Field label="Ville">
                        <input v-model="form.city" type="text" class="input" />
                    </Field>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <Field label="Montant recherché *">
                        <input v-model.number="form.amount_needed" type="number" min="0" required class="input" />
                    </Field>
                    <Field label="Devise">
                        <select v-model="form.currency" class="input">
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                            <option value="XOF">XOF (CFA)</option>
                        </select>
                    </Field>
                    <Field label="Emplois visés">
                        <input v-model.number="form.jobs_target" type="number" min="0" class="input" />
                    </Field>
                </div>

                <Field label="Date limite">
                    <input v-model="form.deadline" type="date" class="input" />
                </Field>

                <Field label="Tags" hint="Ajoutez avec Entrée ou virgule">
                    <TagInput v-model="form.tags" placeholder="agritech, solaire…" />
                </Field>

                <Field label="Objectifs de Développement Durable (ODD)">
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <label v-for="s in sdgs" :key="s.id"
                            class="flex items-center gap-1 cursor-pointer text-xs p-2 rounded border"
                            :class="form.sdg_ids.includes(s.id) ? 'ring-2 ring-offset-1' : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-emerald-300 dark:hover:border-emerald-500/60'"
                            :style="form.sdg_ids.includes(s.id) ? { backgroundColor: s.color, color: 'white', borderColor: s.color } : {}">
                            <input type="checkbox" :value="s.id" v-model="form.sdg_ids" class="hidden" />
                            <span class="font-bold">{{ s.number }}</span>
                            <span class="truncate">{{ s.name }}</span>
                        </label>
                    </div>
                </Field>

                <div class="grid sm:grid-cols-2 gap-4">
                    <Field label="Site web">
                        <input v-model="form.website" type="url" placeholder="https://" class="input" />
                    </Field>
                    <Field label="Vidéo">
                        <input v-model="form.video_url" type="url" placeholder="https://" class="input" />
                    </Field>
                </div>
            </div>

            <!-- ÉTAPE 3 — Dossier du stade -->
            <div v-show="currentStep === 2" class="space-y-5">
                <!-- Informations à fournir -->
                <fieldset v-if="currentStage.info.length"
                    class="rounded-xl border border-sky-200 dark:border-sky-800/50 bg-sky-50/50 dark:bg-sky-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-sky-900 dark:text-sky-200">
                        Informations à fournir — stade « {{ stageLabel(form.stage) }} »
                    </legend>
                    <p class="text-xs text-sky-800/80 dark:text-sky-300/80 -mt-2">
                        Ces éléments aident les investisseurs à évaluer la maturité de votre projet au stade sélectionné.
                    </p>
                    <Field v-for="f in currentStage.info" :key="f.key" :label="f.label">
                        <textarea v-model="form.stage_details[f.key]" rows="3" maxlength="5000"
                            class="input" :placeholder="f.placeholder || ''"></textarea>
                    </Field>
                </fieldset>

                <!-- Documents requis -->
                <fieldset class="rounded-xl border border-violet-200 dark:border-violet-800/50 bg-violet-50/50 dark:bg-violet-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-violet-900 dark:text-violet-200">
                        Documents requis — stade « {{ stageLabel(form.stage) }} »
                    </legend>
                    <p class="text-xs text-violet-800/80 dark:text-violet-300/80 -mt-2">
                        Renseignez un lien public ou partagé (Google Drive, Dropbox, site…) vers chaque document.
                    </p>
                    <Field :label="currentStage.pitchLabel" hint="Lien vers le pitch deck">
                        <input v-model="form.pitch_deck_url" type="url" placeholder="https://" class="input" />
                    </Field>
                    <Field v-for="d in currentStage.docs" :key="d.key" :label="d.label" hint="Lien vers le document">
                        <input v-model="form.stage_details[d.key]" type="url" placeholder="https://" class="input" />
                    </Field>
                </fieldset>

                <!-- Localisation de l'entreprise (Lancement & Croissance) -->
                <fieldset v-if="currentStage.localization"
                    class="rounded-xl border border-teal-200 dark:border-teal-800/50 bg-teal-50/50 dark:bg-teal-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-teal-900 dark:text-teal-200">
                        Localisation de l'entreprise
                    </legend>
                    <p class="text-xs text-teal-800/80 dark:text-teal-300/80 -mt-2">
                        Justifiez l'occupation de vos locaux. Un dossier complet débloque le badge
                        <strong>« Localisation de l'entreprise »</strong> auprès des investisseurs. Renseignez des liens Drive partagés.
                    </p>

                    <Field label="Justificatif d'occupation des locaux *">
                        <select v-model="form.stage_details.loc_doc_type" class="input">
                            <option value="">—</option>
                            <option v-for="t in currentStage.localization.docTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </Field>
                    <Field v-if="form.stage_details.loc_doc_type" :label="`Lien Drive — ${locDocTypeLabel}`" hint="Lien Drive vers le document sélectionné">
                        <input v-model="form.stage_details.loc_doc_link" type="url" placeholder="https://drive.google.com/…" class="input" />
                    </Field>

                    <Field v-for="d in currentStage.localization.docs" :key="d.key"
                        :label="d.optional ? `${d.label} (facultatif)` : d.label"
                        hint="Lien Drive vers le document">
                        <input v-model="form.stage_details[d.key]" type="url" placeholder="https://drive.google.com/…" class="input" />
                    </Field>
                </fieldset>
            </div>

            <!-- ÉTAPE 4 — Financement & apport -->
            <div v-show="currentStep === 3" class="space-y-5">
                <!-- Preuves d'un financement antérieur -->
                <fieldset class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-amber-900 dark:text-amber-200">
                        Preuves d'un financement antérieur
                    </legend>
                    <p class="text-xs text-amber-800/80 dark:text-amber-300/80 -mt-2">
                        Un historique de financement bien documenté est un signal très positif pour les investisseurs,
                        surtout s'il s'accompagne de résultats concrets. Un dossier complet débloque le badge
                        <strong>« Preuves d'un financement antérieur »</strong>.
                    </p>

                    <Field label="Avez-vous déjà obtenu un financement pour ce projet / cette entreprise ou un autre?">
                        <select v-model="form.stage_details.fin_has_prior" class="input">
                            <option value="">—</option>
                            <option value="oui">Oui</option>
                            <option value="non">Non</option>
                        </select>
                    </Field>

                    <template v-if="form.stage_details.fin_has_prior === 'oui'">
                        <Field v-for="f in FINANCING.fields" :key="f.key" :label="f.label" hint="Lien Drive vers le document">
                            <input v-model="form.stage_details[f.key]" type="url" placeholder="https://drive.google.com/…" class="input" />
                        </Field>
                    </template>
                </fieldset>

                <!-- Preuves de l'apport personnel -->
                <fieldset class="rounded-xl border border-rose-200 dark:border-rose-800/50 bg-rose-50/50 dark:bg-rose-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-rose-900 dark:text-rose-200">
                        Preuves de l'apport personnel
                    </legend>
                    <p class="text-xs text-rose-800/80 dark:text-rose-300/80 -mt-2">
                        Un apport personnel clair et documenté est un signal fort de sérieux et d'alignement avec les
                        investisseurs. Un dossier complet débloque le badge
                        <strong>« Preuves de l'apport personnel »</strong>.
                    </p>

                    <Field label="Avez-vous réalisé un apport personnel dans ce projet / cette entreprise ?">
                        <select v-model="form.stage_details.eq_has_prior" class="input">
                            <option value="">—</option>
                            <option value="oui">Oui</option>
                            <option value="non">Non</option>
                        </select>
                    </Field>

                    <template v-if="form.stage_details.eq_has_prior === 'oui'">
                        <Field v-for="f in EQUITY.fields" :key="f.key" :label="f.label" hint="Lien Drive vers le document">
                            <input v-model="form.stage_details[f.key]" type="url" placeholder="https://drive.google.com/…" class="input" />
                        </Field>
                    </template>
                </fieldset>
            </div>

            <!-- ÉTAPE 5 — Entreprise & paiement -->
            <div v-show="currentStep === 4" class="space-y-5">
                <fieldset class="rounded-xl border border-indigo-200 dark:border-indigo-800/50 bg-indigo-50/50 dark:bg-indigo-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-indigo-900 dark:text-indigo-200">
                        Statut juridique &amp; formalisation
                    </legend>
                    <p class="text-xs text-indigo-800/80 dark:text-indigo-300/80 -mt-2">
                        Ces informations sont communes à tous vos projets. Complétez-les pour obtenir le badge
                        <strong>« Statut juridique &amp; formalisation »</strong> qui rassure vos investisseurs sur la fiabilité de votre entreprise.
                    </p>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <Field label="Statut juridique *">
                            <select v-model="form.legal_status" class="input">
                                <option value="">—</option>
                                <option value="informal">Informel (non enregistré)</option>
                                <option value="individual">Entreprise individuelle</option>
                                <option value="suarl">SUARL / SARLU</option>
                                <option value="sarl">SARL</option>
                                <option value="sas">SAS</option>
                                <option value="sa">SA</option>
                                <option value="gie">GIE</option>
                                <option value="other">Autre</option>
                            </select>
                        </Field>
                        <Field label="N° RCCM / Registre de commerce" hint="ex. SN-DKR-2024-B-12345">
                            <input v-model="form.rccm_number" type="text" maxlength="100" placeholder="SN-DKR-2024-B-12345" class="input" />
                        </Field>
                        <Field label="N° fiscal (NINEA / NIF / NCC)" hint="Identifiant fiscal de l'entreprise">
                            <input v-model="form.tax_number" type="text" maxlength="100" placeholder="0051234567 2A4" class="input" />
                        </Field>
                    </div>
                </fieldset>

                <fieldset class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-900/20 p-4 space-y-4">
                    <legend class="px-2 text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                        Compte de réception (séquestre)
                    </legend>
                    <p class="text-xs text-emerald-800/80 dark:text-emerald-300/80 -mt-2">
                        Ces informations sont nécessaires pour recevoir, par virement bancaire, le décaissement des jalons validés par vos investisseurs. Le compte doit être au nom de l'entreprise porteuse du projet.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Titulaire du compte" hint="Raison sociale de l'entreprise">
                            <input v-model="form.payout_account_holder" type="text" maxlength="200" placeholder="SARL AgriDrone Sahel" class="input" />
                        </Field>
                        <Field label="Banque" hint="Nom de l'établissement bancaire">
                            <input v-model="form.payout_bank_name" type="text" maxlength="150" placeholder="Ecobank Sénégal" class="input" />
                        </Field>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <Field label="IBAN" hint="Sans espaces, ex. SN08SN0100100123456789012345">
                            <input v-model="form.payout_iban" type="text" maxlength="34" placeholder="SN08SN0100100123456789012345" class="input uppercase font-mono" />
                        </Field>
                        <Field label="BIC / SWIFT" hint="8 ou 11 caractères">
                            <input v-model="form.payout_bic" type="text" maxlength="11" placeholder="ECOCSNDA" class="input uppercase font-mono" />
                        </Field>
                        <Field label="Pays du compte" hint="Code ISO à 2 lettres">
                            <input v-model="form.payout_bank_country" type="text" maxlength="2" placeholder="SN" class="input uppercase" />
                        </Field>
                    </div>
                </fieldset>
            </div>

            <!-- ÉTAPE 6 — Récapitulatif -->
            <div v-show="currentStep === 5" class="space-y-5">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Récapitulatif</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 -mt-2">
                    Vérifiez les informations avant de publier. Vous pouvez aussi enregistrer comme brouillon et revenir plus tard.
                </p>

                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Titre</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ form.title || '—' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Stade</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ stageLabel(form.stage) }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Secteur</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ categoryName }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Localisation</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ form.country || '—' }}<span v-if="form.city"> · {{ form.city }}</span></dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Montant recherché</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ form.amount_needed || 0 }} {{ form.currency }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-500 dark:text-slate-400">Emplois visés</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ form.jobs_target || 0 }}</dd>
                    </div>
                </dl>

                <div class="rounded-xl border border-slate-100 dark:border-slate-700 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Badges de confiance prévisibles</h3>
                    <ul class="space-y-1.5 text-sm">
                        <li :class="legalComplete ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                            {{ legalComplete ? '✓' : '○' }} Statut juridique &amp; formalisation
                        </li>
                        <li :class="bankComplete ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                            {{ bankComplete ? '✓' : '○' }} Compte de réception (séquestre)
                        </li>
                        <li v-if="form.stage_details.fin_has_prior === 'oui'" :class="financingComplete ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                            {{ financingComplete ? '✓' : '○' }} Preuves d'un financement antérieur
                        </li>
                        <li v-if="form.stage_details.eq_has_prior === 'oui'" :class="equityComplete ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
                            {{ equityComplete ? '✓' : '○' }} Preuves de l'apport personnel
                        </li>
                    </ul>
                </div>
            </div>

            <p v-if="error" class="mt-5 text-sm text-rose-600">{{ error }}</p>

            <!-- Navigation -->
            <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <button v-if="currentStep > 0" type="button" @click="goPrev"
                    class="px-4 py-2.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold">
                    ← Précédent
                </button>
                <span v-else></span>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="saveDraft" :disabled="saving"
                        class="px-4 py-2.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold disabled:opacity-60">
                        {{ saving ? 'Enregistrement…' : 'Enregistrer le brouillon' }}
                    </button>
                    <button v-if="currentStep < totalSteps - 1" type="button" @click="goNext"
                        class="px-5 py-2.5 rounded-md bg-slate-800 hover:bg-slate-900 text-white font-semibold">
                        Suivant →
                    </button>
                    <button v-else type="button" @click="publishNow" :disabled="saving"
                        class="px-5 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                        {{ isEdit && form.status === 'published' ? 'Mettre à jour' : 'Publier' }}
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { stageConfig, stageLabel, stageDetailKeys, FINANCING, EQUITY } from '../../utils/projectStages';
import Field from '../../components/Field.vue';
import TagInput from '../../components/TagInput.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isEdit = computed(() => !!route.params.slug);

// ── Étapes du formulaire multi-étapes ──
const steps = [
    { id: 'presentation', label: 'Présentation' },
    { id: 'details', label: 'Détails' },
    { id: 'dossier', label: 'Dossier du stade' },
    { id: 'financement', label: 'Financement & apport' },
    { id: 'entreprise', label: 'Entreprise & paiement' },
    { id: 'recap', label: 'Récapitulatif' },
];
const totalSteps = steps.length;
const currentStep = ref(0);
const progressPct = computed(() => Math.round(((currentStep.value + 1) / totalSteps) * 100));

function scrollTop() {
    if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' });
}
function goNext() { if (currentStep.value < totalSteps - 1) { currentStep.value++; scrollTop(); } }
function goPrev() { if (currentStep.value > 0) { currentStep.value--; scrollTop(); } }
function goTo(i) { currentStep.value = i; scrollTop(); }

// Champs attendus selon le stade sélectionné (cf. utils/projectStages.js).
const currentStage = computed(() => stageConfig(form.stage));

const locDocTypeLabel = computed(() => {
    const loc = currentStage.value.localization;
    if (!loc) return '';
    const t = loc.docTypes.find((t) => t.value === form.stage_details?.loc_doc_type);
    return t?.label || 'Document';
});

const categories = ref([]);
const subCategories = ref([]);
const sdgs = ref([]);
const saving = ref(false);
const error = ref(null);
const projectId = ref(null);
const savedSlug = ref(null);

function blankForm() {
    return {
        title: '', summary: '', description: '',
        category_id: '', sub_category_id: '',
        country: '', city: '',
        amount_needed: 0, currency: 'EUR', jobs_target: 0,
        stage: 'idea', deadline: '',
        stage_details: {},
        tags: [],
        sdg_ids: [],
        website: '', video_url: '', pitch_deck_url: '',
        legal_status: '', rccm_number: '', tax_number: '',
        payout_account_holder: '', payout_bank_name: '', payout_iban: '', payout_bic: '', payout_bank_country: '',
        status: 'draft',
    };
}
const form = reactive(blankForm());

function resetForm() {
    Object.assign(form, blankForm());
    subCategories.value = [];
}

// ── Récapitulatif : complétude indicative (miroir léger des badges) ──
const categoryName = computed(() => categories.value.find((c) => c.id == form.category_id)?.name || '—');
const legalComplete = computed(() => !!(form.legal_status && form.rccm_number && form.tax_number));
const bankComplete = computed(() => !!(form.payout_account_holder && form.payout_bank_name && form.payout_iban && form.payout_bic && form.payout_bank_country));
const financingComplete = computed(() => ['fin_contrat', 'fin_lettre_attribution', 'fin_attestation_institution', 'fin_releves_bancaires'].every((k) => form.stage_details?.[k]));
const equityComplete = computed(() => ['eq_declaration_montant', 'eq_detail_nature', 'eq_releves_bancaires', 'eq_attestation_bancaire'].every((k) => form.stage_details?.[k]));

function refreshSubCategories() {
    const cat = categories.value.find((c) => c.id == form.category_id);
    subCategories.value = cat?.sub_categories || [];
}
function onCategoryChange() {
    form.sub_category_id = '';
    refreshSubCategories();
}

async function loadLookups() {
    const [sectorsRes, sdgsRes] = await Promise.all([
        window.axios.get('/api/sectors'),
        window.axios.get('/api/sdgs'),
    ]);
    categories.value = sectorsRes.data.data || [];
    sdgs.value = sdgsRes.data.data || [];
}

function hydrateOwnerLegalFields() {
    const profile = auth.profileFor('entrepreneur');
    const d = profile?.data || {};
    form.legal_status = form.legal_status || d.legal_status || '';
    form.rccm_number = form.rccm_number || d.registration_number || '';
    form.tax_number = form.tax_number || d.tax_id || '';
}

async function loadProject() {
    if (!isEdit.value) return;
    const { data } = await window.axios.get(`/api/projects/${route.params.slug}`);
    const p = data.data;
    projectId.value = p.id;
    savedSlug.value = p.slug;
    Object.assign(form, {
        title: p.title,
        summary: p.summary,
        description: p.description || '',
        category_id: p.category_id || '',
        sub_category_id: p.sub_category_id || '',
        country: p.country,
        city: p.city || '',
        amount_needed: parseFloat(p.amount_needed) || 0,
        currency: p.currency || 'EUR',
        jobs_target: p.jobs_target || 0,
        stage: p.stage,
        deadline: p.deadline ? p.deadline.substring(0, 10) : '',
        stage_details: p.stage_details || {},
        tags: p.tags || [],
        sdg_ids: (p.sdgs || []).map((s) => s.id),
        website: p.website || '',
        video_url: p.video_url || '',
        pitch_deck_url: p.pitch_deck_url || '',
        payout_account_holder: p.payout_account_holder || '',
        payout_bank_name: p.payout_bank_name || '',
        payout_iban: p.payout_iban || '',
        payout_bic: p.payout_bic || '',
        payout_bank_country: p.payout_bank_country || '',
        status: p.status,
    });
    refreshSubCategories();
}

function cleanPayload() {
    const payload = { ...form };
    if (!payload.deadline) delete payload.deadline;
    if (!payload.sub_category_id) payload.sub_category_id = null;

    const allowed = stageDetailKeys(form.stage);
    const details = {};
    for (const key of allowed) {
        const val = (form.stage_details?.[key] ?? '').toString().trim();
        if (val) details[key] = val;
    }
    if (details.fin_has_prior !== 'oui') {
        for (const f of FINANCING.fields) delete details[f.key];
    }
    if (details.eq_has_prior !== 'oui') {
        for (const f of EQUITY.fields) delete details[f.key];
    }
    payload.stage_details = details;

    return payload;
}

// ─────────────────────────────────────────────────────────────────────────────
// Sauvegarde automatique
//   1) localStorage : immédiat, survit à un rafraîchissement / une fermeture /
//      un crash → reprise instantanée sur le même appareil.
//   2) backend (brouillon) : dès que le projet existe (édition, ou après un
//      premier "Enregistrer le brouillon"), on synchronise via PATCH debouncé,
//      pour une reprise multi-appareils depuis "Mes projets".
// ─────────────────────────────────────────────────────────────────────────────
const ready = ref(false);
const autosaveStatus = ref(''); // '' | 'local' | 'saving' | 'saved' | 'error'
const lastSavedAt = ref(null);
const restoredFromLocal = ref(false);
const restoredAt = ref(null);
let backendTimer = null;

// localStorage = filet de sécurité pour un NOUVEAU projet (pas encore de copie
// backend). En édition, c'est l'autosave backend (toutes les 2 s) qui assure la
// reprise — on n'utilise donc pas localStorage.
const LS_KEY = 'gaplus.project_draft.new';

// Le brouillon local n'est pertinent que tant qu'il a du contenu réel.
function hasContent() {
    return !!(
        String(form.title).trim() ||
        String(form.summary).trim() ||
        String(form.description).trim() ||
        projectId.value ||
        Object.keys(form.stage_details || {}).length
    );
}
function readLocal() {
    try {
        const raw = localStorage.getItem(LS_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch { return null; }
}
function persistLocal() {
    if (isEdit.value) return;          // édition → pas de brouillon local
    if (!hasContent()) { clearLocal(); return; }
    try {
        localStorage.setItem(LS_KEY, JSON.stringify({
            form,
            currentStep: currentStep.value,
            projectId: projectId.value,
            slug: savedSlug.value,
            savedAt: Date.now(),
        }));
    } catch { /* quota / private mode — non bloquant */ }
}
function clearLocal() {
    try { localStorage.removeItem(LS_KEY); } catch { /* ignore */ }
}

function applyLocal(local) {
    Object.assign(form, blankForm(), local.form || {});
    currentStep.value = Math.min(local.currentStep ?? 0, totalSteps - 1);
    if (local.projectId) projectId.value = local.projectId;
    if (local.slug) savedSlug.value = local.slug;
    refreshSubCategories();
}

function minimalValid() {
    return !!(
        String(form.title).trim() &&
        String(form.summary).trim() &&
        String(form.country).trim() &&
        form.category_id &&
        Number(form.amount_needed) > 0
    );
}

async function autosaveBackend() {
    if (!projectId.value || !minimalValid()) return;
    autosaveStatus.value = 'saving';
    try {
        const payload = cleanPayload();
        if (!isEdit.value && form.status !== 'published') payload.status = 'draft';
        const { data } = await window.axios.patch(`/api/projects/${projectId.value}`, payload);
        savedSlug.value = data.data.slug;
        autosaveStatus.value = 'saved';
        lastSavedAt.value = Date.now();
        persistLocal();
    } catch {
        autosaveStatus.value = 'error';
    }
}

function scheduleAutosave() {
    if (!ready.value) return;
    persistLocal();
    if (!isEdit.value && !projectId.value) {
        // Nouveau projet pas encore persisté → seul le brouillon local existe.
        autosaveStatus.value = 'local';
        lastSavedAt.value = Date.now();
    }
    if (projectId.value) {
        autosaveStatus.value = 'saving';
        clearTimeout(backendTimer);
        backendTimer = setTimeout(autosaveBackend, 2000);
    }
}

watch(form, scheduleAutosave, { deep: true });
watch(currentStep, () => { if (ready.value) persistLocal(); });

const lastSavedLabel = computed(() => (lastSavedAt.value ? new Date(lastSavedAt.value).toLocaleTimeString('fr-FR') : ''));
const restoredAtLabel = computed(() => (restoredAt.value ? new Date(restoredAt.value).toLocaleString('fr-FR') : ''));
const autosaveText = computed(() => {
    if (autosaveStatus.value === 'saving') return 'Enregistrement…';
    if (autosaveStatus.value === 'error') return '⚠ Échec — nouvelle tentative à la prochaine modification';
    if (autosaveStatus.value === 'saved') return `✓ Enregistré à ${lastSavedLabel.value}`;
    if (autosaveStatus.value === 'local') return `✓ Brouillon enregistré à ${lastSavedLabel.value}`;
    return 'Sauvegarde automatique activée';
});
const autosaveClass = computed(() => {
    if (autosaveStatus.value === 'error') return 'text-rose-600 dark:text-rose-400';
    if (autosaveStatus.value === 'saving') return 'text-slate-400';
    if (autosaveStatus.value === 'saved' || autosaveStatus.value === 'local') return 'text-emerald-600 dark:text-emerald-400';
    return 'text-slate-400 dark:text-slate-500';
});

// ── Actions bannière de reprise ──
function restartFresh() {
    clearLocal();
    resetForm();
    projectId.value = null;
    savedSlug.value = null;
    currentStep.value = 0;
    restoredFromLocal.value = false;
    autosaveStatus.value = '';
    lastSavedAt.value = null;
}
// ── Enregistrement / publication ──
async function save(status, { navigate = true } = {}) {
    saving.value = true;
    error.value = null;
    try {
        const payload = cleanPayload();
        if (status) payload.status = status;

        let slug;
        if (projectId.value) {
            const { data } = await window.axios.patch(`/api/projects/${projectId.value}`, payload);
            slug = data.data.slug;
        } else {
            const { data } = await window.axios.post('/api/projects', payload);
            slug = data.data.slug;
            projectId.value = data.data.id;
        }
        savedSlug.value = slug;

        // Champs juridiques persistés dans le RoleProfile entrepreneur côté
        // backend → on rafraîchit le store auth pour les autres écrans/badges.
        try { await auth.fetchUser(); } catch { /* ignore */ }

        lastSavedAt.value = Date.now();
        autosaveStatus.value = 'saved';

        if (navigate) {
            clearLocal();
            router.push(`/projets/${slug}`);
        } else {
            persistLocal();
        }
        return true;
    } catch (e) {
        error.value = e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})[0]?.[0] ||
            "Impossible d'enregistrer le projet.";
        return false;
    } finally {
        saving.value = false;
    }
}

// "Enregistrer le brouillon" — reste sur le formulaire pour continuer la saisie.
async function saveDraft() {
    if (!minimalValid()) {
        error.value = 'Renseignez au minimum le titre, le résumé, le secteur, le pays et le montant pour enregistrer un brouillon.';
        currentStep.value = (!form.title || !form.summary) ? 0 : 1;
        scrollTop();
        return;
    }
    await save(isEdit.value && form.status === 'published' ? 'published' : 'draft', { navigate: false });
}

function validatePublish() {
    if (!String(form.title).trim() || !String(form.summary).trim()) {
        error.value = 'Le titre et le résumé sont requis.';
        return 0;
    }
    if (!form.category_id || !String(form.country).trim() || !(Number(form.amount_needed) > 0)) {
        error.value = 'Le secteur, le pays et un montant supérieur à 0 sont requis.';
        return 1;
    }
    return -1;
}
function publishNow() {
    const bad = validatePublish();
    if (bad >= 0) { currentStep.value = bad; scrollTop(); return; }
    save('published', { navigate: true });
}

onMounted(async () => {
    await loadLookups();

    if (isEdit.value) {
        await loadProject();
    } else {
        const local = readLocal();
        if (local) {
            applyLocal(local);
            restoredFromLocal.value = true;
            restoredAt.value = local.savedAt;
        }
    }

    hydrateOwnerLegalFields();
    await nextTick();
    ready.value = true;
});

onBeforeUnmount(() => { clearTimeout(backendTimer); });
</script>

<style scoped>
.input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    outline: none;
    background: white;
    color: rgb(15 23 42); /* slate-900 */
}
.input::placeholder { color: rgb(148 163 184); /* slate-400 */ }
.input:focus { border-color: rgb(52 211 153); }

:global(.dark) .input {
    background: rgb(15 23 42); /* slate-900 */
    border-color: rgb(71 85 105); /* slate-600 */
    color: rgb(241 245 249); /* slate-100 */
}
:global(.dark) .input::placeholder { color: rgb(100 116 139); /* slate-500 */ }
:global(.dark) .input:focus { border-color: rgb(52 211 153); }

/* Also style native option elements inside <select class="input"> in dark mode */
:global(.dark) .input option {
    background: rgb(15 23 42);
    color: rgb(241 245 249);
}
</style>
