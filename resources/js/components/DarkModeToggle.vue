<template>
    <button @click="theme.toggle()"
        class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg border transition-all duration-300"
        :class="theme.isDark
            ? 'border-brand-gold-700/50 bg-brand-black-50 hover:bg-slate-700 text-brand-gold-400'
            : 'border-brand-gold-200 bg-white hover:bg-brand-gold-50 text-brand-gold-600'"
        :title="theme.isDark ? 'Passer en mode clair' : 'Passer en mode sombre'"
        aria-label="Basculer le mode sombre">

        <!-- Sun icon (shown in dark mode → click to go light) -->
        <transition name="icon-spin">
            <svg v-if="theme.isDark" key="sun" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41m12.73-12.73 1.41-1.41"/>
            </svg>

            <!-- Moon icon (shown in light mode → click to go dark) -->
            <svg v-else key="moon" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </transition>
    </button>
</template>

<script setup>
import { useThemeStore } from '../stores/theme';
const theme = useThemeStore();
</script>

<style scoped>
.icon-spin-enter-active { transition: all .3s ease-out; }
.icon-spin-leave-active { transition: all .2s ease-in; position: absolute; }
.icon-spin-enter-from { opacity: 0; transform: rotate(-90deg) scale(0.5); }
.icon-spin-leave-to   { opacity: 0; transform: rotate(90deg) scale(0.5); }
</style>
