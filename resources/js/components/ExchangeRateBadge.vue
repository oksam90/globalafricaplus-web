<template>
    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800/50 text-xs font-semibold text-amber-700 dark:text-amber-300">
        <span class="text-base leading-none">💱</span>
        <span>1 EUR = {{ formattedRate }} XOF</span>
        <span v-if="source === 'live'" class="text-[10px] uppercase tracking-wide text-emerald-600 dark:text-emerald-400">live</span>
        <span v-else class="text-[10px] uppercase tracking-wide text-slate-500">peg</span>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useExchangeRate } from '../composables/useExchangeRate';

const { rate, source, fetchRate } = useExchangeRate();

const formattedRate = computed(() => {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(rate.value);
});

onMounted(() => fetchRate('EUR', 'XOF'));
</script>
