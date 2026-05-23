<template>
    <Teleport to="body">
        <div aria-live="polite" aria-atomic="true"
            class="fixed bottom-6 right-6 z-[100] flex flex-col gap-2 max-w-sm pointer-events-none">
            <TransitionGroup name="ga-toast" tag="div" class="flex flex-col gap-2">
                <div v-for="t in toast.toasts" :key="t.id"
                    role="status"
                    class="pointer-events-auto px-5 py-3 rounded-lg shadow-lg text-sm font-medium flex items-start gap-3 border"
                    :class="classFor(t.tone)">
                    <span class="text-base shrink-0" aria-hidden="true">{{ iconFor(t.tone) }}</span>
                    <span class="flex-1">{{ t.message }}</span>
                    <button @click="toast.dismiss(t.id)"
                        :aria-label="'Fermer la notification'"
                        class="opacity-60 hover:opacity-100 -mr-1 -my-1 px-2">
                        ×
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useToast } from '../composables/useToast';
const toast = useToast();

function classFor(tone) {
    return {
        success: 'bg-emerald-600 border-emerald-700 text-white',
        error:   'bg-rose-600 border-rose-700 text-white',
        warn:    'bg-amber-500 border-amber-600 text-white',
        info:    'bg-slate-800 border-slate-900 text-white',
    }[tone] || 'bg-slate-800 border-slate-900 text-white';
}

function iconFor(tone) {
    return {
        success: '✓',
        error:   '✕',
        warn:    '⚠',
        info:    'ℹ',
    }[tone] || 'ℹ';
}
</script>

<style scoped>
.ga-toast-enter-active, .ga-toast-leave-active { transition: all 220ms ease; }
.ga-toast-enter-from { opacity: 0; transform: translateY(8px) scale(0.98); }
.ga-toast-leave-to   { opacity: 0; transform: translateX(20px); }
.ga-toast-move       { transition: transform 220ms ease; }
</style>
