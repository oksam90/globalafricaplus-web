<template>
    <section class="max-w-md mx-auto px-4 py-16">
        <h1 class="text-3xl font-black tracking-tight text-center">Connexion</h1>
        <p class="text-center text-slate-600 mt-2 text-sm">Accédez à votre espace Africa+.</p>

        <form @submit.prevent="submit" class="mt-8 bg-white border border-slate-100 rounded-2xl p-6 space-y-4 shadow-sm">
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input v-model="form.email" type="email" required
                    class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Mot de passe</label>
                <input v-model="form.password" type="password" required
                    class="w-full px-3 py-2 rounded-md border border-slate-200 focus:border-emerald-400 focus:outline-none" />
            </div>

            <p v-if="auth.error" class="text-sm text-rose-600">{{ auth.error }}</p>

            <button :disabled="auth.loading" class="w-full py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold disabled:opacity-60">
                {{ auth.loading ? 'Connexion…' : 'Se connecter' }}
            </button>

            <p class="text-sm text-center text-slate-600">
                Pas de compte ?
                <router-link to="/inscription" class="text-emerald-700 font-semibold hover:underline">S'inscrire</router-link>
            </p>
        </form>

        <div class="mt-6 text-xs text-slate-500 text-center">
            Comptes de démo : <code>aminata@africaplus.test</code> / <code>password</code>
        </div>
    </section>
</template>

<script setup>
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '' });

async function submit() {
    if (await auth.login(form)) {
        router.push(route.query.redirect || '/dashboard');
    }
}
</script>
