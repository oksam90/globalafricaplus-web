<template>
    <div class="min-h-full flex flex-col bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
        <SiteHeader />
        <NavigationLoader />
        <main class="flex-1">
            <!--
                Plain <router-view> — Vue Router already unmounts/remounts
                when the route path changes, so onMounted() fires on each
                navigation. Pages with filters (Mentorat, Gouvernement,
                Projets) also watch `route.query` to refetch when only
                the query string changes. The previous :key="route.fullPath"
                + <transition mode="out-in"> combo caused blank renders on
                some browsers because async components and key-forced
                remounts don't compose well with out-in transitions.
            -->
            <router-view />
        </main>
        <SiteFooter />
    </div>
</template>

<script setup>
import SiteHeader from './components/SiteHeader.vue';
import SiteFooter from './components/SiteFooter.vue';
import NavigationLoader from './components/NavigationLoader.vue';
import { useThemeStore } from './stores/theme';
// Init theme store early so dark class is applied before first paint
useThemeStore();
// Auth bootstrap is handled exclusively by the router guard (beforeEach).
</script>
