<template>
    <div class="min-h-full flex flex-col bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
        <SiteHeader />
        <main class="flex-1">
            <router-view v-slot="{ Component, route }">
                <transition name="fade" mode="out-in">
                    <!--
                        :key="route.fullPath" forces a full remount whenever the URL
                        (path OR query) changes, so onMounted() refires and pages
                        reload their data — fixes the "stale data until reload" issue
                        on filtered pages (mentorat, gouvernement, …).
                    -->
                    <component :is="Component" :key="route.fullPath" />
                </transition>
            </router-view>
        </main>
        <SiteFooter />
    </div>
</template>

<script setup>
import SiteHeader from './components/SiteHeader.vue';
import SiteFooter from './components/SiteFooter.vue';
import { useThemeStore } from './stores/theme';
// Init theme store early so dark class is applied before first paint
useThemeStore();
// Auth bootstrap is handled exclusively by the router guard (beforeEach).
// Doing it here too caused a duplicate request race + stale data.
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
