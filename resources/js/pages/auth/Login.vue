<template>
    <section class="max-w-md mx-auto px-4 py-16">
        <h1 class="text-3xl font-black tracking-tight text-center text-slate-900 dark:text-slate-100">Connexion</h1>
        <p class="text-center text-slate-600 dark:text-slate-400 mt-2 text-sm">Accédez à votre espace GlobalAfrica+.</p>

        <form @submit.prevent="submit"
            class="mt-8 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 space-y-4 shadow-sm">
            <div>
                <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Email</label>
                <input v-model="form.email" type="email" required
                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">Mot de passe</label>
                <input v-model="form.password" type="password" required
                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-emerald-400 dark:focus:border-emerald-500 focus:outline-none" />
            </div>

            <p v-if="auth.error || oauthError" class="text-sm text-rose-600 dark:text-rose-400">
                {{ auth.error || oauthError }}
            </p>

            <button :disabled="auth.loading"
                class="w-full py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                {{ auth.loading ? 'Connexion…' : 'Se connecter' }}
            </button>

            <!-- Divider -->
            <div class="flex items-center gap-3 my-1">
                <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                <span class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">ou</span>
                <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
            </div>

            <!-- Google OAuth — full-page redirect (Socialite handles state/CSRF) -->
            <a href="/auth/google/redirect"
                class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-sm font-semibold">
                <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M21.6 12.227c0-.71-.064-1.391-.182-2.045H12v3.868h5.382a4.6 4.6 0 0 1-1.995 3.018v2.51h3.227c1.886-1.737 2.986-4.295 2.986-7.351z"/>
                    <path fill="#34A853" d="M12 22c2.7 0 4.964-.895 6.618-2.422l-3.227-2.51c-.895.6-2.04.954-3.391.954-2.605 0-4.81-1.76-5.595-4.123H3.073v2.59A9.997 9.997 0 0 0 12 22z"/>
                    <path fill="#FBBC05" d="M6.405 13.9a6.018 6.018 0 0 1 0-3.8V7.51H3.073a9.997 9.997 0 0 0 0 8.98l3.332-2.59z"/>
                    <path fill="#EA4335" d="M12 5.977c1.469 0 2.787.504 3.823 1.495l2.866-2.866C16.96 2.99 14.7 2 12 2A9.997 9.997 0 0 0 3.073 7.51l3.332 2.59C7.19 7.737 9.395 5.977 12 5.977z"/>
                </svg>
                Se connecter avec Google
            </a>

            <p class="text-sm text-center text-slate-600 dark:text-slate-400">
                Pas de compte ?
                <router-link to="/inscription"
                    class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">S'inscrire</router-link>
            </p>
        </form>

        <!-- <div class="mt-6 text-xs text-slate-500 dark:text-slate-400 text-center">
            Comptes de démo : <code class="text-slate-700 dark:text-slate-200">aminata@africaplus.test</code> / <code class="text-slate-700 dark:text-slate-200">password</code>
        </div> -->
    </section>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '' });

// Surface the OAuth error returned by the backend redirect on failure.
const oauthError = computed(() => {
    const code = route.query.oauth;
    if (!code || code === 'google') return '';
    return ({
        failed: 'La connexion avec Google a échoué. Réessayez ou utilisez votre mot de passe.',
        missing_profile_data: 'Google n\'a pas renvoyé votre email — vérifiez les autorisations puis réessayez.',
    })[code] || `Erreur OAuth : ${code}`;
});

async function submit() {
    if (await auth.login(form)) {
        router.push(route.query.redirect || '/dashboard');
    }
}
</script>
