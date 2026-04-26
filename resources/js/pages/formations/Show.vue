<template>
    <div>
        <div v-if="loading" class="max-w-5xl mx-auto p-12 text-center text-slate-500 dark:text-slate-400">Chargement…</div>

        <article v-else-if="training" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <router-link to="/formations" class="text-sm text-emerald-600 hover:underline">← Retour au catalogue</router-link>

            <div class="grid lg:grid-cols-3 gap-8 mt-6">
                <!-- Main -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="aspect-video rounded-2xl overflow-hidden bg-gradient-to-br from-violet-100 to-emerald-100 dark:from-violet-900/40 dark:to-emerald-900/40">
                        <video v-if="training.video_preview_url" :src="training.video_preview_url" controls class="w-full h-full object-cover"></video>
                        <img v-else-if="training.cover_image" :src="training.cover_image" :alt="training.title" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-7xl">🎓</div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="levelClass(training.level)">{{ levelLabel(training.level) }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ training.duration_minutes }} min</span>
                            <span v-if="training.rating_avg" class="text-xs text-amber-600">⭐ {{ Number(training.rating_avg).toFixed(1) }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">· {{ training.purchases_count }} apprenants</span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-slate-100">{{ training.title }}</h1>
                        <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">{{ training.summary }}</p>
                    </div>

                    <div v-if="training.description" class="prose dark:prose-invert max-w-none">
                        <h2 class="text-xl font-bold mb-2">À propos de cette formation</h2>
                        <div class="text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ training.description }}</div>
                    </div>

                    <div v-if="curriculum.length" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6">
                        <h2 class="text-xl font-bold mb-4">Programme</h2>
                        <ol class="space-y-2">
                            <li v-for="(mod, i) in curriculum" :key="i"
                                class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-200">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-xs font-bold">{{ i + 1 }}</span>
                                <span>{{ mod }}</span>
                            </li>
                        </ol>
                    </div>

                    <div v-if="training.instructor" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6 flex items-center gap-4">
                        <img v-if="training.instructor.avatar" :src="training.instructor.avatar" alt="" class="w-14 h-14 rounded-full object-cover" />
                        <div v-else class="w-14 h-14 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300">
                            {{ training.instructor.name?.[0] || '?' }}
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Formateur</div>
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ training.instructor.name }}</div>
                        </div>
                    </div>
                </div>

                <!-- Purchase aside -->
                <aside class="lg:col-span-1">
                    <div class="lg:sticky lg:top-24 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-6">
                        <div class="text-center mb-4">
                            <div class="text-4xl font-black text-slate-900 dark:text-slate-100">
                                {{ formatPrice(training.price, training.currency) }}
                            </div>
                            <div v-if="training.currency === 'EUR'" class="text-sm text-amber-600 dark:text-amber-400 font-semibold mt-1">
                                &asymp; {{ formatXof(training.price) }} via PayDunya
                            </div>
                        </div>

                        <div v-if="hasAccess" class="space-y-3">
                            <div class="text-center px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-sm font-semibold">
                                ✓ Vous avez accès à cette formation
                            </div>
                            <a v-if="contentUrl" :href="contentUrl" target="_blank" rel="noopener"
                                class="block w-full text-center px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                ▶ Accéder au contenu
                            </a>
                            <button v-if="myPurchase?.is_refundable" @click="requestRefund" :disabled="refunding"
                                class="block w-full text-center px-4 py-2 rounded-lg border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/30 text-xs font-semibold disabled:opacity-60">
                                {{ refunding ? 'Remboursement…' : '↻ Remboursement (garantie 30j)' }}
                            </button>
                        </div>
                        <div v-else class="space-y-3">
                            <button @click="purchase" :disabled="purchasing"
                                class="w-full px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold">
                                {{ purchasing ? 'Redirection…' : '🔓 Acheter cette formation' }}
                            </button>
                            <p class="text-xs text-slate-500 dark:text-slate-400 text-center">
                                Paiement sécurisé via PayDunya · Garantie 30 jours satisfait ou remboursé
                            </p>
                        </div>

                        <p v-if="error" class="mt-3 text-sm text-rose-600 text-center">{{ error }}</p>
                    </div>
                </aside>
            </div>
        </article>

        <div v-else class="max-w-5xl mx-auto p-12 text-center text-slate-500 dark:text-slate-400">Formation introuvable.</div>

        <Teleport to="body">
            <div v-if="toast" class="fixed bottom-6 right-6 z-50 max-w-sm px-5 py-3 rounded-lg shadow-lg text-sm font-medium"
                :class="toast.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
                {{ toast.message }}
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const training = ref(null);
const hasAccess = ref(false);
const contentUrl = ref(null);
const myPurchase = ref(null);
const loading = ref(true);
const purchasing = ref(false);
const refunding = ref(false);
const error = ref('');
const toast = ref(null);

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

const curriculum = computed(() => {
    const c = training.value?.curriculum;
    if (!c) return [];
    return Array.isArray(c) ? c : Object.values(c);
});

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(`/api/trainings/${route.params.slug}`);
        training.value = data.data;
        hasAccess.value = !!data.has_access;
        contentUrl.value = data.content_url || null;
        if (hasAccess.value && auth.isAuthenticated) {
            try {
                const { data: mine } = await window.axios.get('/api/trainings/mine');
                const list = mine.data || [];
                myPurchase.value = list.find(p => p.training_id === training.value.id) || null;
            } catch { /* ignore */ }
        }
    } catch {
        training.value = null;
    } finally {
        loading.value = false;
    }
}

async function purchase() {
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    purchasing.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post(`/api/trainings/${training.value.slug}/purchase`, {
            country: auth.user?.country || 'SN',
        });
        if (data.status === 'checkout_required' && data.checkout?.url) {
            window.location.href = data.checkout.url;
            return;
        }
        if (data.status === 'already_owned') {
            await load();
            showToast('Vous avez déjà cette formation.', 'success');
            return;
        }
        error.value = data.message || 'Réponse inattendue.';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erreur lors de l\'achat.';
    } finally {
        purchasing.value = false;
    }
}

async function requestRefund() {
    if (!myPurchase.value) return;
    if (!confirm('Confirmer le remboursement de cette formation ? Vous perdrez l\'accès au contenu.')) return;
    refunding.value = true;
    try {
        await window.axios.post(`/api/trainings/purchases/${myPurchase.value.id}/refund`);
        showToast('Remboursement effectué.', 'success');
        await load();
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors du remboursement.', 'error');
    } finally {
        refunding.value = false;
    }
}

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(load);
</script>
