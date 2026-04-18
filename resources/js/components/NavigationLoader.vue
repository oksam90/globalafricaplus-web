<template>
    <transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div v-if="show" class="fixed top-0 left-0 right-0 z-[60] h-0.5 overflow-hidden pointer-events-none">
            <div class="nav-bar h-full bg-gradient-to-r from-brand-gold-500 via-brand-red-500 to-brand-gold-500"></div>
        </div>
    </transition>

    <!-- Centered overlay spinner while a route is resolving -->
    <transition
        enter-active-class="transition ease-out duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div v-if="showOverlay"
            class="fixed inset-0 z-40 flex items-center justify-center bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm pointer-events-none">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-10 w-10 text-brand-gold-500" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-85" fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Chargement…</span>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useUiStore } from '../stores/ui';

const ui = useUiStore();
const show = ref(false);        // top bar — always on as soon as navigating
const showOverlay = ref(false); // centered spinner — only after a short delay

let overlayTimer;

watch(
    () => ui.navigating,
    (nav) => {
        clearTimeout(overlayTimer);
        if (nav) {
            show.value = true;
            // Only display the full-screen overlay if navigation takes >250 ms,
            // so fast transitions don't show a blink.
            overlayTimer = setTimeout(() => { showOverlay.value = true; }, 250);
        } else {
            show.value = false;
            showOverlay.value = false;
        }
    },
    { immediate: true }
);
</script>

<style scoped>
.nav-bar {
    width: 40%;
    animation: nav-slide 1.1s ease-in-out infinite;
}
@keyframes nav-slide {
    0%   { transform: translateX(-100%); }
    50%  { transform: translateX(120%); }
    100% { transform: translateX(260%); }
}
</style>
