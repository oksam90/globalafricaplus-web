<template>
    <header class="sticky top-0 z-40 backdrop-blur bg-white/90 dark:bg-brand-black/90 border-b border-brand-gold-200/60 dark:border-brand-gold-700/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <router-link to="/" @click="mobileOpen = false"
                class="flex items-center group shrink-0" aria-label="GlobalAfrica+ — Accueil">
                <img :src="logoUrl" alt="GlobalAfrica+"
                    class="h-10 md:h-12 w-auto rounded-md" />
            </router-link>

            <!-- Desktop nav -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-700 dark:text-slate-300">
                <router-link v-for="link in navLinks" :key="link.to" :to="link.to"
                    class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">
                    {{ link.label }}
                </router-link>
            </nav>

            <!-- Right cluster -->
            <div class="flex items-center gap-2">
                <DarkModeToggle />

                <!-- Desktop auth actions -->
                <template v-if="auth.isAuthenticated">
                    <router-link to="/dashboard"
                        class="hidden md:inline text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-brand-gold-600 dark:hover:text-brand-gold-400">
                        Dashboard
                    </router-link>
                    <div class="hidden md:flex items-center gap-2">
                        <RoleSwitcher />
                        <button @click="logout" :disabled="loggingOut"
                            class="text-sm font-medium px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-50">
                            {{ loggingOut ? '…' : 'Déconnexion' }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <router-link to="/connexion"
                        class="hidden md:inline text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-brand-gold-600 dark:hover:text-brand-gold-400">
                        Connexion
                    </router-link>
                    <router-link to="/inscription"
                        class="hidden sm:inline text-sm font-semibold text-white bg-brand-red-500 hover:bg-brand-red-600 px-3 py-1.5 rounded-md shadow-sm">
                        Rejoindre
                    </router-link>
                </template>

                <!-- Hamburger (mobile only) -->
                <button type="button" @click="mobileOpen = !mobileOpen"
                    class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-md text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-gold-400"
                    :aria-expanded="mobileOpen" aria-controls="mobile-menu" aria-label="Menu principal">
                    <svg v-if="!mobileOpen" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile drawer -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2">
            <div v-if="mobileOpen" id="mobile-menu"
                class="md:hidden border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-brand-black shadow-lg">
                <nav class="px-4 py-3 space-y-1 text-sm font-medium">
                    <router-link v-for="link in navLinks" :key="link.to" :to="link.to"
                        @click="mobileOpen = false"
                        class="block px-3 py-2.5 rounded-md text-slate-700 dark:text-slate-200 hover:bg-brand-gold-50 dark:hover:bg-slate-800 hover:text-brand-gold-700 dark:hover:text-brand-gold-400"
                        active-class="bg-brand-gold-50 dark:bg-slate-800 text-brand-gold-700 dark:text-brand-gold-400">
                        {{ link.label }}
                    </router-link>
                </nav>

                <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-3 space-y-2">
                    <template v-if="auth.isAuthenticated">
                        <router-link to="/dashboard" @click="mobileOpen = false"
                            class="block px-3 py-2.5 rounded-md text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                            📊 Dashboard
                        </router-link>
                        <router-link to="/profil" @click="mobileOpen = false"
                            class="block px-3 py-2.5 rounded-md text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                            👤 Mon profil
                        </router-link>
                        <div class="pt-1">
                            <RoleSwitcher />
                        </div>
                        <button @click="handleLogout" :disabled="loggingOut"
                            class="w-full text-left px-3 py-2.5 rounded-md text-sm font-medium border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50">
                            {{ loggingOut ? 'Déconnexion…' : 'Déconnexion' }}
                        </button>
                    </template>
                    <template v-else>
                        <router-link to="/connexion" @click="mobileOpen = false"
                            class="block px-3 py-2.5 rounded-md text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                            Connexion
                        </router-link>
                        <router-link to="/inscription" @click="mobileOpen = false"
                            class="block px-3 py-2.5 rounded-md text-sm font-semibold text-center text-white bg-brand-red-500 hover:bg-brand-red-600">
                            Rejoindre GlobalAfrica+
                        </router-link>
                    </template>
                </div>
            </div>
        </transition>
    </header>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import RoleSwitcher from './RoleSwitcher.vue';
import DarkModeToggle from './DarkModeToggle.vue';

const logoUrl = '/brand/logo-horizontal-dark.svg';
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const loggingOut = ref(false);
const mobileOpen = ref(false);

const navLinks = [
    { to: '/projets',      label: 'Projets' },
    { to: '/secteurs',     label: 'Secteurs' },
    { to: '/diaspora',     label: 'Diaspora' },
    { to: '/mentorat',     label: 'Mentorat' },
    { to: '/formations',   label: 'Formations' },
    { to: '/gouvernement', label: 'Gouvernement' },
    { to: '/tarifs',       label: 'Tarifs' },
];

// Close drawer on route change (covers programmatic navigation too)
watch(() => route.fullPath, () => { mobileOpen.value = false; });

// Close on Escape
function onKey(e) {
    if (e.key === 'Escape') mobileOpen.value = false;
}
// Lock body scroll while drawer is open
watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

async function logout() {
    if (loggingOut.value) return;
    loggingOut.value = true;
    try {
        await auth.logout();
        router.push('/');
    } finally {
        loggingOut.value = false;
    }
}

async function handleLogout() {
    mobileOpen.value = false;
    await logout();
}
</script>
