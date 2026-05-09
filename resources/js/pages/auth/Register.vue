<template>
    <section class="max-w-2xl mx-auto px-4 py-16">
        <h1 class="text-3xl font-black tracking-tight text-center text-slate-900 dark:text-slate-100">Rejoindre GlobalAfrica+</h1>
        <p class="text-center text-slate-600 dark:text-slate-400 mt-2 text-sm">Créez votre compte en moins d'une minute.</p>

        <form @submit.prevent="submit"
            class="mt-8 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 space-y-5 shadow-sm">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Nom complet</label>
                    <input v-model="form.name" type="text" required
                        class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Email</label>
                    <input v-model="form.email" type="email" required
                        class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Téléphone</label>
                    <input v-model="form.phone" type="tel"
                        class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Pays</label>
                    <input v-model="form.country" type="text"
                        class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Mot de passe</label>
                    <input v-model="form.password" type="password" required minlength="8"
                        class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">8 caractères minimum.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Je suis…</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <label v-for="r in availableRoles" :key="r.slug"
                        class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer text-sm"
                        :class="form.roles.includes(r.slug)
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 dark:border-emerald-500'
                            : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-emerald-300 dark:hover:border-emerald-500'">
                        <input type="checkbox" :value="r.slug" v-model="form.roles" class="accent-emerald-600" />
                        {{ r.label }}
                    </label>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input v-model="form.is_diaspora" type="checkbox" class="accent-emerald-600" />
                Je vis hors d'Afrique (membre de la diaspora)
            </label>

            <!-- CGU / Politique / DPA acceptance -->
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input v-model="form.accept_terms" type="checkbox" required class="mt-0.5 accent-emerald-600 shrink-0" />
                <span>
                    J'accepte les
                    <router-link to="/cgu" target="_blank"
                        class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Conditions d'utilisation de GlobalAfrica+</router-link>,
                    la
                    <router-link to="/confidentialite" target="_blank"
                        class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Politique de confidentialité</router-link>
                    et l'
                    <router-link to="/dpa" target="_blank"
                        class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Accord de traitement des données</router-link>.
                </span>
            </label>

            <p v-if="auth.error" class="text-sm text-rose-600 dark:text-rose-400">{{ auth.error }}</p>

            <button :disabled="auth.loading || form.roles.length === 0 || !form.accept_terms"
                class="w-full py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                {{ auth.loading ? 'Création…' : 'Créer mon compte' }}
            </button>

            <!-- Divider -->
            <div class="flex items-center gap-3 my-1">
                <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                <span class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">ou</span>
                <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
            </div>

            <!-- Google OAuth — sign up via Google in one click. The default
                 role on first OAuth login is "investor"; the user can pick
                 their actual role afterwards in the dashboard. -->
            <a href="/auth/google/redirect"
                class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-sm font-semibold">
                <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M21.6 12.227c0-.71-.064-1.391-.182-2.045H12v3.868h5.382a4.6 4.6 0 0 1-1.995 3.018v2.51h3.227c1.886-1.737 2.986-4.295 2.986-7.351z"/>
                    <path fill="#34A853" d="M12 22c2.7 0 4.964-.895 6.618-2.422l-3.227-2.51c-.895.6-2.04.954-3.391.954-2.605 0-4.81-1.76-5.595-4.123H3.073v2.59A9.997 9.997 0 0 0 12 22z"/>
                    <path fill="#FBBC05" d="M6.405 13.9a6.018 6.018 0 0 1 0-3.8V7.51H3.073a9.997 9.997 0 0 0 0 8.98l3.332-2.59z"/>
                    <path fill="#EA4335" d="M12 5.977c1.469 0 2.787.504 3.823 1.495l2.866-2.866C16.96 2.99 14.7 2 12 2A9.997 9.997 0 0 0 3.073 7.51l3.332 2.59C7.19 7.737 9.395 5.977 12 5.977z"/>
                </svg>
                S'inscrire avec Google
            </a>

            <p class="text-sm text-center text-slate-600 dark:text-slate-400">
                Déjà inscrit ?
                <router-link to="/connexion"
                    class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Se connecter</router-link>
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
    accept_terms: false,
});

async function submit() {
    if (!form.accept_terms) return;
    // strip accept_terms before sending — backend doesn't need it
    const { accept_terms, ...payload } = form;
    if (await auth.register(payload)) {
        router.push('/dashboard');
    }
}
</script>
