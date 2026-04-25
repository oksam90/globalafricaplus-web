<template>
    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-gold-50 via-white to-brand-red-50 dark:from-brand-black dark:via-slate-900 dark:to-brand-black"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-brand-gold-100 dark:bg-brand-gold-900/40 text-brand-gold-700 dark:text-brand-gold-400 text-xs font-semibold mb-4 border border-brand-gold-200/60 dark:border-brand-gold-700/40">
                        Plateforme panafricaine
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-white leading-[1.05]">
                        Connecter l'Afrique
                        <span class="block bg-gradient-to-r from-brand-gold-500 via-brand-red-500 to-brand-gold-600 bg-clip-text text-transparent">
                            et sa Diaspora.
                        </span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl">
                        GlobalAfrica<span class="text-brand-red-500 font-bold">+</span> transforme les remittances en investissements stratégiques, facilite la
                        collaboration intercontinentale et crée des millions d'emplois durables.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <router-link to="/inscription" class="px-5 py-3 rounded-lg bg-brand-red-500 hover:bg-brand-red-600 text-white font-semibold shadow-md shadow-brand-red-500/20">
                            Rejoindre la plateforme
                        </router-link>
                        <router-link to="/projets" class="px-5 py-3 rounded-lg bg-white dark:bg-brand-black-50 border border-brand-gold-300 dark:border-brand-gold-700/50 hover:border-brand-gold-500 dark:hover:border-brand-gold-500 font-semibold text-brand-gold-700 dark:text-brand-gold-400">
                            Explorer les projets →
                        </router-link>
                    </div>
                </div>

                <div class="relative">
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="(s, i) in heroStats" :key="i" class="bg-white dark:bg-brand-black-50 rounded-2xl p-5 shadow-sm border border-brand-gold-200/60 dark:border-brand-gold-700/30">
                            <div class="text-2xl font-black text-brand-gold-600 dark:text-brand-gold-400">{{ s.value }}</div>
                            <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">{{ s.label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats live -->
    <section class="bg-white dark:bg-brand-black border-y border-brand-gold-200/40 dark:border-brand-gold-700/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid grid-cols-2 md:grid-cols-4 gap-6">
            <div v-for="stat in liveStats" :key="stat.label" class="text-center">
                <div class="text-3xl font-black text-brand-gold-600 dark:text-brand-gold-400">{{ stat.value }}</div>
                <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-1">{{ stat.label }}</div>
            </div>
        </div>
    </section>

    <!-- Modules -->
    <section class="py-20 bg-brand-gold-50/40 dark:bg-brand-black-50/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">Une infrastructure numérique panafricaine</h2>
                <p class="mt-4 text-slate-600 dark:text-slate-400">10 modules intégrés pour libérer le potentiel économique du continent.</p>
            </div>
            <div class="mt-12 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="m in modules" :key="m.title" class="bg-white dark:bg-brand-black rounded-2xl p-6 border border-brand-gold-200/50 dark:border-brand-gold-700/20 hover:border-brand-gold-400 dark:hover:border-brand-gold-500 hover:shadow-md transition">
                    <div class="text-3xl mb-3">{{ m.icon }}</div>
                    <h3 class="font-bold text-lg text-slate-900 dark:text-white">{{ m.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ m.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ Ad Banner Carousel (Diaporama) ═══ -->
    <section v-if="banners.length" class="relative bg-slate-900 overflow-hidden">
        <div class="relative h-[420px] md:h-[480px]">
            <transition-group name="slide-fade">
                <div v-for="(b, idx) in banners" :key="b.id" v-show="idx === activeBanner"
                    class="absolute inset-0 flex items-center">
                    <!-- Background image overlay -->
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-900 via-slate-900/80 to-transparent z-10"></div>
                    <img :src="b.image_url" :alt="b.title"
                        class="absolute inset-0 w-full h-full object-cover opacity-40" />
                    <!-- Content -->
                    <div class="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                        <div class="max-w-2xl">
                            <div v-if="b.sponsor" class="flex items-center gap-2 mb-4">
                                <img v-if="b.sponsor_logo" :src="b.sponsor_logo" :alt="b.sponsor"
                                    class="h-8 w-auto bg-white/10 rounded px-2 py-1" />
                                <span class="text-brand-gold-400 text-xs font-semibold uppercase tracking-wider">
                                    Sponsorisé par {{ b.sponsor }}
                                </span>
                            </div>
                            <h2 class="text-3xl md:text-4xl lg:text-5xl font-black text-white leading-tight">
                                {{ b.title }}
                            </h2>
                            <p v-if="b.subtitle" class="mt-2 text-lg text-brand-gold-400 font-semibold">
                                {{ b.subtitle }}
                            </p>
                            <p class="mt-4 text-slate-300 max-w-xl line-clamp-3">
                                {{ b.description }}
                            </p>
                            <a v-if="b.cta_url && b.cta_text" :href="b.cta_url"
                                @click.prevent="clickBanner(b)"
                                class="mt-6 inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-brand-red-500 hover:bg-brand-red-600 text-white font-semibold shadow-lg shadow-brand-red-500/30 transition">
                                {{ b.cta_text }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </transition-group>

            <!-- Navigation dots -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-30 flex gap-2">
                <button v-for="(b, idx) in banners" :key="'dot-'+idx"
                    @click="activeBanner = idx; resetAutoSlide()"
                    class="w-3 h-3 rounded-full transition-all"
                    :class="idx === activeBanner ? 'bg-brand-gold-500 w-8' : 'bg-white/40 hover:bg-white/60'">
                </button>
            </div>
            <!-- Prev / Next -->
            <button @click="prevBanner" class="absolute left-3 top-1/2 -translate-y-1/2 z-30 p-2 rounded-full bg-black/30 hover:bg-black/50 text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </button>
            <button @click="nextBanner" class="absolute right-3 top-1/2 -translate-y-1/2 z-30 p-2 rounded-full bg-black/30 hover:bg-black/50 text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
        </div>
    </section>

    <!-- Featured projects -->
    <section class="py-20 bg-white dark:bg-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">Projets à la une</h2>
                    <p class="text-slate-600 dark:text-slate-400 mt-2">Des initiatives concrètes portées par la jeunesse africaine.</p>
                </div>
                <router-link to="/projets" class="hidden sm:inline text-sm font-semibold text-brand-red-500 dark:text-brand-red-400 hover:text-brand-red-600 dark:hover:text-brand-red-300">
                    Voir tous →
                </router-link>
            </div>
            <div v-if="loading" class="text-slate-500 dark:text-slate-400">Chargement…</div>
            <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <ProjectCard v-for="p in featured" :key="p.id" :project="p" />
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="py-20 bg-brand-gold-50/40 dark:bg-brand-black-50/40" id="tarifs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-4">
                <span class="inline-block px-3 py-1 rounded-full bg-brand-gold-100 dark:bg-brand-gold-900/40 text-brand-gold-700 dark:text-brand-gold-400 text-xs font-semibold mb-3 border border-brand-gold-200/60 dark:border-brand-gold-700/30">
                    Modèle Économique
                </span>
                <h2 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">Packs & Tarifs</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">Choisissez le pack adapté à votre profil. Garantie satisfait ou remboursé de 30 jours.</p>
            </div>

            <!-- Toggle -->
            <div class="flex items-center justify-center gap-3 mb-10">
                <span class="text-sm text-slate-500 dark:text-slate-400" :class="{ 'font-bold text-slate-900 dark:text-white': !yearlyToggle }">Mensuel</span>
                <button @click="yearlyToggle = !yearlyToggle"
                    class="relative inline-flex h-7 w-14 items-center rounded-full transition-colors"
                    :class="yearlyToggle ? 'bg-brand-gold-500' : 'bg-slate-300 dark:bg-slate-600'">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                        :class="yearlyToggle ? 'translate-x-8' : 'translate-x-1'"></span>
                </button>
                <span class="text-sm text-slate-500 dark:text-slate-400" :class="{ 'font-bold text-slate-900 dark:text-white': yearlyToggle }">
                    Annuel <span class="text-brand-red-500 dark:text-brand-red-400 font-semibold text-xs">-17%</span>
                </span>
            </div>

            <!-- Plans -->
            <div v-if="pricingPlans.length" class="grid md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div v-for="plan in pricingPlans" :key="plan.slug"
                    class="relative bg-white dark:bg-brand-black rounded-2xl border-2 p-5 flex flex-col"
                    :class="plan.is_popular ? 'border-brand-gold-500 shadow-md shadow-brand-gold-500/20' : 'border-brand-gold-200/50 dark:border-brand-gold-700/20'">
                    <div v-if="plan.is_popular"
                        class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-brand-red-500 text-white text-[11px] font-bold rounded-full whitespace-nowrap shadow">
                        Le plus populaire
                    </div>
                    <div class="text-center mb-4">
                        <h3 class="font-black text-lg text-slate-900 dark:text-white">{{ plan.name }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ plan.subtitle }}</p>
                        <div class="mt-3">
                            <template v-if="plan.slug === 'free'">
                                <span class="text-3xl font-black text-slate-900 dark:text-white">0 €</span>
                            </template>
                            <template v-else>
                                <span class="text-3xl font-black text-brand-gold-600 dark:text-brand-gold-400">
                                    {{ yearlyToggle ? fmtPrice(plan.price_yearly) : fmtPrice(plan.price_monthly) }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ yearlyToggle ? '/an' : '/mois' }}</span>
                                <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 mt-1">
                                    &asymp; {{ fmtXof(yearlyToggle ? plan.price_yearly : plan.price_monthly) }}
                                </div>
                            </template>
                        </div>
                    </div>
                    <ul class="space-y-1.5 mb-5 flex-1 text-sm text-slate-700 dark:text-slate-300">
                        <li v-for="(f, i) in (plan.features || []).slice(0, 5)" :key="i" class="flex items-start gap-2">
                            <span class="text-brand-gold-500 shrink-0">&#10003;</span>
                            <span>{{ f }}</span>
                        </li>
                        <li v-if="(plan.features || []).length > 5" class="text-xs text-slate-400 dark:text-slate-500">
                            + {{ plan.features.length - 5 }} autres avantages
                        </li>
                    </ul>
                    <router-link to="/tarifs"
                        class="block w-full py-2.5 rounded-lg text-center text-sm font-semibold transition-colors"
                        :class="plan.is_popular
                            ? 'bg-brand-red-500 hover:bg-brand-red-600 text-white'
                            : 'bg-brand-black dark:bg-brand-black-50 hover:bg-slate-800 dark:hover:bg-slate-700 text-white'">
                        {{ plan.slug === 'free' ? 'Commencer' : 'Voir les détails' }}
                    </router-link>
                </div>
            </div>

            <!-- Guarantees -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-8 text-sm text-slate-500 dark:text-slate-400">
                <span>🛡️ Garantie 30 jours</span>
                <span>🔓 Annulation libre</span>
                <span>💬 Support 24/7</span>
            </div>
        </div>
    </section>

    <!-- ═══ Partners – Ils nous font confiance ═══ -->
    <section v-if="partners.length" class="py-16 bg-white dark:bg-brand-black overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <span class="inline-block px-3 py-1 rounded-full bg-brand-gold-100 dark:bg-brand-gold-900/40 text-brand-gold-700 dark:text-brand-gold-400 text-xs font-semibold mb-3 border border-brand-gold-200/60 dark:border-brand-gold-700/30">
                    Nos partenaires
                </span>
                <h2 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">Ils nous font confiance</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">Institutions, organisations et entreprises technologiques qui soutiennent l'écosystème GlobalAfrica<span class="text-brand-red-500 font-bold">+</span>.</p>
            </div>
            <!-- Marquee / auto-scrolling logos -->
            <div class="relative">
                <div class="flex animate-marquee gap-12 items-center">
                    <a v-for="p in [...partners, ...partners]" :key="p.slug + Math.random()"
                        :href="p.website" target="_blank" rel="noopener"
                        class="shrink-0 grayscale hover:grayscale-0 opacity-60 hover:opacity-100 transition duration-300"
                        :title="p.name">
                        <img :src="p.logo_url" :alt="p.name" class="h-12 md:h-14 w-auto max-w-[140px] object-contain" />
                    </a>
                </div>
            </div>
            <!-- Partner type badges -->
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <span v-for="type in partnerTypes" :key="type.key"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
                    :class="type.classes">
                    {{ type.icon }} {{ type.label }} ({{ type.count }})
                </span>
            </div>
        </div>
    </section>

    <!-- ═══ Testimonials – Témoignages ═══ -->
    <section v-if="testimonials.length" class="py-20 bg-gradient-to-b from-brand-gold-50/40 to-white dark:from-brand-black-50/40 dark:to-brand-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="inline-block px-3 py-1 rounded-full bg-brand-red-50 dark:bg-brand-red-900/30 text-brand-red-600 dark:text-brand-red-400 text-xs font-semibold mb-3 border border-brand-red-200/50 dark:border-brand-red-800/40">
                    Témoignages
                </span>
                <h2 class="text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">Ce que disent nos utilisateurs</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">Entrepreneurs, investisseurs, mentors et talents partagent leur expérience.</p>
            </div>

            <!-- Featured testimonials (large cards) -->
            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <div v-for="t in featuredTestimonials" :key="t.id"
                    class="bg-white dark:bg-brand-black-50 rounded-2xl p-6 border border-brand-gold-200/50 dark:border-brand-gold-700/20 shadow-sm hover:shadow-md hover:border-brand-gold-400 dark:hover:border-brand-gold-500/50 transition relative">
                    <!-- Quote icon -->
                    <div class="absolute -top-3 left-6 text-4xl text-brand-gold-400 dark:text-brand-gold-600 font-serif leading-none">"</div>
                    <!-- Rating -->
                    <div class="flex gap-0.5 mb-3">
                        <span v-for="s in 5" :key="s" class="text-sm"
                            :class="s <= t.rating ? 'text-brand-gold-500' : 'text-slate-200 dark:text-slate-600'">★</span>
                    </div>
                    <p class="text-slate-700 dark:text-slate-300 text-sm leading-relaxed line-clamp-5">{{ t.content }}</p>
                    <div class="mt-5 flex items-center gap-3 pt-4 border-t border-brand-gold-200/30 dark:border-brand-gold-700/20">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-gold-400 to-brand-gold-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                            {{ t.author_name.charAt(0) }}
                        </div>
                        <div>
                            <div class="font-bold text-sm text-slate-900 dark:text-white">{{ t.author_name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.author_role }}</div>
                        </div>
                        <span v-if="t.author_country" class="ml-auto text-xs bg-brand-gold-50 dark:bg-brand-gold-900/30 text-brand-gold-700 dark:text-brand-gold-400 px-2 py-1 rounded-full">
                            {{ t.author_country }}
                        </span>
                    </div>
                    <div v-if="t.project_title" class="mt-3 text-xs text-brand-red-500 dark:text-brand-red-400 font-semibold">
                        📌 Projet : {{ t.project_title }}
                    </div>
                </div>
            </div>

            <!-- Other testimonials (compact) -->
            <div v-if="otherTestimonials.length" class="grid md:grid-cols-2 gap-4">
                <div v-for="t in otherTestimonials" :key="t.id"
                    class="flex items-start gap-4 bg-white dark:bg-brand-black-50 rounded-xl p-4 border border-brand-gold-200/40 dark:border-brand-gold-700/20">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-gold-500 to-brand-red-500 flex items-center justify-center text-white font-bold text-xs shrink-0">
                        {{ t.author_name.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-slate-900 dark:text-white">{{ t.author_name }}</span>
                            <span class="flex gap-0.5">
                                <span v-for="s in 5" :key="s" class="text-[10px]"
                                    :class="s <= t.rating ? 'text-brand-gold-500' : 'text-slate-200 dark:text-slate-600'">★</span>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ t.author_role }}</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300 line-clamp-2">{{ t.content }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="relative py-20 bg-brand-black text-white overflow-hidden">
        <!-- Decorative gold ring inspired by logo -->
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full border-4 border-brand-gold-500/20 pointer-events-none"></div>
        <div class="absolute -bottom-40 -left-20 w-80 h-80 rounded-full border-2 border-brand-red-500/15 pointer-events-none"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-black">
                Prêt à investir dans l'<span class="text-brand-gold-400">Afrique</span> de demain&nbsp;<span class="text-brand-red-500">?</span>
            </h2>
            <p class="mt-4 text-slate-300">
                Rejoignez des milliers d'entrepreneurs, d'investisseurs et de mentors qui construisent l'avenir.
            </p>
            <router-link to="/inscription"
                class="mt-8 inline-block px-6 py-3 rounded-lg bg-brand-red-500 text-white font-semibold hover:bg-brand-red-600 shadow-lg shadow-brand-red-500/30 transition">
                Créer mon compte gratuitement
            </router-link>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ProjectCard from '../components/ProjectCard.vue';

const featured = ref([]);
const loading = ref(true);
const stats = ref({});
const pricingPlans = ref([]);
const yearlyToggle = ref(false);

// Advertising data
const banners = ref([]);
const activeBanner = ref(0);
const partners = ref([]);
const testimonials = ref([]);
let autoSlideTimer = null;

function fmtPrice(price) {
    return parseFloat(price).toFixed(2).replace('.', ',') + ' \u20AC';
}

const EUR_TO_XOF = 655.957;
const xofFmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 });
function fmtXof(priceEur) {
    const n = parseFloat(priceEur) || 0;
    return xofFmt.format(Math.round(n * EUR_TO_XOF));
}

// ─── Banner carousel controls ─────────────────
function nextBanner() {
    activeBanner.value = (activeBanner.value + 1) % banners.value.length;
    resetAutoSlide();
}
function prevBanner() {
    activeBanner.value = (activeBanner.value - 1 + banners.value.length) % banners.value.length;
    resetAutoSlide();
}
function resetAutoSlide() {
    clearInterval(autoSlideTimer);
    if (banners.value.length > 1) {
        autoSlideTimer = setInterval(() => {
            activeBanner.value = (activeBanner.value + 1) % banners.value.length;
        }, 6000);
    }
}
async function clickBanner(b) {
    try { await window.axios.post(`/api/advertising/banners/${b.id}/click`); } catch {}
    if (b.cta_url && b.cta_url !== '#') {
        if (b.cta_url.startsWith('http')) {
            window.open(b.cta_url, '_blank');
        } else {
            window.location.href = b.cta_url;
        }
    }
}

// ─── Testimonials computed ─────────────────────
const featuredTestimonials = computed(() => testimonials.value.filter(t => t.is_featured).slice(0, 3));
const otherTestimonials = computed(() => testimonials.value.filter(t => !t.is_featured));

// ─── Partner types computed ────────────────────
const typeConfig = {
    financial:     { label: 'Finance', icon: '🏦', classes: 'bg-brand-gold-100 text-brand-gold-700 dark:bg-brand-gold-900/30 dark:text-brand-gold-400' },
    institutional: { label: 'Institutions', icon: '🏛️', classes: 'bg-brand-red-50 text-brand-red-600 dark:bg-brand-red-900/30 dark:text-brand-red-400' },
    tech:          { label: 'Tech', icon: '💻', classes: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' },
    government:    { label: 'Gouvernement', icon: '🇸🇳', classes: 'bg-brand-gold-50 text-brand-gold-600 dark:bg-brand-gold-900/20 dark:text-brand-gold-400' },
    ngo:           { label: 'ONG', icon: '🌐', classes: 'bg-brand-red-100 text-brand-red-700 dark:bg-brand-red-900/40 dark:text-brand-red-300' },
    media:         { label: 'Média', icon: '📰', classes: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' },
};
const partnerTypes = computed(() => {
    const counts = {};
    partners.value.forEach(p => { counts[p.type] = (counts[p.type] || 0) + 1; });
    return Object.entries(counts).map(([key, count]) => ({
        key,
        count,
        ...(typeConfig[key] || { label: key, icon: '🔹', classes: 'bg-slate-100 text-slate-700' }),
    }));
});

const heroStats = [
    { value: '54', label: 'Pays africains' },
    { value: '100Md$', label: 'Remittances annuelles' },
    { value: '85%', label: 'Emploi informel' },
    { value: '4 langues', label: 'FR · EN · PT · AR' },
];

const liveStats = ref([
    { value: '0', label: 'Projets publiés' },
    { value: '0', label: 'Membres' },
    { value: '0', label: 'Pays actifs' },
    { value: '0 €', label: 'Levés' },
]);

const modules = [
    { icon: '👥', title: 'Multi-rôles', text: 'Entrepreneurs, investisseurs, gouvernements, mentors et talents sur une même plateforme.' },
    { icon: '🚀', title: 'Projets sectoriels', text: 'Catalogue de projets agritech, fintech, énergie, santé, éducation…' },
    { icon: '🌍', title: 'Portail Diaspora', text: 'Carte interactive, simulateur d\'investissement, guide pays.' },
    { icon: '🛡️', title: 'eKYC & escrow', text: 'Vérification d\'identité multi-niveaux et transactions sécurisées.' },
    { icon: '💸', title: 'Mobile money', text: 'Paiements panafricains via Stripe, Flutterwave et opérateurs locaux.' },
    { icon: '🤖', title: 'IA & matching', text: 'Mise en relation intelligente projets ↔ investisseurs ↔ talents.' },
    { icon: '🎓', title: 'Mentorat', text: 'Marketplace de compétences et accompagnement par des experts.' },
    { icon: '📜', title: 'Formalisation', text: 'Hub d\'aide à la formalisation pour l\'économie informelle.' },
    { icon: '🏛️', title: 'Gouvernement', text: 'Appels à projets publics et zones économiques spéciales.' },
];

async function load() {
    try {
        const [
            { data: projects },
            { data: s },
            { data: plansData },
            { data: bannersData },
            { data: partnersData },
            { data: testimonialsData },
        ] = await Promise.all([
            window.axios.get('/api/projects', { params: { per_page: 3 } }),
            window.axios.get('/api/stats'),
            window.axios.get('/api/subscription/plans'),
            window.axios.get('/api/advertising/banners'),
            window.axios.get('/api/advertising/partners'),
            window.axios.get('/api/advertising/testimonials'),
        ]);
        featured.value = (projects.data || []).slice(0, 3);
        stats.value = s;
        pricingPlans.value = plansData.data || [];
        banners.value = bannersData.data || [];
        partners.value = partnersData.data || [];
        testimonials.value = testimonialsData.data || [];

        const fmt = new Intl.NumberFormat('fr-FR');
        liveStats.value = [
            { value: fmt.format(s.projects_count || 0), label: 'Projets publiés' },
            { value: fmt.format(s.users_count || 0), label: 'Membres' },
            { value: fmt.format(s.countries_count || 0), label: 'Pays actifs' },
            { value: fmt.format(s.amount_raised || 0) + ' €', label: 'Levés' },
        ];

        // Start auto-slide for banners
        resetAutoSlide();
    } finally {
        loading.value = false;
    }
}

onMounted(load);
onUnmounted(() => clearInterval(autoSlideTimer));
</script>

<style scoped>
/* Marquee animation for partner logos */
@keyframes marquee {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.animate-marquee {
    animation: marquee 30s linear infinite;
}
.animate-marquee:hover {
    animation-play-state: paused;
}
/* Banner slide transition */
.slide-fade-enter-active { transition: all .6s ease-out; }
.slide-fade-leave-active { transition: all .4s ease-in; }
.slide-fade-enter-from   { opacity: 0; transform: translateX(40px); }
.slide-fade-leave-to     { opacity: 0; transform: translateX(-40px); }
</style>
