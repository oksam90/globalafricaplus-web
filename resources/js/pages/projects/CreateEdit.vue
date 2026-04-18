<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/projets/mes-projets" class="text-sm text-emerald-700 hover:underline">← Mes projets</router-link>

        <h1 class="mt-4 text-3xl font-black tracking-tight">
            {{ isEdit ? 'Modifier le projet' : 'Nouveau projet' }}
        </h1>
        <p class="text-slate-600 mt-1">Présentez votre initiative pour trouver du financement, des talents et des partenaires.</p>

        <form @submit.prevent="submit" class="mt-8 bg-white border border-slate-100 rounded-2xl p-6 space-y-5">
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

            <div class="grid sm:grid-cols-2 gap-4">
                <Field label="Stade *">
                    <select v-model="form.stage" required class="input">
                        <option value="idea">Idée</option>
                        <option value="mvp">MVP</option>
                        <option value="launch">Lancement</option>
                        <option value="scaling">Croissance</option>
                    </select>
                </Field>
                <Field label="Date limite">
                    <input v-model="form.deadline" type="date" class="input" />
                </Field>
            </div>

            <Field label="Tags" hint="Ajoutez avec Entrée ou virgule">
                <TagInput v-model="form.tags" placeholder="agritech, solaire…" />
            </Field>

            <Field label="Objectifs de Développement Durable (ODD)">
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                    <label v-for="s in sdgs" :key="s.id"
                        class="flex items-center gap-1 cursor-pointer text-xs p-2 rounded border"
                        :class="form.sdg_ids.includes(s.id) ? 'ring-2 ring-offset-1' : 'border-slate-200 hover:border-emerald-300'"
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
            <Field label="Pitch deck (lien)">
                <input v-model="form.pitch_deck_url" type="url" placeholder="https://" class="input" />
            </Field>

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
                <router-link to="/projets/mes-projets" class="px-5 py-2.5 rounded-md border border-slate-200 hover:bg-slate-50 text-slate-800 font-semibold">
                    Annuler
                </router-link>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '../../components/Field.vue';
import TagInput from '../../components/TagInput.vue';

const route = useRoute();
const router = useRouter();
const isEdit = computed(() => !!route.params.slug);

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
    tags: [],
    sdg_ids: [],
    website: '', video_url: '', pitch_deck_url: '',
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
        tags: p.tags || [],
        sdg_ids: (p.sdgs || []).map((s) => s.id),
        website: p.website || '',
        video_url: p.video_url || '',
        pitch_deck_url: p.pitch_deck_url || '',
        status: p.status,
    });
    onCategoryChange();
}

function cleanPayload() {
    const payload = { ...form };
    if (!payload.deadline) delete payload.deadline;
    if (!payload.sub_category_id) payload.sub_category_id = null;
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
}
.input:focus { border-color: rgb(52 211 153); }
</style>
