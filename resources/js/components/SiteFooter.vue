<template>
    <footer class="border-t border-brand-gold-200/50 dark:border-brand-gold-700/20 bg-white dark:bg-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid gap-8 md:grid-cols-4">
            <div>
                <img :src="logoUrl" alt="GlobalAfrica+"
                    class="h-12 w-auto mb-4 rounded-md" />
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Connecter l'Afrique et sa Diaspora pour un développement économique inclusif.
                </p>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Plateforme</h4>
                <ul class="space-y-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <li><router-link to="/projets" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Projets</router-link></li>
                    <li><router-link to="/diaspora" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Portail Diaspora</router-link></li>
                    <li><router-link to="/mentorat" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Mentorat</router-link></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Acteurs</h4>
                <ul class="space-y-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <li>Entrepreneurs</li>
                    <li>Investisseurs</li>
                    <li>Gouvernements</li>
                    <li>Mentors</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Légal</h4>
                <ul class="space-y-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <li>Conditions d'utilisation</li>
                    <li>Confidentialité (RGPD)</li>
                    <li>Sécurité</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-brand-gold-200/30 dark:border-brand-gold-700/20 py-4 text-center text-xs text-slate-500 dark:text-slate-500">
            © {{ new Date().getFullYear() }} <span class="text-brand-gold-600 dark:text-brand-gold-400 font-semibold">GlobalAfrica<span class="text-brand-red-500">+</span></span>. Tous droits réservés.
        </div>
    </footer>
</template>

<script setup>
const logoUrl = '/brand/logo-horizontal-dark.svg';
</script>
