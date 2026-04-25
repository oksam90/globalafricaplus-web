<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Analytics plateforme</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">Vue d'ensemble de l'activité Africa+.</p>
            </div>
            <router-link to="/dashboard" class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                ← Dashboard admin
            </router-link>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-12 text-center">Chargement des analytics…</div>
        <template v-else>
            <!-- Main KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-10">
                <div v-for="kpi in mainKpis" :key="kpi.label"
                    class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                    <div class="text-xl mb-1">{{ kpi.icon }}</div>
                    <div class="text-3xl font-black tracking-tight" :class="kpi.color || 'text-slate-900 dark:text-slate-100'">{{ kpi.value }}</div>
                    <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">{{ kpi.label }}</div>
                </div>
            </div>

            <!-- Funding progress global -->
            <div v-if="data.kpis.total_needed > 0" class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-950/40 dark:to-orange-950/40 border border-red-100 dark:border-red-900/40 rounded-2xl p-6 mb-10">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-red-900 dark:text-red-200">Progression globale du financement</h3>
                    <span class="text-sm font-semibold text-red-700 dark:text-red-300">{{ data.kpis.funding_rate }}%</span>
                </div>
                <div class="h-3 bg-white dark:bg-slate-900 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-red-400 to-orange-500 rounded-full transition-all"
                        :style="{ width: Math.min(data.kpis.funding_rate, 100) + '%' }"></div>
                </div>
                <div class="flex justify-between text-xs text-red-700 dark:text-red-300 mt-2">
                    <span>Total levé : {{ fmtMoney(data.kpis.total_raised) }}</span>
                    <span>Total demandé : {{ fmtMoney(data.kpis.total_needed) }}</span>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 mb-10">
                <!-- Users by role -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Utilisateurs par rôle</h3>
                    <div class="space-y-3">
                        <div v-for="r in data.users_by_role" :key="r.slug" class="flex items-center gap-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full w-28 text-center"
                                :class="roleColorClass(r.slug)">{{ r.name }}</span>
                            <div class="flex-1 h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                    :class="roleBarColor(r.slug)"
                                    :style="{ width: maxRoleCount > 0 ? (r.count / maxRoleCount * 100) + '%' : '0%' }"></div>
                            </div>
                            <span class="text-sm font-bold w-10 text-right">{{ r.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Projects by status -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Projets par statut</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="(count, status) in data.projects_by_status" :key="status"
                            class="rounded-xl p-4 text-center" :class="statusBgClass(status)">
                            <div class="text-2xl font-black">{{ count }}</div>
                            <div class="text-xs font-semibold mt-1">{{ statusLabel(status) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 mb-10">
                <!-- Projects by country -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Top 10 pays (projets publiés)</h3>
                    <div class="space-y-2">
                        <div v-for="c in data.projects_by_country" :key="c.country"
                            class="flex items-center justify-between text-sm py-2 border-b border-slate-50 dark:border-slate-700/60 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">{{ c.country }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-slate-500 dark:text-slate-400">{{ c.count }} projets</span>
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ fmtMoney(parseFloat(c.raised) || 0) }}</span>
                            </div>
                        </div>
                        <div v-if="!data.projects_by_country?.length" class="text-slate-400 dark:text-slate-500 text-center py-4">Aucune donnée.</div>
                    </div>
                </div>

                <!-- Projects by category -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Projets par secteur</h3>
                    <div class="space-y-2">
                        <div v-for="c in data.projects_by_category" :key="c.name"
                            class="flex items-center justify-between text-sm py-2 border-b border-slate-50 dark:border-slate-700/60 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: c.color }"></span>
                                <span class="font-semibold">{{ c.name }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-slate-500 dark:text-slate-400">{{ c.count }} projets</span>
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ fmtMoney(parseFloat(c.raised) || 0) }}</span>
                            </div>
                        </div>
                        <div v-if="!data.projects_by_category?.length" class="text-slate-400 dark:text-slate-500 text-center py-4">Aucune donnée.</div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 mb-10">
                <!-- Registration trend -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Inscriptions (12 derniers mois)</h3>
                    <div v-if="data.registration_trend?.length" class="flex items-end gap-1 h-40">
                        <div v-for="m in data.registration_trend" :key="m.month"
                            class="flex-1 flex flex-col items-center justify-end gap-1">
                            <span class="text-[10px] font-bold text-slate-700 dark:text-slate-200">{{ m.count }}</span>
                            <div class="w-full bg-red-400 rounded-t-sm transition-all min-h-[2px]"
                                :style="{ height: maxRegistrations > 0 ? (m.count / maxRegistrations * 100) + '%' : '2px' }"></div>
                            <span class="text-[9px] text-slate-400 dark:text-slate-500 -rotate-45 origin-top-left mt-1 whitespace-nowrap">
                                {{ formatMonth(m.month) }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-slate-400 dark:text-slate-500 text-center py-8">Pas encore de données.</div>
                </div>

                <!-- KYC distribution -->
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4">Distribution KYC</h3>
                    <div class="space-y-4">
                        <div v-for="(count, level) in data.kyc_distribution" :key="level">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium capitalize">{{ KYC_LABELS[level] || level }}</span>
                                <span class="font-bold">{{ count }}</span>
                            </div>
                            <div class="h-2.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                    :class="kycBarColor(level)"
                                    :style="{ width: totalUsers > 0 ? (count / totalUsers * 100) + '%' : '0%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="bg-red-50 dark:bg-red-950/30 border border-red-100 dark:border-red-900/40 rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-4 text-red-900 dark:text-red-200">Actions rapides</h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <router-link to="/admin/users"
                        class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-red-100 dark:border-red-900/40 hover:border-red-300 dark:hover:border-red-700 transition text-center">
                        <div class="text-2xl mb-2">👥</div>
                        <div class="font-semibold text-sm">Gestion utilisateurs</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ data.kpis.total_users }} inscrits</div>
                    </router-link>
                    <router-link to="/admin/moderation"
                        class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-red-100 dark:border-red-900/40 hover:border-red-300 dark:hover:border-red-700 transition text-center relative">
                        <span v-if="data.kpis.pending_projects > 0"
                            class="absolute top-2 right-2 flex h-5 w-5 items-center justify-center text-[10px] font-bold rounded-full bg-rose-500 text-white">
                            {{ data.kpis.pending_projects }}
                        </span>
                        <div class="text-2xl mb-2">🛡️</div>
                        <div class="font-semibold text-sm">Modération</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ data.kpis.pending_projects }} en attente</div>
                    </router-link>
                    <router-link to="/profil/admin"
                        class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-red-100 dark:border-red-900/40 hover:border-red-300 dark:hover:border-red-700 transition text-center">
                        <div class="text-2xl mb-2">⚙️</div>
                        <div class="font-semibold text-sm">Mon profil admin</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configuration</div>
                    </router-link>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref({ kpis: {}, users_by_role: [], projects_by_status: {}, projects_by_country: [], projects_by_category: [], registration_trend: [], kyc_distribution: {} });
const loading = ref(true);

const KYC_LABELS = { none: 'Aucun', basic: 'Basique', verified: 'Vérifié', certified: 'Certifié' };

const mainKpis = computed(() => {
    const k = data.value.kpis;
    return [
        { label: 'Utilisateurs total', value: k.total_users || 0, icon: '👥', color: 'text-slate-900 dark:text-slate-100' },
        { label: 'Inscrits (30j)', value: k.recent_signups || 0, icon: '📈', color: 'text-blue-600 dark:text-blue-400' },
        { label: 'Projets publiés', value: k.published_projects || 0, icon: '✅', color: 'text-emerald-600 dark:text-emerald-400' },
        { label: 'En attente', value: k.pending_projects || 0, icon: '⏳', color: k.pending_projects > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' },
        { label: 'Brouillons', value: k.draft_projects || 0, icon: '📝', color: 'text-slate-500 dark:text-slate-400' },
        { label: 'Total levé', value: fmtMoney(k.total_raised || 0), icon: '💰', color: 'text-emerald-600 dark:text-emerald-400' },
        { label: 'Investissements', value: k.total_investments || 0, icon: '📊', color: 'text-blue-600 dark:text-blue-400' },
        { label: 'Mentorats actifs', value: k.active_mentorships || 0, icon: '🎓', color: 'text-violet-600 dark:text-violet-400' },
    ];
});

const maxRoleCount = computed(() => {
    return Math.max(1, ...data.value.users_by_role.map(r => r.count));
});

const maxRegistrations = computed(() => {
    return Math.max(1, ...data.value.registration_trend.map(m => m.count));
});

const totalUsers = computed(() => data.value.kpis.total_users || 1);

function roleColorClass(slug) {
    return {
        entrepreneur: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
        investor: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        mentor: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
        jobseeker: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        government: 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300',
        admin: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    }[slug] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
}

function roleBarColor(slug) {
    return {
        entrepreneur: 'bg-emerald-500',
        investor: 'bg-blue-500',
        mentor: 'bg-violet-500',
        jobseeker: 'bg-amber-500',
        government: 'bg-sky-500',
        admin: 'bg-red-500',
    }[slug] || 'bg-slate-400';
}

function statusBgClass(s) {
    return {
        published: 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
        pending: 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
        rejected: 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300',
        draft: 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300',
    }[s] || 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300';
}

function statusLabel(s) {
    return { published: 'Publiés', pending: 'En attente', rejected: 'Rejetés', draft: 'Brouillons' }[s] || s;
}

function kycBarColor(level) {
    return {
        none: 'bg-slate-300 dark:bg-slate-500',
        basic: 'bg-blue-400',
        verified: 'bg-emerald-500',
        certified: 'bg-violet-500',
    }[level] || 'bg-slate-300 dark:bg-slate-500';
}

function fmtMoney(amount) {
    const n = parseFloat(amount) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M €';
    if (n >= 1_000) return Math.round(n / 1_000) + 'k €';
    return n + ' €';
}

function formatMonth(m) {
    if (!m) return '';
    const [y, mo] = m.split('-');
    const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    return months[parseInt(mo) - 1] + ' ' + y.slice(2);
}

async function load() {
    loading.value = true;
    try {
        const { data: d } = await window.axios.get('/api/admin/analytics');
        data.value = d;
    } catch (e) {
        console.error('Analytics load error:', e);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
