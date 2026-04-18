<template>
    <section class="max-w-2xl mx-auto px-4 py-16">
        <h1 class="text-3xl font-black tracking-tight text-center">Rejoindre Africa+</h1>
        <p class="text-center text-slate-600 mt-2 text-sm">Créez votre compte en moins d'une minute.</p>

        <form @submit.prevent="submit" class="mt-8 bg-white border border-slate-100 rounded-2xl p-6 space-y-5 shadow-sm">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nom complet</label>
                    <input v-model="form.name" type="text" required class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input v-model="form.email" type="email" required class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Téléphone</label>
                    <input v-model="form.phone" type="tel" class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Pays</label>
                    <input v-model="form.country" type="text" class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Mot de passe</label>
                    <input v-model="form.password" type="password" required minlength="8" class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
                    <p class="text-xs text-slate-500 mt-1">8 caractères minimum.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Je suis…</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <label v-for="r in availableRoles" :key="r.slug"
                        class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer text-sm"
                        :class="form.roles.includes(r.slug)
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                            : 'border-slate-200 hover:border-emerald-300'">
                        <input type="checkbox" :value="r.slug" v-model="form.roles" class="accent-emerald-600" />
                        {{ r.label }}
                    </label>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_diaspora" type="checkbox" class="accent-emerald-600" />
                Je vis hors d'Afrique (membre de la diaspora)
            </label>

            <p v-if="auth.error" class="text-sm text-rose-600">{{ auth.error }}</p>

            <button :disabled="auth.loading || form.roles.length === 0"
                class="w-full py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                {{ auth.loading ? 'Création…' : 'Créer mon compte' }}
            </button>

            <p class="text-sm text-center text-slate-600">
                Déjà inscrit ?
                <router-link to="/connexion" class="text-emerald-700 font-semibold hover:underline">Se connecter</router-link>
            </p>
        </form>
    </section>
</template>

<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const availableRoles = [
    { slug: 'entrepreneur', label: 'Entrepreneur' },
    { slug: 'investor', label: 'Investisseur' },
    { slug: 'mentor', label: 'Mentor' },
    { slug: 'jobseeker', label: "Chercheur d'emploi" },
    { slug: 'government', label: 'Gouvernement' },
];

const form = reactive({
    name: '',
    email: '',
    phone: '',
    country: '',
    password: '',
    is_diaspora: false,
    roles: ['entrepreneur'],
});

async function submit() {
    if (await auth.register(form)) {
        router.push('/dashboard');
    }
}
</script>
