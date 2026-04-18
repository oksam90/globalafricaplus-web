<template>
    <div v-if="auth.roleObjects.length > 0" class="relative">
        <button @click="open = !open" @blur="close"
            class="flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300">
            <span class="inline-block w-2 h-2 rounded-full" :style="{ backgroundColor: roleColor(auth.activeRole) }"></span>
            <span class="hidden sm:inline">{{ roleLabel(auth.activeRole) || 'Choisir un rôle' }}</span>
            <svg class="w-3 h-3 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.4a.75.75 0 0 1-1.08 0l-4.25-4.4a.75.75 0 0 1 .02-1.06z"/></svg>
        </button>

        <div v-if="open" class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-lg py-1 z-50"
            @mousedown.prevent>
            <div class="px-3 py-2 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">Mes rôles actifs</div>
            <button v-for="role in auth.roleObjects" :key="role.id"
                @click="pick(role.slug)"
                class="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-3"
                :class="{ 'bg-emerald-50 dark:bg-emerald-900/30': role.slug === auth.activeRole }">
                <span class="inline-block w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: roleColor(role.slug) }"></span>
                <span class="flex-1">
                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ role.name }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Profil à {{ completionPct(role.slug) }}%</div>
                </span>
                <svg v-if="role.slug === auth.activeRole" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z"/>
                </svg>
            </button>
            <div class="border-t border-slate-100 dark:border-slate-700 mt-1 pt-1">
                <router-link to="/profil/ajouter-role" @click="open = false"
                    class="block px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-700 font-medium">
                    + Ajouter un rôle
                </router-link>
                <router-link to="/profil" @click="open = false"
                    class="block px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                    Gérer mes profils
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const open = ref(false);

const COLORS = {
    entrepreneur: '#16a34a',
    investor: '#2563eb',
    mentor: '#7c3aed',
    jobseeker: '#f59e0b',
    government: '#0ea5e9',
    admin: '#ef4444',
};

const LABELS = {
    entrepreneur: 'Entrepreneur',
    investor: 'Investisseur',
    mentor: 'Mentor',
    jobseeker: "Chercheur d'emploi",
    government: 'Gouvernement',
    admin: 'Administrateur',
};

function roleColor(slug) { return COLORS[slug] || '#64748b'; }
function roleLabel(slug) { return LABELS[slug] || slug; }
function completionPct(slug) { return auth.completionFor(slug); }

async function pick(slug) {
    await auth.switchRole(slug);
    open.value = false;
}
function close() {
    // delay so click on menu item still triggers
    setTimeout(() => (open.value = false), 150);
}
</script>
