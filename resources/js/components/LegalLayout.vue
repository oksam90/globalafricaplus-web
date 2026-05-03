<template>
    <section class="legal-layout max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <header class="mb-8 pb-6 border-b border-slate-200 dark:border-slate-700">
            <p class="text-xs font-semibold uppercase tracking-wider text-brand-gold-600 dark:text-brand-gold-400">
                GlobalAfrica+ — {{ kicker }}
            </p>
            <h1 class="mt-2 text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-3 text-slate-600 dark:text-slate-300">{{ subtitle }}</p>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Version {{ version }} — Dernière mise à jour : {{ updated }}
            </p>
        </header>

        <article class="legal-article space-y-6 text-slate-700 dark:text-slate-200 leading-relaxed">
            <slot />
        </article>

        <footer class="mt-12 pt-6 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-500 dark:text-slate-400">
            <p>
                Questions ? Contactez-nous à
                <a href="mailto:contact@globalafricaplus.com" class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">contact@globalafricaplus.com</a>
                ou le DPO à
                <a href="mailto:dpo@globalafricaplus.com" class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">dpo@globalafricaplus.com</a>.
            </p>
            <div class="mt-4 flex flex-wrap gap-4">
                <router-link to="/cgu" class="hover:text-emerald-700 dark:hover:text-emerald-400">Conditions d'utilisation</router-link>
                <router-link to="/confidentialite" class="hover:text-emerald-700 dark:hover:text-emerald-400">Politique de confidentialité</router-link>
                <router-link to="/dpa" class="hover:text-emerald-700 dark:hover:text-emerald-400">Accord de traitement des données</router-link>
            </div>
        </footer>
    </section>
</template>

<script setup>
defineProps({
    kicker: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    version: { type: String, default: '1.0' },
    updated: { type: String, default: 'Avril 2026' },
});
</script>

<!--
    Non-scoped style on purpose: the slot's content (h2/h3/p/ul/a/table) is
    rendered by parent components, and Vue's scoped CSS would not attach the
    data-v hash to those nested nodes. We namespace via .legal-article instead.
-->
<style>
.legal-article h2 {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: rgb(15 23 42); /* slate-900 */
}
html.dark .legal-article h2 {
    color: rgb(241 245 249); /* slate-100 */
}
.legal-article h3 {
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: rgb(30 41 59); /* slate-800 */
}
html.dark .legal-article h3 {
    color: rgb(226 232 240); /* slate-200 */
}
.legal-article p { margin-bottom: 0.75rem; }
.legal-article ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.legal-article ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.legal-article ul li,
.legal-article ol li { margin-bottom: 0.35rem; }
.legal-article strong { color: rgb(15 23 42); font-weight: 700; }
html.dark .legal-article strong { color: rgb(241 245 249); }
.legal-article a {
    color: rgb(4 120 87); /* emerald-700 */
    text-decoration: underline;
    text-underline-offset: 2px;
}
.legal-article a:hover { color: rgb(6 95 70); /* emerald-800 */ }
html.dark .legal-article a { color: rgb(52 211 153); /* emerald-400 */ }
html.dark .legal-article a:hover { color: rgb(110 231 183); /* emerald-300 */ }
.legal-article blockquote {
    border-left: 3px solid rgb(203 213 225);
    padding-left: 1rem;
    color: rgb(71 85 105);
    font-style: italic;
    margin: 1rem 0;
}
html.dark .legal-article blockquote {
    border-left-color: rgb(71 85 105);
    color: rgb(148 163 184);
}
.legal-article code {
    background: rgb(241 245 249);
    color: rgb(15 23 42);
    padding: 0.1rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.85em;
}
html.dark .legal-article code {
    background: rgb(30 41 59);
    color: rgb(226 232 240);
}
.legal-article table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    font-size: 0.9rem;
}
.legal-article th,
.legal-article td {
    border: 1px solid rgb(226 232 240);
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
}
html.dark .legal-article th,
html.dark .legal-article td {
    border-color: rgb(51 65 85);
}
.legal-article th {
    background: rgb(248 250 252);
    color: rgb(15 23 42);
    font-weight: 700;
}
html.dark .legal-article th {
    background: rgb(30 41 59);
    color: rgb(241 245 249);
}
</style>
