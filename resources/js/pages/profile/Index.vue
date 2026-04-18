<template>
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Mes profils</h1>
                <p class="text-slate-600 mt-1">Gérez vos rôles et complétez vos profils métier.</p>
            </div>
            <router-link to="/profil/ajouter-role"
                class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                + Ajouter un rôle
            </router-link>
        </header>

        <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-bold">Complétion globale</h2>
                <span class="text-sm font-semibold text-emerald-700">{{ auth.globalCompletion }}%</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-amber-500 transition-all"
                    :style="{ width: auth.globalCompletion + '%' }"></div>
            </div>
            <p v-if="auth.globalCompletion < 100" class="text-xs text-slate-500 mt-2">
                Complétez votre profil pour améliorer vos chances d'être contacté.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-5">
            <article v-for="role in auth.roleObjects" :key="role.id"
                class="bg-white border border-slate-100 rounded-2xl p-6 hover:border-emerald-200 transition">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: roleColor(role.slug) }"></span>
                            Rôle
                        </div>
                        <h3 class="font-bold text-lg mt-1">{{ role.name }}</h3>
                        <p class="text-xs text-slate-500">{{ role.description }}</p>
                    </div>
                    <span v-if="role.slug === auth.activeRole"
                        class="text-[10px] font-bold uppercase px-2 py-1 rounded bg-emerald-100 text-emerald-700">
                        Actif
                    </span>
                </div>

                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 mb-1">
                    <span>Profil {{ auth.completionFor(role.slug) }}%</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" :style="{ width: auth.completionFor(role.slug) + '%' }"></div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <router-link :to="`/profil/${role.slug}`"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md border border-slate-200 hover:border-emerald-300">
                        Compléter
                    </router-link>
                    <button v-if="role.slug !== auth.activeRole" @click="pick(role.slug)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                        Activer
                    </button>
                    <button v-if="auth.roleObjects.length > 1" @click="remove(role.slug)"
                        class="text-sm font-semibold px-3 py-1.5 rounded-md text-rose-600 hover:bg-rose-50 ml-auto">
                        Retirer
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();

const COLORS = {
    entrepreneur: '#16a34a', investor: '#2563eb', mentor: '#7c3aed',
    jobseeker: '#f59e0b', government: '#0ea5e9', admin: '#ef4444',
};
function roleColor(slug) { return COLORS[slug] || '#64748b'; }

async function pick(slug) { await auth.switchRole(slug); }
async function remove(slug) {
    if (!confirm('Retirer ce rôle de votre compte ?')) return;
    await auth.removeRole(slug);
}
</script>
