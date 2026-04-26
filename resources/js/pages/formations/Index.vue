<template>
    <div>
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-50 via-emerald-50 to-amber-50 dark:from-violet-950/40 dark:via-emerald-950/30 dark:to-amber-950/40"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                <span class="inline-block px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold mb-4">
                    Formations
                </span>
                <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                    Montez en compétences
                    <span class="bg-gradient-to-r from-emerald-500 via-violet-500 to-amber-500 bg-clip-text text-transparent">
                        avec nos experts africains
                    </span>
                </h1>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                    Entrepreneuriat, finance, tech, gouvernance — accédez à des formations conçues pour le marché africain.
                </p>
            </div>
        </section>

        <!-- Filters -->
        <section class="bg-white dark:bg-slate-800 border-y border-slate-100 dark:border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap gap-3 items-center">
                <input v-model="filters.q" @input="debouncedLoad" type="search" placeholder="Rechercher une formation…"
                    class="flex-1 min-w-[200px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                <select v-model="filters.category" @change="load"
                    class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="">Toutes catégories</option>
                    <option value="entrepreneurship">Entrepreneuriat</option>
                    <option value="finance">Finance</option>
                    <option value="tech">Tech</option>
                    <option value="leadership">Leadership</option>
                    <option value="marketing">Marketing</option>
                </select>
                <select v-model="filters.level" @change="load"
                    class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="">Tous niveaux</option>
                    <option value="beginner">Débutant</option>
                    <option value="intermediate">Intermédiaire</option>
                    <option value="advanced">Avancé</option>
                </select>
            </div>
        </section>

        <!-- Grid -->
        <section class="py-12 bg-slate-50 dark:bg-slate-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="text-center py-16 text-slate-500 dark:text-slate-400">Chargement…</div>
                <div v-else-if="!trainings.length" class="text-center py-16 text-slate-500 dark:text-slate-400">
                    Aucune formation trouvée.
                </div>
                <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <router-link v-for="t in trainings" :key="t.id"
                        :to="`/formations/${t.slug}`"
                        class="group bg-white dark:bg-slate-800 rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-700 hover:shadow-lg transition-shadow">
                        <div class="aspect-video bg-gradient-to-br from-violet-100 to-emerald-100 dark:from-violet-900/40 dark:to-emerald-900/40 overflow-hidden">
                            <img v-if="t.cover_image" :src="t.cover_image" :alt="t.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            <div v-else class="w-full h-full flex items-center justify-center text-5xl">
                                {{ categoryIcon(t.category) }}
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                    :class="levelClass(t.level)">{{ levelLabel(t.level) }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ t.duration_minutes }} min</span>
                            </div>
                            <h3 class="font-bold text-slate-900 dark:text-slate-100 line-clamp-2 group-hover:text-emerald-600">
                                {{ t.title }}
                            </h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 line-clamp-2">{{ t.summary }}</p>
                            <div class="mt-4 flex items-end justify-between">
                                <div>
                                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100">
                                        {{ formatPrice(t.price, t.currency) }}
                                    </div>
                                    <div v-if="t.currency === 'EUR'" class="text-xs text-amber-600 dark:text-amber-400 font-semibold">
                                        &asymp; {{ formatXof(t.price) }}
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 text-right">
                                    <div v-if="t.rating_avg">⭐ {{ Number(t.rating_avg).toFixed(1) }}</div>
                                    <div>{{ t.purchases_count }} apprenants</div>
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>

                <!-- Pagination -->
                <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-10">
                    <button @click="goPage(meta.current_page - 1)" :disabled="meta.current_page <= 1"
                        class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm disabled:opacity-50">
                        ← Précédent
                    </button>
                    <span class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300">
                        Page {{ meta.current_page }} / {{ meta.last_page }}
                    </span>
                    <button @click="goPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page"
                        class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm disabled:opacity-50">
                        Suivant →
                    </button>
                </div>
            </div>
        </section>

        <!-- My trainings link -->
        <section v-if="auth.isAuthenticated" class="py-8 bg-white dark:bg-slate-800 border-t border-slate-100 dark:border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <router-link to="/formations/mes-formations"
                    class="inline-block px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                    🎓 Mes formations
                </router-link>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();

const trainings = ref([]);
const loading = ref(true);
const meta = reactive({ current_page: 1, last_page: 1 });
const filters = reactive({ q: '', category: '', level: '' });

let debounceTimer = null;
function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(load, 350);
}

const EUR_TO_XOF = 655.957;
const xofFmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 });
function formatXof(eur) { return xofFmt.format(Math.round((parseFloat(eur) || 0) * EUR_TO_XOF)); }
function formatPrice(amount, currency) {
    const cur = (currency || 'EUR').toUpperCase();
    try { return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur, maximumFractionDigits: cur === 'XOF' ? 0 : 2 }).format(parseFloat(amount) || 0); }
    catch { return `${amount} ${cur}`; }
}

function levelLabel(l) { return ({ beginner: 'Débutant', intermediate: 'Intermédiaire', advanced: 'Avancé' })[l] || l; }
function levelClass(l) {
    return ({
        beginner: 'bg-emerald-100 text-emerald-700',
        intermediate: 'bg-amber-100 text-amber-700',
        advanced: 'bg-rose-100 text-rose-700',
    })[l] || 'bg-slate-100 text-slate-700';
}
function categoryIcon(c) {
    return ({ entrepreneurship: '🚀', finance: '💰', tech: '💻', leadership: '👑', marketing: '📈' })[c] || '🎓';
}

async function load(page = 1) {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/trainings', {
            params: { page, q: filters.q || undefined, category: filters.category || undefined, level: filters.level || undefined },
        });
        trainings.value = data.data || [];
        meta.current_page = data.current_page || 1;
        meta.last_page = data.last_page || 1;
    } catch {
        trainings.value = [];
    } finally {
        loading.value = false;
    }
}

function goPage(p) {
    if (p < 1 || p > meta.last_page) return;
    load(p);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => load());
</script>
