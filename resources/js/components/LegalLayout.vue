<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
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

        <article class="space-y-6 text-slate-700 dark:text-slate-200 leading-relaxed">
            <slot />
        </article>

        <footer class="mt-12 pt-6 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-500 dark:text-slate-400">
            <p>
                Questions ? Contactez-nous à
                <a href="mailto:contact@globalafrica.plus" class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">contact@globalafrica.plus</a>
                ou le DPO à
                <a href="mailto:dpo@globalafrica.plus" class="text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">dpo@globalafrica.plus</a>.
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

<style scoped>
article :deep(h2) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: rgb(15 23 42); /* slate-900 */
}
:global(html.dark) article :deep(h2) {
    color: rgb(241 245 249); /* slate-100 */
}
article :deep(h3) {
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: rgb(30 41 59); /* slate-800 */
}
:global(html.dark) article :deep(h3) {
    color: rgb(226 232 240); /* slate-200 */
}
article :deep(p) { margin-bottom: 0.75rem; }
article :deep(ul) { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
article :deep(ul li) { margin-bottom: 0.35rem; }
article :deep(a) { color: rgb(4 120 87); text-decoration: underline; }
:global(html.dark) article :deep(a) { color: rgb(52 211 153); }
article :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    font-size: 0.9rem;
}
article :deep(th), article :deep(td) {
    border: 1px solid rgb(226 232 240);
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
}
:global(html.dark) article :deep(th),
:global(html.dark) article :deep(td) {
    border-color: rgb(51 65 85);
}
article :deep(th) {
    background: rgb(248 250 252);
    font-weight: 700;
}
:global(html.dark) article :deep(th) {
    background: rgb(30 41 59);
}
</style>
