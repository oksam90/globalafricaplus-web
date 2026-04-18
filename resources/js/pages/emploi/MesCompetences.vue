<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes compétences</h1>
                <p class="text-slate-600 mt-1">Gérez vos compétences pour améliorer votre matching avec les projets.</p>
            </div>
            <div class="flex gap-2">
                <router-link to="/emploi" class="text-sm font-semibold text-amber-600 hover:underline">Offres d'emploi →</router-link>
                <router-link to="/emploi/mes-candidatures" class="text-sm font-semibold text-sky-600 hover:underline">Mes candidatures →</router-link>
            </div>
        </div>

        <div v-if="loading" class="text-slate-500 py-8">Chargement…</div>
        <template v-else>
            <!-- Current skills -->
            <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Mes compétences ({{ mySkills.length }}/20)</h2>
                    <span v-if="dirty" class="text-xs text-amber-600 font-semibold">Modifications non enregistrées</span>
                </div>

                <div v-if="mySkills.length === 0" class="text-center py-8 text-slate-500 bg-slate-50 rounded-xl">
                    Aucune compétence ajoutée. Sélectionnez des compétences ci-dessous.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="(s, i) in mySkills" :key="s.id"
                        class="flex items-center gap-4 bg-slate-50 rounded-xl p-3">
                        <div class="flex-1">
                            <div class="font-semibold text-sm">{{ s.name }}</div>
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider">{{ s.category }}</div>
                        </div>
                        <select v-model="s.level" @change="dirty = true"
                            class="px-2 py-1 rounded-md border border-slate-200 text-xs font-medium">
                            <option value="beginner">Débutant</option>
                            <option value="intermediate">Intermédiaire</option>
                            <option value="advanced">Avancé</option>
                            <option value="expert">Expert</option>
                        </select>
                        <div class="flex items-center gap-1">
                            <input v-model.number="s.years_experience" @input="dirty = true" type="number" min="0" max="50"
                                class="w-14 px-2 py-1 rounded-md border border-slate-200 text-xs text-center" />
                            <span class="text-xs text-slate-500">ans</span>
                        </div>
                        <button @click="removeSkill(i)" class="text-red-500 hover:text-red-700 text-sm font-bold">&times;</button>
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button @click="save" :disabled="saving || !dirty"
                        class="px-5 py-2.5 rounded-md bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm disabled:opacity-50">
                        {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                </div>
                <p v-if="saveSuccess" class="text-sm text-emerald-600 mt-2">Compétences enregistrées.</p>
                <p v-if="saveError" class="text-sm text-rose-600 mt-2">{{ saveError }}</p>
            </div>

            <!-- Add skills -->
            <div class="bg-white border border-slate-100 rounded-2xl p-6">
                <h2 class="text-lg font-bold mb-4">Ajouter des compétences</h2>
                <input v-model="searchSkill" type="text" placeholder="Rechercher une compétence…"
                    class="w-full px-3 py-2 rounded-md border border-slate-200 text-sm mb-4" />

                <div v-for="cat in filteredCategories" :key="cat" class="mb-4">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">{{ cat }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="sk in skillsByCategory(cat)" :key="sk.id"
                            @click="addSkill(sk)"
                            :disabled="isAdded(sk.id) || mySkills.length >= 20"
                            class="text-xs px-3 py-1.5 rounded-full border font-medium transition"
                            :class="isAdded(sk.id) ? 'bg-amber-100 text-amber-700 border-amber-200 cursor-default' : 'border-slate-200 hover:border-amber-300 hover:bg-amber-50'">
                            {{ isAdded(sk.id) ? '✓ ' : '+ ' }}{{ sk.name }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const allSkills = ref([]);
const mySkills = ref([]);
const loading = ref(true);
const saving = ref(false);
const dirty = ref(false);
const saveSuccess = ref(false);
const saveError = ref('');
const searchSkill = ref('');

const categories = computed(() => [...new Set(allSkills.value.map(s => s.category))].sort());

const filteredCategories = computed(() => {
    if (!searchSkill.value) return categories.value;
    const term = searchSkill.value.toLowerCase();
    return categories.value.filter(cat =>
        allSkills.value.some(s => s.category === cat && s.name.toLowerCase().includes(term))
    );
});

function skillsByCategory(cat) {
    const term = searchSkill.value.toLowerCase();
    return allSkills.value.filter(s =>
        s.category === cat && (!term || s.name.toLowerCase().includes(term))
    );
}

function isAdded(id) {
    return mySkills.value.some(s => s.id === id);
}

function addSkill(sk) {
    if (isAdded(sk.id) || mySkills.value.length >= 20) return;
    mySkills.value.push({ id: sk.id, name: sk.name, category: sk.category, level: 'intermediate', years_experience: 1 });
    dirty.value = true;
}

function removeSkill(i) {
    mySkills.value.splice(i, 1);
    dirty.value = true;
}

async function save() {
    saving.value = true;
    saveError.value = '';
    saveSuccess.value = false;
    try {
        const payload = {
            skills: mySkills.value.map(s => ({
                id: s.id,
                level: s.level,
                years_experience: s.years_experience,
            })),
        };
        const { data } = await window.axios.put('/api/emploi/mes-competences', payload);
        mySkills.value = (data.data || []).map(s => ({
            id: s.id, name: s.name, category: s.category,
            level: s.pivot.level, years_experience: s.pivot.years_experience,
        }));
        dirty.value = false;
        saveSuccess.value = true;
    } catch (e) {
        saveError.value = e?.response?.data?.message || 'Erreur lors de l\'enregistrement.';
    } finally {
        saving.value = false;
    }
}

async function loadData() {
    loading.value = true;
    try {
        const [skillsRes, myRes] = await Promise.all([
            window.axios.get('/api/emploi/skills'),
            window.axios.get('/api/emploi/mes-competences'),
        ]);
        allSkills.value = skillsRes.data.data || [];
        mySkills.value = (myRes.data.data || []).map(s => ({
            id: s.id, name: s.name, category: s.category,
            level: s.pivot.level, years_experience: s.pivot.years_experience,
        }));
    } finally {
        loading.value = false;
    }
}

onMounted(loadData);
</script>
