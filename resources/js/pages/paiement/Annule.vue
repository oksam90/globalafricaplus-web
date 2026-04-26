<template>
    <div class="min-h-[70vh] flex items-center justify-center px-4">
        <div class="max-w-xl w-full bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-lg p-8 text-center">
            <div class="text-5xl mb-3">🛑</div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Paiement annulé</h1>
            <p class="mt-3 text-slate-600 dark:text-slate-300">
                Vous avez interrompu le processus de paiement. Aucun montant n'a été débité.
            </p>
            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                <router-link to="/tarifs"
                    class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                    Retour aux tarifs
                </router-link>
                <router-link to="/dashboard"
                    class="px-5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold">
                    Tableau de bord
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
// No logic — PayDunya simply redirects here when the user cancels.
</script>
