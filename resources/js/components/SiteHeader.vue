<template>
    <header class="sticky top-0 z-40 backdrop-blur bg-white/90 dark:bg-brand-black/90 border-b border-brand-gold-200/60 dark:border-brand-gold-700/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <router-link to="/" class="flex items-center group" aria-label="GlobalAfrica+ — Accueil">
                <!-- Logo horizontal (dark version = noir avec or + cerise) -->
                <img :src="logoUrl" alt="GlobalAfrica+"
                    class="h-11 md:h-12 w-auto rounded-md" />
            </router-link>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-700 dark:text-slate-300">
                <router-link to="/projets" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Projets</router-link>
                <router-link to="/secteurs" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Secteurs</router-link>
                <router-link to="/diaspora" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Diaspora</router-link>
                <router-link to="/mentorat" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Mentorat</router-link>
                <router-link to="/gouvernement" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Gouvernement</router-link>
                <router-link to="/tarifs" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Tarifs</router-link>
            </nav>

            <div class="flex items-center gap-2">
                <DarkModeToggle />
                <template v-if="auth.isAuthenticated">
                    <router-link to="/dashboard" class="text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-brand-gold-600 dark:hover:text-brand-gold-400 hidden sm:inline">
                        Dashboard
                    </router-link>
                    <RoleSwitcher />
                    <button @click="logout" :disabled="loggingOut"
                        class="text-sm font-medium px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-50">
                        {{ loggingOut ? 'Déconnexion…' : 'Déconnexion' }}
                    </button>
                </template>
                <template v-else>
                    <router-link to="/connexion" class="text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Connexion</router-link>
                    <router-link to="/inscription" class="text-sm font-semibold text-white bg-brand-red-500 hover:bg-brand-red-600 px-3 py-1.5 rounded-md shadow-sm">
                        Rejoindre
                    </router-link>
                </template>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import RoleSwitcher from './RoleSwitcher.vue';
import DarkModeToggle from './DarkModeToggle.vue';

const logoUrl = '/brand/logo-horizontal-dark.svg';
const auth = useAuthStore();
const router = useRouter();

const loggingOut = ref(false);

async function logout() {
    if (loggingOut.value) return; // Prevent double-click
    loggingOut.value = true;
    try {
        await auth.logout();
        router.push('/');
    } finally {
        loggingOut.value = false;
    }
}
</script>
