<template>
    <div class="relative" v-click-outside="close">
        <button @click="toggle" :aria-expanded="open" aria-label="Notifications"
            class="relative w-10 h-10 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .53-.21 1.04-.59 1.41L4 17h5m6 0a3 3 0 11-6 0" />
            </svg>
            <span v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95">
            <div v-if="open"
                class="absolute right-0 mt-2 w-80 sm:w-96 max-h-[70vh] overflow-hidden rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl z-50 flex flex-col">
                <header class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-slate-100">Notifications</h3>
                    <button v-if="unreadCount > 0" @click="markAllRead"
                        class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                        Tout marquer comme lu
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto">
                    <div v-if="loading" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        Chargement…
                    </div>
                    <div v-else-if="!items.length" class="px-4 py-12 text-center">
                        <div class="text-4xl mb-2">🔔</div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Aucune notification.</p>
                    </div>
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                        <li v-for="n in items" :key="n.id"
                            class="group px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-900/50 cursor-pointer relative"
                            :class="{ 'bg-emerald-50/40 dark:bg-emerald-900/10': !n.read_at }"
                            @click="onClick(n)">
                            <div class="flex items-start gap-3">
                                <div class="text-xl shrink-0">{{ iconFor(n.type) }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 line-clamp-1">
                                        {{ titleFor(n) }}
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 line-clamp-2">
                                        {{ messageFor(n) }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                                        {{ timeAgo(n.created_at) }}
                                    </div>
                                </div>
                                <span v-if="!n.read_at"
                                    class="w-2 h-2 rounded-full bg-emerald-500 mt-2 shrink-0"
                                    aria-label="Non lu"></span>
                            </div>
                        </li>
                    </ul>
                </div>

                <footer v-if="items.length" class="px-4 py-2 border-t border-slate-200 dark:border-slate-700 text-center">
                    <router-link to="/notifications" @click="close"
                        class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400">
                        Voir tout l'historique →
                    </router-link>
                </footer>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const open = ref(false);
const loading = ref(false);
const items = ref([]);
const unreadCount = ref(0);
let pollHandle = null;

// ─── click-outside directive (locally registered) ──────────────
const vClickOutside = {
    mounted(el, binding) {
        el.__clickOutside__ = (e) => {
            if (!el.contains(e.target)) binding.value();
        };
        document.addEventListener('mousedown', el.__clickOutside__);
    },
    unmounted(el) {
        document.removeEventListener('mousedown', el.__clickOutside__);
    },
};

async function fetchUnreadCount() {
    try {
        const { data } = await window.axios.get('/api/notifications/unread-count');
        unreadCount.value = data.count ?? 0;
    } catch { /* silently ignore — bell stays at last known count */ }
}

async function fetchList() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/notifications');
        items.value = data.data ?? [];
        unreadCount.value = items.value.filter(n => !n.read_at).length
            // fall back to API count when there are more pages of unread
            || unreadCount.value;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value) fetchList();
}
function close() { open.value = false; }

async function onClick(n) {
    if (!n.read_at) {
        try {
            await window.axios.post(`/api/notifications/${n.id}/read`);
            n.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch { /* swallow */ }
    }

    const url = urlFor(n);
    if (url) {
        close();
        router.push(url);
    }
}

async function markAllRead() {
    try {
        await window.axios.post('/api/notifications/read-all');
        items.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
        unreadCount.value = 0;
    } catch { /* swallow */ }
}

// ─── presenters ───────────────────────────────────────────────
function iconFor(type) {
    return ({
        kyc_verified: '✅',
        kyc_rejected: '⛔',
        aml_flagged:  '🚨',
    })[type] || '🔔';
}

function titleFor(n) {
    const map = {
        kyc_verified: `KYC ${n.data?.tier === 'certified' ? 'Certifié' : 'Vérifié'}`,
        kyc_rejected: 'Vérification KYC rejetée',
        aml_flagged:  `Alerte AML — ${(n.data?.risk_level || '').toUpperCase()}`,
    };
    return map[n.type] || 'Notification';
}

function messageFor(n) {
    if (n.type === 'kyc_verified') {
        return `Vous avez désormais accès aux fonctionnalités niveau ${n.data?.tier || 'verified'}.`;
    }
    if (n.type === 'kyc_rejected') {
        return n.data?.reason || 'Votre vérification a été rejetée. Réessayez ou contactez le support.';
    }
    if (n.type === 'aml_flagged') {
        const flags = [];
        if (n.data?.sanctions_match)     flags.push('sanctions');
        if (n.data?.pep_match)           flags.push('PEP');
        if (n.data?.adverse_media_match) flags.push('médias');
        return `Match: ${flags.join(', ')} pour ${n.data?.screened_name || 'un utilisateur'}.`;
    }
    return JSON.stringify(n.data ?? {}).slice(0, 80);
}

function urlFor(n) {
    return ({
        kyc_verified: '/dashboard',
        kyc_rejected: '/kyc',
        aml_flagged:  n.data?.user_id ? `/admin/users/${n.data.user_id}` : '/dashboard',
    })[n.type];
}

function timeAgo(iso) {
    if (!iso) return '';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60)       return 'à l\'instant';
    if (diff < 3600)     return `il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86_400)   return `il y a ${Math.floor(diff / 3600)} h`;
    if (diff < 604_800)  return `il y a ${Math.floor(diff / 86_400)} j`;
    return new Date(iso).toLocaleDateString('fr-FR');
}

// ─── lifecycle ────────────────────────────────────────────────
onMounted(() => {
    fetchUnreadCount();
    // Poll the count every 60 s — light enough to keep the badge fresh.
    pollHandle = setInterval(fetchUnreadCount, 60_000);
});
onUnmounted(() => {
    if (pollHandle) clearInterval(pollHandle);
});
</script>
