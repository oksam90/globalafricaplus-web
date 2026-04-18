<template>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/profil" class="text-sm text-emerald-700 hover:underline">← Mes profils</router-link>

        <div v-if="loading" class="mt-8 text-slate-500">Chargement…</div>

        <template v-else-if="role">
            <header class="mt-4 mb-8">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Profil</div>
                <h1 class="text-3xl font-black tracking-tight">{{ role.name }}</h1>
                <p class="text-slate-600 mt-1">{{ role.description }}</p>

                <div class="mt-4 flex items-center gap-3">
                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all" :style="{ width: completion + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold text-slate-700">{{ completion }}%</span>
                </div>
            </header>

            <form @submit.prevent="save" class="bg-white border border-slate-100 rounded-2xl p-6 space-y-5">
                <!-- Entrepreneur -->
                <template v-if="slug === 'entrepreneur'">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 mb-2">
                        <p class="text-sm text-emerald-800 font-medium">Profil Entrepreneur</p>
                        <p class="text-xs text-emerald-600 mt-1">Complétez votre profil pour maximiser la visibilité de vos projets et accéder au Hub de formalisation.</p>
                    </div>
                    <Field label="Nom de votre entreprise / projet *">
                        <input v-model="form.company_name" type="text" class="input" maxlength="150" />
                    </Field>
                    <Field label="Secteurs d'activité *" hint="Ajoutez avec la touche Entrée">
                        <TagInput v-model="form.sectors" placeholder="agritech, fintech…" />
                    </Field>
                    <Field label="Pitch de votre projet *" hint="Résumez votre vision en quelques lignes">
                        <textarea v-model="form.pitch" rows="4" class="input" maxlength="2000" placeholder="Décrivez votre projet, le problème résolu et votre avantage concurrentiel…"></textarea>
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Années d'expérience *">
                            <input v-model.number="form.years_experience" type="number" min="0" max="80" class="input" />
                        </Field>
                        <Field label="Taille de l'équipe">
                            <input v-model.number="form.team_size" type="number" min="1" max="10000" class="input" placeholder="Nombre de personnes" />
                        </Field>
                    </div>

                    <div class="border-t border-slate-100 pt-5 mt-2">
                        <h3 class="text-sm font-bold text-slate-700 mb-3">Statut juridique & formalisation</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Statut juridique *" hint="Informel si non encore formalisé">
                            <select v-model="form.legal_status" class="input">
                                <option value="">—</option>
                                <option value="informal">Informel (non formalisé)</option>
                                <option value="individual">Entreprise individuelle</option>
                                <option value="suarl">SUARL</option>
                                <option value="sarl">SARL</option>
                                <option value="sas">SAS</option>
                                <option value="sa">SA</option>
                                <option value="gie">GIE</option>
                                <option value="other">Autre</option>
                            </select>
                        </Field>
                        <Field label="Pays d'enregistrement">
                            <input v-model="form.registration_country" type="text" placeholder="Sénégal" class="input" />
                        </Field>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="N° RCCM / Registre de commerce">
                            <input v-model="form.registration_number" type="text" placeholder="SN-DKR-2024-A-12345" class="input" maxlength="100" />
                        </Field>
                        <Field label="N° fiscal (NINEA / NIF / NCC)">
                            <input v-model="form.tax_id" type="text" placeholder="12345678 A 1" class="input" maxlength="100" />
                        </Field>
                    </div>
                    <Field label="Date de création">
                        <input v-model="form.founding_date" type="date" class="input" />
                    </Field>

                    <div class="border-t border-slate-100 pt-5 mt-2">
                        <h3 class="text-sm font-bold text-slate-700 mb-3">Liens & contact</h3>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Site web">
                            <input v-model="form.website" type="url" placeholder="https://" class="input" />
                        </Field>
                        <Field label="Profil LinkedIn">
                            <input v-model="form.linkedin_url" type="url" placeholder="https://linkedin.com/in/…" class="input" />
                        </Field>
                    </div>
                    <Field label="Téléphone">
                        <input v-model="form.phone" type="tel" placeholder="+221 77 000 00 00" class="input" />
                    </Field>

                    <div v-if="form.legal_status === 'informal'" class="bg-amber-50 border border-amber-100 rounded-xl p-4 mt-2">
                        <p class="text-xs text-amber-700"><strong>💡 Astuce :</strong> Vous êtes en secteur informel ? Accédez au <router-link to="/formalisation" class="underline font-semibold">Hub de formalisation</router-link> pour un guide pas-à-pas adapté à votre pays : création d'entreprise, obtention de patente, accès au micro-crédit.</p>
                    </div>
                </template>

                <!-- Investor -->
                <template v-else-if="slug === 'investor'">
                    <Field label="Type d'investisseur">
                        <select v-model="form.investor_type" class="input">
                            <option value="">—</option>
                            <option value="individual">Particulier</option>
                            <option value="family_office">Family office</option>
                            <option value="vc">Fonds / VC</option>
                            <option value="institutional">Institutionnel</option>
                        </select>
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Ticket min">
                            <input v-model.number="form.investment_min" type="number" min="0" class="input" />
                        </Field>
                        <Field label="Ticket max">
                            <input v-model.number="form.investment_max" type="number" min="0" class="input" />
                        </Field>
                    </div>
                    <Field label="Devise">
                        <select v-model="form.currency" class="input">
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                            <option value="XOF">XOF</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </Field>
                    <Field label="Secteurs préférés">
                        <TagInput v-model="form.preferred_sectors" placeholder="fintech, healthtech…" />
                    </Field>
                    <Field label="Pays cibles">
                        <TagInput v-model="form.preferred_countries" placeholder="Sénégal, Côte d'Ivoire…" />
                    </Field>
                </template>

                <!-- Mentor -->
                <template v-else-if="slug === 'mentor'">
                    <Field label="Domaines d'expertise">
                        <TagInput v-model="form.expertise" placeholder="Stratégie, Marketing, Finance…" />
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Tarif horaire">
                            <input v-model.number="form.hourly_rate" type="number" min="0" class="input" />
                        </Field>
                        <Field label="Devise">
                            <select v-model="form.currency" class="input">
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="XOF">XOF</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="Disponibilité (h / semaine)">
                        <input v-model.number="form.availability_hours_week" type="number" min="0" max="60" class="input" />
                    </Field>
                    <Field label="Langues parlées">
                        <TagInput v-model="form.languages" placeholder="français, anglais…" />
                    </Field>
                    <Field label="Profil LinkedIn">
                        <input v-model="form.linkedin_url" type="url" placeholder="https://linkedin.com/in/…" class="input" />
                    </Field>
                </template>

                <!-- Jobseeker -->
                <template v-else-if="slug === 'jobseeker'">
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 mb-2">
                        <p class="text-sm text-amber-800 font-medium">Profil Chercheur d'emploi</p>
                        <p class="text-xs text-amber-600 mt-1">Complétez votre profil pour être visible par les entrepreneurs qui recrutent et bénéficier du matching IA.</p>
                    </div>
                    <Field label="Titre / headline *" hint="Ex: Développeuse full-stack, 5 ans d'expérience">
                        <input v-model="form.headline" type="text" placeholder="Développeuse full-stack, 5 ans d'expérience" class="input" maxlength="150" />
                    </Field>
                    <Field label="Bio / résumé professionnel" hint="Décrivez brièvement votre parcours et vos atouts">
                        <textarea v-model="form.bio" rows="3" class="input" maxlength="1000" placeholder="Passionné(e) par l'innovation en Afrique, j'ai 5 ans d'expérience dans…"></textarea>
                    </Field>
                    <Field label="Postes recherchés *">
                        <TagInput v-model="form.desired_roles" placeholder="Product manager, Data analyst…" />
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Années d'expérience *">
                            <input v-model.number="form.experience_years" type="number" min="0" max="60" class="input" />
                        </Field>
                        <Field label="Disponibilité *">
                            <select v-model="form.availability" class="input">
                                <option value="">—</option>
                                <option value="immediate">Immédiate</option>
                                <option value="1_month">1 mois</option>
                                <option value="3_months">3 mois</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="Formation / diplôme *" hint="Votre plus haut diplôme ou formation pertinente">
                        <input v-model="form.education" type="text" placeholder="Master en Informatique — Université Cheikh Anta Diop" class="input" maxlength="300" />
                    </Field>
                    <Field label="Langues parlées *">
                        <TagInput v-model="form.languages" placeholder="français, anglais, wolof…" />
                    </Field>
                    <Field label="Certifications">
                        <TagInput v-model="form.certifications" placeholder="AWS Certified, PMP, Google Analytics…" />
                    </Field>
                    <Field label="Pays préférés">
                        <TagInput v-model="form.preferred_countries" placeholder="Sénégal, Côte d'Ivoire, Rwanda…" />
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Lien CV">
                            <input v-model="form.cv_url" type="url" placeholder="https://" class="input" />
                        </Field>
                        <Field label="Portfolio / site perso">
                            <input v-model="form.portfolio_url" type="url" placeholder="https://" class="input" />
                        </Field>
                    </div>
                    <Field label="Profil LinkedIn">
                        <input v-model="form.linkedin_url" type="url" placeholder="https://linkedin.com/in/…" class="input" />
                    </Field>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.open_to_remote" type="checkbox" class="accent-emerald-600" />
                        Ouvert au travail à distance
                    </label>
                    <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 mt-2">
                        <p class="text-xs text-sky-700"><strong>Astuce :</strong> Gérez vos compétences techniques depuis la page <router-link to="/emploi/mes-competences" class="underline font-semibold">Mes compétences</router-link> pour un meilleur matching.</p>
                    </div>
                </template>

                <!-- Government -->
                <template v-else-if="slug === 'government'">
                    <div class="bg-sky-50 border border-sky-100 rounded-xl p-4 mb-2">
                        <p class="text-sm text-sky-800 font-medium">Profil Institution Gouvernementale</p>
                        <p class="text-xs text-sky-600 mt-1">Ce profil vous permet de publier des appels à projets officiels, gérer des zones économiques spéciales et suivre les projets de votre territoire.</p>
                    </div>
                    <Field label="Ministère / Institution *">
                        <input v-model="form.ministry" type="text" placeholder="Ex: Ministère de l'Économie et des Finances" class="input" />
                    </Field>
                    <Field label="Département / Direction">
                        <input v-model="form.department" type="text" placeholder="Ex: Direction de l'Investissement" class="input" />
                    </Field>
                    <Field label="Poste / Fonction *">
                        <input v-model="form.position" type="text" placeholder="Ex: Directeur des Investissements" class="input" />
                    </Field>
                    <Field label="Périmètre du mandat *" hint="Décrivez brièvement votre mandat et vos responsabilités">
                        <textarea v-model="form.mandate_scope" rows="2" maxlength="300"
                            placeholder="Ex: Coordination des appels à projets pour le secteur agritech dans la région de Dakar…"
                            class="input"></textarea>
                    </Field>
                    <Field label="Email officiel *">
                        <input v-model="form.official_email" type="email" placeholder="prenom.nom@ministere.gouv.sn" class="input" />
                    </Field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Pays *">
                            <input v-model="form.country" type="text" placeholder="Sénégal" class="input" />
                        </Field>
                        <Field label="Téléphone officiel">
                            <input v-model="form.phone" type="tel" placeholder="+221 33 000 00 00" class="input" />
                        </Field>
                    </div>
                    <Field label="Secteurs prioritaires">
                        <TagInput v-model="form.priority_sectors" placeholder="agritech, énergie, tourisme…" />
                    </Field>
                    <Field label="Site web institutionnel">
                        <input v-model="form.website" type="url" placeholder="https://www.ministere.gouv.sn" class="input" />
                    </Field>
                </template>

                <!-- Admin -->
                <template v-else-if="slug === 'admin'">
                    <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-2">
                        <p class="text-sm text-red-800 font-medium">Profil Administrateur</p>
                        <p class="text-xs text-red-600 mt-1">Ce profil contrôle l'accès aux outils de gestion de la plateforme (modération, utilisateurs, analytics).</p>
                    </div>
                    <Field label="Département / Service *">
                        <input v-model="form.department" type="text" placeholder="Ex: Direction technique, Support, Opérations" class="input" />
                    </Field>
                    <Field label="Responsabilité principale *">
                        <textarea v-model="form.responsibility" rows="2" maxlength="300"
                            placeholder="Décrivez votre rôle : modération de contenus, gestion des utilisateurs, configuration technique…"
                            class="input"></textarea>
                    </Field>
                    <Field label="Téléphone de support">
                        <input v-model="form.phone_support" type="tel" placeholder="+221 77 000 00 00" class="input" />
                    </Field>
                    <Field label="Langues de travail *">
                        <TagInput v-model="form.languages" placeholder="français, anglais, wolof…" />
                    </Field>
                    <Field label="Canaux de notification préférés">
                        <TagInput v-model="form.notification_channels" placeholder="email, sms, slack…" />
                    </Field>
                    <Field label="Notes de modération / politique interne">
                        <textarea v-model="form.moderation_notes" rows="3" maxlength="2000"
                            placeholder="Règles ou guidelines de modération à suivre…"
                            class="input"></textarea>
                    </Field>
                </template>

                <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
                <p v-if="savedAt" class="text-sm text-emerald-600">✓ Enregistré</p>

                <div class="flex gap-3">
                    <button :disabled="saving" class="px-5 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                        {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <router-link to="/profil" class="px-5 py-2.5 rounded-md border border-slate-200 hover:bg-slate-50 text-slate-800 font-semibold">
                        Retour
                    </router-link>
                </div>
            </form>
        </template>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import Field from '../../components/Field.vue';
import TagInput from '../../components/TagInput.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const slug = ref(route.params.slug);
const role = ref(null);
const form = reactive({});
const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const savedAt = ref(null);
const completion = ref(0);

async function load() {
    loading.value = true;
    if (!auth.hasRole(slug.value)) {
        router.push('/profil');
        return;
    }
    try {
        const data = await auth.loadRoleProfile(slug.value);
        role.value = data.role;
        completion.value = data.profile?.completion || 0;
        Object.keys(form).forEach((k) => delete form[k]);
        Object.assign(form, data.profile?.data || {});
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;
    savedAt.value = null;
    try {
        const profile = await auth.updateRoleProfile(slug.value, form);
        completion.value = profile.completion || 0;
        savedAt.value = Date.now();
    } catch (e) {
        error.value = e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})[0]?.[0] ||
            "Impossible d'enregistrer.";
    } finally {
        saving.value = false;
    }
}

onMounted(load);
watch(() => route.params.slug, (v) => { slug.value = v; load(); });
</script>

<style scoped>
.input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    outline: none;
    background: white;
}
.input:focus { border-color: rgb(52 211 153); }
</style>
