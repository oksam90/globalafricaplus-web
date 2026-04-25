<template>
    <div v-if="!auth.kyc?.is_verified" class="bg-gradient-to-r from-orange-50 via-amber-50 to-red-50 dark:from-orange-950/40 dark:via-amber-950/40 dark:to-red-950/40 border-2 border-orange-300 dark:border-orange-800/60 rounded-2xl p-6 mb-8">
        <!-- Alert header -->
        <div class="flex items-start gap-4">
            <div class="shrink-0">
                <div class="w-12 h-12 rounded-full bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-orange-900 dark:text-orange-200">
                    Veuillez dans un premier temps renseigner les informations de votre KYC.
                </h3>

                <!-- KYC pending status -->
                <div v-if="auth.kyc?.has_pending" class="mt-2 flex items-center gap-2">
                    <span class="flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-2.5 w-2.5 rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    <span class="text-sm font-semibold text-amber-700 dark:text-amber-300">Vérification en cours...</span>
                </div>

                <!-- Explanation text with read more -->
                <div class="mt-4 text-sm text-slate-700 dark:text-slate-200 leading-relaxed space-y-3">
                    <p class="font-semibold text-orange-800 dark:text-orange-300 text-base">C'est quoi le KYC ?</p>
                    <p class="italic text-slate-500 dark:text-slate-400 text-xs">
                        Les informations remplies à chaque étape du formulaire seront enregistrées avant soumission pour une meilleure expérience.
                    </p>
                    <p>
                        Le formulaire KYC est une mesure de sécurité que Globalafrica+ s'engage à prendre pour identifier ses utilisateurs, investisseurs, porteurs de projets et partenaires institutionnels.
                    </p>
                    <p>
                        Globalafrica+ étant une plateforme numérique panafricaine facilitant la mise en relation entre acteurs économiques, les investissements productifs, les transactions financières via mobile money et passerelles internationales, ainsi que les mécanismes d'escrow (séquestre), elle est assujettie aux obligations de lutte contre le blanchiment de capitaux et le financement du terrorisme et de la prolifération, conformément aux dispositions de l'article 5 de la Directive de l'Union Économique et Monétaire Ouest Africaine (UEMOA) N° 02/2015/CM/UEMOA. Globalafrica+ s'est engagée à prendre des mesures afin d'identifier ses clients et d'éviter que ses services soient utilisés à des fins de blanchiment d'argent ou tout autres crimes financiers.
                    </p>
                    <p>
                        Compte tenu de l'article 18 de ladite Directive, Globalafrica+ a instauré un principe d'identification de ses utilisateurs, un processus d'identification qui se déroule en deux étapes :
                    </p>
                    <ol class="list-decimal pl-5 space-y-1">
                        <li>La collecte des informations ;</li>
                        <li>La vérification des informations par des justificatifs (via notre partenaire de vérification d'identité numérique eKYC).</li>
                    </ol>
                    <p>Et devant se faire dans les conditions suivantes :</p>

                    <!-- Truncated content -->
                    <template v-if="!showMore">
                        <p>
                            <strong>A. Personnes physiques</strong> — L'identification d'une personne physique implique l'obtention des nom et prénoms complets, de la date et du lieu de naissance et de l'adresse de son domicile principal. La vérification de l'identité d'une personne physique requiert la présentation d'un document officiel original en cours de validité et comportant une photographie, dont il en est pris copie...
                        </p>
                        <button @click="showMore = true"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 mt-1">
                            Lire plus
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </template>

                    <!-- Full content -->
                    <template v-if="showMore">
                        <div class="space-y-3">
                            <p>
                                <strong>A. Personnes physiques</strong> — L'identification d'une personne physique implique l'obtention des nom et prénoms complets, de la date et du lieu de naissance et de l'adresse de son domicile principal. La vérification de l'identité d'une personne physique requiert la présentation d'un document officiel original en cours de validité et comportant une photographie, dont il en est pris copie. La vérification de son adresse est effectuée par la présentation d'un document de nature à en rapporter la preuve ou par tout autre moyen. Les mentions à relever et conserver comprennent les noms, prénoms, la date et le lieu de naissance de la personne ainsi que la nature, la date et lieu de délivrance du document. Globalafrica+ vérifie l'authenticité du document présenté via son service de vérification d'identité renforcée (eKYC), compatible avec les documents d'identité locaux et internationaux.
                            </p>
                            <p>
                                S'il s'agit d'une personne physique commerçante ou d'un entrepreneur souhaitant publier un projet sur la plateforme, cette dernière est tenue de fournir, en outre, toute pièce attestant de son immatriculation au Registre du Commerce et du Crédit Mobilier. Lorsque la vérification de l'identité ne peut avoir lieu en présence de la personne concernée — notamment pour les membres de la diaspora agissant depuis l'étranger — Globalafrica+ met en oeuvre, en application des dispositions de l'article 40 de la Directive, des mesures de vigilance complémentaires, incluant la vérification documentaire à distance et la reconnaissance faciale.
                            </p>
                            <p>
                                <strong>B. Personnes morales</strong> — L'identification d'une personne morale, d'une succursale ou d'un bureau de représentation — y compris les institutions gouvernementales, les agences de développement et les organisations partenaires utilisant la plateforme — implique l'obtention et la vérification d'informations sur la dénomination sociale, l'adresse du siège social, l'identité et les pouvoirs des associés et dirigeants sociaux mentionnés dans l'Acte uniforme concerné ou de leurs équivalents en droit étranger, la preuve de sa constitution légale, à savoir l'original, voire l'expédition ou la copie certifiée conforme de tout acte ou extrait du Registre du Commerce et du Crédit Mobilier datant de moins de trois mois, attestant notamment de sa forme juridique. Lorsque la vérification de l'identité ne peut avoir lieu en présence du représentant de la personne morale, Globalafrica+ met en oeuvre, en application des dispositions de l'article 40 de la Directive, des mesures de vigilance complémentaires.
                            </p>
                            <p>
                                <strong>C. L'ayant droit économique</strong> — Au cas où il n'est pas certain que le client agit pour son propre compte — notamment dans le cadre d'investissements réalisés via le mécanisme d'escrow ou de micro-investissements facilités par la plateforme — Globalafrica+ se renseigne par tout moyen sur l'identité du véritable donneur d'ordre. Après vérification, si le doute persiste sur l'identité de l'ayant droit économique, il doit être mis fin à l'opération, sans préjudice de l'obligation de déclarer les soupçons, auprès de la Cellule Nationale de Traitement des Informations Financières, dans les conditions fixées à l'article 81 de la Directive. Si le client est un avocat, un notaire, un professionnel de l'expertise comptable ou du commissariat aux comptes, un courtier en valeurs mobilières, intervenant en tant qu'intermédiaire financier, il ne pourra invoquer le secret professionnel pour refuser de communiquer l'identité de l'ayant droit économique.
                            </p>
                        </div>
                        <button @click="showMore = false"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 mt-1">
                            Lire moins
                            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </template>
                </div>

                <!-- 4 steps overview -->
                <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="step in steps" :key="step.num"
                        class="bg-white dark:bg-slate-800 rounded-xl p-3 border text-center"
                        :class="step.done ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-900/30' : 'border-orange-100 dark:border-orange-900/50'">
                        <div class="w-8 h-8 rounded-full mx-auto flex items-center justify-center text-sm font-bold mb-1.5"
                            :class="step.done ? 'bg-emerald-600 text-white' : 'bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300'">
                            <template v-if="step.done">&#10003;</template>
                            <template v-else>{{ step.num }}</template>
                        </div>
                        <div class="text-xs font-semibold" :class="step.done ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-200'">
                            {{ step.title }}
                        </div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ step.desc }}</div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="mt-6">
                    <router-link to="/kyc"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm shadow-sm transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Remplir le formulaire KYC
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const showMore = ref(false);

const steps = computed(() => [
    {
        num: 1,
        title: 'Collecte d\'identite',
        desc: 'Donnees personnelles',
        done: auth.kyc?.completion >= 25,
    },
    {
        num: 2,
        title: 'Documents KYC',
        desc: 'Verification des pieces',
        done: auth.kyc?.completion >= 50,
    },
    {
        num: 3,
        title: 'Evaluation du risque',
        desc: 'Analyse du profil client',
        done: auth.kyc?.completion >= 75,
    },
    {
        num: 4,
        title: 'Verification AML',
        desc: 'Anti-blanchiment',
        done: auth.kyc?.completion >= 100,
    },
]);
</script>
