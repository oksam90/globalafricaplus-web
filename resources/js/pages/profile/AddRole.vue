<template>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <router-link to="/profil" class="text-sm text-emerald-700 hover:underline">← Mes profils</router-link>

        <header class="mt-4 mb-8">
            <h1 class="text-3xl font-black tracking-tight">Ajouter un rôle</h1>
            <p class="text-slate-600 mt-1">
                Vous pouvez cumuler plusieurs rôles sur Africa+ (entrepreneur + mentor par exemple).
            </p>
        </header>

        <div class="grid sm:grid-cols-2 gap-4">
            <button v-for="role in availableRoles" :key="role.slug"
                :disabled="auth.hasRole(role.slug) || adding === role.slug"
                @click="add(role.slug)"
                class="text-left bg-white border border-slate-100 rounded-2xl p-5 hover:border-emerald-300 hover:shadow-sm transition disabled:opacity-50 disabled:hover:border-slate-100">
                <div class="flex items-start justify-between">
                    <div class="text-3xl">{{ role.icon }}</div>
                    <span v-if="auth.hasRole(role.slug)"
                        class="text-[10px] font-bold uppercase px-2 py-1 rounded bg-slate-100 text-slate-600">
                        Déjà attribué
                    </span>
                </div>
                <h3 class="font-bold text-lg mt-3">{{ role.label }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ role.description }}</p>
                <div v-if="adding === role.slug" class="mt-3 text-xs text-emerald-700 font-semibold">Ajout…</div>
            </button>
        </div>

        <p v-if="auth.error" class="text-sm text-rose-600 mt-4">{{ auth.error }}</p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const adding = ref(null);

const availableRoles = [
    { slug: 'entrepreneur', icon: '🚀', label: 'Entrepreneur', description: 'Publier et gérer vos projets.' },
    { slug: 'investor', icon: '💰', label: 'Investisseur', description: 'Investir dans des projets à impact.' },
    { slug: 'mentor', icon: '🎓', label: 'Mentor', description: 'Accompagner des entrepreneurs.' },
    { slug: 'jobseeker', icon: '🛠️', label: "Chercheur d'emploi", description: 'Trouver des opportunités.' },
    { slug: 'government', icon: '🏛️', label: 'Gouvernement', description: 'Publier des appels à projets.' },
];

async function add(slug) {
    adding.value = slug;
    const ok = await auth.addRole(slug);
    adding.value = null;
    if (ok) {
        await auth.switchRole(slug);
        router.push(`/profil/${slug}`);
    }
}
</script>
