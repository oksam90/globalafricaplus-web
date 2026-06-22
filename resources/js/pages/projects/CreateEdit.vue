<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/projets/mes-projets" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">← Mes projets</router-link>

        <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
            {{ isEdit ? 'Modifier le projet' : 'Nouveau projet' }}
        </h1>
        <p class="text-slate-600 dark:text-slate-300 mt-1">Présentez votre initiative pour trouver du financement, des talents et des partenaires.</p>

        <form @submit.prevent="submit" class="mt-8 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 space-y-5">
            <!-- Stade en premier : il pilote la structure du reste du formulaire -->
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

            <!-- ─── Section pilotée par le stade : Informations à fournir ─── -->
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

            <!-- ─── Section pilotée par le stade : Documents requis ─── -->
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

            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" :disabled="saving" name="save"
                    class="px-5 py-2.5 rounded-md bg-slate-800 hover:bg-slate-900 text-white font-semibold disabled:opacity-60">
                    {{ saving ? 'Enregistrement…' : 'Enregistrer comme brouillon' }}
                </button>
                <button type="button" @click="publishNow" :disabled="saving"
                    class="px-5 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                    {{ isEdit && form.status === 'published' ? 'Mettre à jour' : 'Publier' }}
                </button>
                <router-link to="/projets/mes-projets" class="px-5 py-2.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold">
                    Annuler
                </router-link>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { stageConfig, stageLabel } from '../../utils/projectStages';
import Field from '../../components/Field.vue';
import TagInput from '../../components/TagInput.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isEdit = computed(() => !!route.params.slug);

// Champs attendus selon le stade sélectionné (cf. utils/projectStages.js).
// Le pitch deck réutilise le champ existant pitch_deck_url (non dupliqué).
const currentStage = computed(() => stageConfig(form.stage));

const categories = ref([]);
const subCategories = ref([]);
const sdgs = ref([]);
const saving = ref(false);
const error = ref(null);
const projectId = ref(null);

const form = reactive({
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
});

function onCategoryChange() {
    form.sub_category_id = '';
    const cat = categories.value.find((c) => c.id == form.category_id);
    subCategories.value = cat?.sub_categories || [];
}

async function loadLookups() {
    const [sectorsRes, sdgsRes] = await Promise.all([
        window.axios.get('/api/sectors'),
        window.axios.get('/api/sdgs'),
    ]);
    categories.value = sectorsRes.data.data || [];
    sdgs.value = sdgsRes.data.data || [];
}

// Préremplit les champs de formalisation depuis le RoleProfile entrepreneur
// déjà chargé dans le store auth (/api/auth/me eager-load roleProfiles.role).
// Mapping RoleProfile.data → form :
//   legal_status        → legal_status
//   registration_number → rccm_number
//   tax_id              → tax_number
function hydrateOwnerLegalFields() {
    const profile = auth.profileFor('entrepreneur');
    const d = profile?.data || {};
    form.legal_status = form.legal_status || d.legal_status        || '';
    form.rccm_number  = form.rccm_number  || d.registration_number || '';
    form.tax_number   = form.tax_number   || d.tax_id              || '';
}

async function loadProject() {
    if (!isEdit.value) return;
    const { data } = await window.axios.get(`/api/projects/${route.params.slug}`);
    const p = data.data;
    projectId.value = p.id;
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
        // legal_status / rccm_number / tax_number sont hydratés depuis le
        // RoleProfile entrepreneur (commun à tous les projets du porteur) —
        // pas depuis le projet lui-même. hydrateOwnerLegalFields() s'en charge
        // en onMounted après loadProject().
        status: p.status,
    });
    onCategoryChange();
}

function cleanPayload() {
    const payload = { ...form };
    if (!payload.deadline) delete payload.deadline;
    if (!payload.sub_category_id) payload.sub_category_id = null;

    // Ne conserver dans stage_details que les clés du stade sélectionné, en
    // ignorant les champs vides — ainsi les données stockées restent cohérentes
    // avec le stade affiché si l'entrepreneur a changé de stade en cours de route.
    const allowed = [
        ...currentStage.value.info.map((f) => f.key),
        ...currentStage.value.docs.map((d) => d.key),
    ];
    const details = {};
    for (const key of allowed) {
        const val = (form.stage_details?.[key] ?? '').toString().trim();
        if (val) details[key] = val;
    }
    payload.stage_details = details;

    return payload;
}

async function save(status = null) {
    saving.value = true;
    error.value = null;
    try {
        const payload = cleanPayload();
        if (status) payload.status = status;

        let slug;
        if (isEdit.value) {
            const { data } = await window.axios.patch(`/api/projects/${projectId.value}`, payload);
            slug = data.data.slug;
        } else {
            const { data } = await window.axios.post('/api/projects', payload);
            slug = data.data.slug;
            projectId.value = data.data.id;
        }
        // Les champs juridiques ont été persistés dans le RoleProfile
        // entrepreneur côté backend. On rafraîchit le store auth pour que les
        // autres écrans (Profile/Edit, Dashboard, autres projets) voient
        // immédiatement la mise à jour et le badge se calcule correctement.
        try { await auth.fetchUser(); } catch { /* ignore */ }
        router.push(`/projets/${slug}`);
    } catch (e) {
        error.value = e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})[0]?.[0] ||
            "Impossible d'enregistrer le projet.";
    } finally {
        saving.value = false;
    }
}

function submit() { save('draft'); }
function publishNow() { save('published'); }

onMounted(async () => {
    await loadLookups();
    await loadProject();
    hydrateOwnerLegalFields();
});
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
