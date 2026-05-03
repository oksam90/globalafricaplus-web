<template>
    <footer class="border-t border-brand-gold-200/50 dark:border-brand-gold-700/20 bg-white dark:bg-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid gap-10 grid-cols-2 sm:grid-cols-3 lg:grid-cols-12">
                <!-- Brand -->
                <div class="col-span-2 sm:col-span-3 lg:col-span-4">
                    <img :src="logoUrl" alt="GlobalAfrica+"
                        class="h-12 w-auto mb-4 rounded-md" />
                    <p class="text-sm text-slate-600 dark:text-slate-400 max-w-xs">
                        Connecter l'Afrique et sa Diaspora pour un développement économique inclusif&nbsp;:
                        levée de fonds en séquestre, mentorat, formalisation et formations pour entrepreneurs.
                    </p>

                    <div class="mt-5 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <a href="mailto:contact@globalafricaplus.com"
                            class="inline-flex items-center gap-2 hover:text-brand-gold-600 dark:hover:text-brand-gold-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            contact@globalafricaplus.com
                        </a>
                        <div class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Dakar, Sénégal · UEMOA
                        </div>
                    </div>

                    <!-- Socials -->
                    <div class="mt-5 flex items-center gap-3">
                        <a v-for="s in socials" :key="s.name" :href="s.url" target="_blank" rel="noopener"
                            :aria-label="s.name"
                            class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-brand-gold-600 dark:hover:text-brand-gold-400 hover:border-brand-gold-400 transition-colors">
                            <span v-html="s.icon" class="block w-4 h-4" />
                        </a>
                    </div>
                </div>

                <!-- Plateforme -->
                <div class="col-span-1 sm:col-span-1 lg:col-span-2">
                    <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Plateforme</h4>
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li><router-link to="/projets" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Projets</router-link></li>
                        <li><router-link to="/secteurs" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Secteurs &amp; ODD</router-link></li>
                        <li><router-link to="/formations" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Formations</router-link></li>
                        <li><router-link to="/mentorat" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Mentorat</router-link></li>
                        <li><router-link to="/emploi" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Emploi</router-link></li>
                        <li><router-link to="/tarifs" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Tarifs</router-link></li>
                    </ul>
                </div>

                <!-- Acteurs -->
                <div class="col-span-1 sm:col-span-1 lg:col-span-2">
                    <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Acteurs</h4>
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li><router-link to="/formalisation" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Entrepreneurs</router-link></li>
                        <li><router-link to="/projets" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Investisseurs</router-link></li>
                        <li><router-link to="/gouvernement" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Gouvernements</router-link></li>
                        <li><router-link to="/mentorat" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Mentors</router-link></li>
                        <li><router-link to="/diaspora" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Diaspora</router-link></li>
                        <li><router-link to="/emploi" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Talents</router-link></li>
                    </ul>
                </div>

                <!-- Ressources -->
                <div class="col-span-1 sm:col-span-1 lg:col-span-2">
                    <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Ressources</h4>
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li><router-link to="/formalisation" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Formalisation</router-link></li>
                        <li><router-link to="/formalisation/business-plans" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Business plans</router-link></li>
                        <li><router-link to="/diaspora" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Guide Diaspora</router-link></li>
                        <li><router-link to="/inscription" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Créer un compte</router-link></li>
                        <li><router-link to="/connexion" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Se connecter</router-link></li>
                        <li><a href="mailto:support@globalafricaplus.com" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Centre d'aide</a></li>
                    </ul>
                </div>

                <!-- Légal -->
                <div class="col-span-1 sm:col-span-1 lg:col-span-2">
                    <h4 class="font-semibold text-sm mb-3 text-brand-gold-600 dark:text-brand-gold-400 uppercase tracking-wider">Légal</h4>
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li><router-link to="/cgu" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Conditions d'utilisation</router-link></li>
                        <li><router-link to="/confidentialite" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Confidentialité (RGPD)</router-link></li>
                        <li><router-link to="/dpa" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">DPA – Sous-traitants</router-link></li>
                        <li><router-link to="/confidentialite" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">Cookies</router-link></li>
                        <li><a href="mailto:dpo@globalafricaplus.com" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">DPO &amp; sécurité</a></li>
                    </ul>
                </div>
            </div>

            <!-- Trust badges -->
            <div class="mt-10 pt-6 border-t border-brand-gold-200/30 dark:border-brand-gold-700/20 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-center gap-2">
                    <span class="text-base">🔒</span>
                    <span>Paiements sécurisés <strong class="text-slate-700 dark:text-slate-300">PayDunya</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-base">🛡️</span>
                    <span>Garantie <strong class="text-slate-700 dark:text-slate-300">satisfait ou remboursé 30j</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-base">⚖️</span>
                    <span>Fonds en <strong class="text-slate-700 dark:text-slate-300">séquestre</strong> (escrow)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-base">🇪🇺</span>
                    <span>Conforme <strong class="text-slate-700 dark:text-slate-300">RGPD</strong></span>
                </div>
            </div>
        </div>

        <div class="border-t border-brand-gold-200/30 dark:border-brand-gold-700/20 py-4 text-center text-xs text-slate-500 dark:text-slate-500">
            © {{ new Date().getFullYear() }}
            <span class="text-brand-gold-600 dark:text-brand-gold-400 font-semibold">GlobalAfrica<span class="text-brand-red-500">+</span></span>
            · Tous droits réservés ·
            <router-link to="/cgu" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">CGU</router-link>
            ·
            <router-link to="/confidentialite" class="hover:text-brand-gold-600 dark:hover:text-brand-gold-400">RGPD</router-link>
        </div>
    </footer>
</template>

<script setup>
const logoUrl = '/brand/logo-horizontal-dark.svg';

const socials = [
    {
        name: 'LinkedIn',
        url: 'https://www.linkedin.com/company/globalafricaplus',
        icon: '<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>',
    },
    {
        name: 'X / Twitter',
        url: 'https://twitter.com/globalafricapls',
        icon: '<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    },
    {
        name: 'Facebook',
        url: 'https://facebook.com/globalafricaplus',
        icon: '<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
    },
    {
        name: 'YouTube',
        url: 'https://youtube.com/@globalafricaplus',
        icon: '<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
    },
];
</script>
